<?php

namespace App\Console\Commands;

use App\Jobs\TranscribeCdrJob;
use App\Models\Domain;
use App\Services\SrsRecorderDialplanService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class SeedRecorderTestCalls extends Command
{
    protected $signature = 'recorder:seed-test-calls
        {--domain=devel.talk.cloudplay.cloud : Domain name or UUID}
        {--source= : Directory containing m4a/mp3 files (default: storage/app/recorder-test-seed/incoming)}
        {--caller=1010 : Caller extension}
        {--destination=1008 : Dialed extension}
        {--combine : Combine all source files into one recorder conversation}
        {--transcribe : Queue transcription jobs after seeding}
        {--dry-run : Preview actions without writing files or database rows}';

    protected $description = 'Convert test audio files to recorder mp3s and create fake recorder CDR rows';

    public function handle(SrsRecorderDialplanService $recorderDialplanService): int
    {
        $domain = $this->resolveDomain((string) $this->option('domain'));
        if (! $domain) {
            $this->error('Domain not found.');

            return self::FAILURE;
        }

        $sourceDir = $this->resolveSourceDir();
        if (! is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");

            return self::FAILURE;
        }

        $files = $this->sortedSourceFiles($sourceDir);

        if ($files->isEmpty()) {
            $this->error("No .m4a or .mp3 files found in {$sourceDir}");
            $this->line('Upload your iPhone recordings there, then rerun this command.');

            return self::FAILURE;
        }

        if (! $this->binaryExists('ffmpeg') || ! $this->binaryExists('ffprobe')) {
            $this->error('ffmpeg and ffprobe are required.');

            return self::FAILURE;
        }

        if ($this->option('combine')) {
            return $this->seedCombinedConversation($domain, $files, $recorderDialplanService);
        }

        return $this->seedSeparateCalls($domain, $files, $recorderDialplanService);
    }

    /**
     * @return array{xml_cdr_uuid: string, record_file: string, duration: int, start_local: string}
     */
    public function seedFromMp3(
        Domain $domain,
        string $mp3File,
        string $caller,
        string $destination,
        ?Carbon $startLocal = null,
        bool $transcribe = false,
        array $transcriptionOverrides = [],
    ): array {
        if (! is_file($mp3File)) {
            throw new \InvalidArgumentException("Recording file not found: {$mp3File}");
        }

        $timezone = get_local_time_zone($domain->domain_uuid);
        $startLocal ??= Carbon::now($timezone);
        $recorderDialplanService = app(SrsRecorderDialplanService::class);

        $sessionUuid = (string) Str::uuid();
        $xmlCdrUuid = (string) Str::uuid();
        $recordName = sprintf('%s-%s-%s.mp3', $caller, $sessionUuid, $sessionUuid);
        $recordPath = sprintf(
            '/var/lib/freeswitch/recordings/%s/archive/%s/%s/%s',
            $domain->domain_name,
            $startLocal->format('Y'),
            $startLocal->format('M'),
            $startLocal->format('d')
        );
        $recordFile = $recordPath . '/' . $recordName;

        if (! is_dir($recordPath) && ! mkdir($recordPath, 0755, true) && ! is_dir($recordPath)) {
            throw new \RuntimeException("Unable to create directory: {$recordPath}");
        }

        if (! copy($mp3File, $recordFile)) {
            throw new \RuntimeException("Unable to copy recording to {$recordFile}");
        }

        $this->fixRecordingOwnership($recordPath, $recordFile);

        $duration = max(1, (int) round($this->probeDurationSeconds($recordFile)));

        $this->insertRecorderCdr(
            $domain,
            $recorderDialplanService,
            $xmlCdrUuid,
            $caller,
            $destination,
            $startLocal,
            $duration,
            $recordPath,
            $recordName,
            0,
            $sessionUuid
        );

        if ($transcribe) {
            TranscribeCdrJob::dispatch($xmlCdrUuid, $domain->domain_uuid, $transcriptionOverrides)
                ->onQueue('transcriptions');
        }

        return [
            'xml_cdr_uuid' => $xmlCdrUuid,
            'record_file' => $recordFile,
            'duration' => $duration,
            'start_local' => $startLocal->toDateTimeString(),
        ];
    }

    protected function seedSeparateCalls(Domain $domain, $files, SrsRecorderDialplanService $recorderDialplanService): int
    {
        $timezone = get_local_time_zone($domain->domain_uuid);
        $caller = (string) $this->option('caller');
        $destination = (string) $this->option('destination');
        $destinationNumber = $recorderDialplanService->recorderDestinationNumber($domain->domain_name);
        $dryRun = (bool) $this->option('dry-run');
        $created = [];

        $this->info(sprintf(
            'Seeding %d recorder test call(s) for %s (%s)',
            $files->count(),
            $domain->domain_description ?: $domain->domain_name,
            $domain->domain_uuid
        ));

        foreach ($files as $index => $sourcePath) {
            $basename = pathinfo($sourcePath, PATHINFO_FILENAME);
            $startLocal = $this->parseTimestampFromFilename($basename, $timezone)
                ?? Carbon::now($timezone)->subMinutes($files->count() - $index);
            $startLocal = $startLocal->copy()->addSeconds($index * 5);

            $sessionUuid = (string) Str::uuid();
            $xmlCdrUuid = (string) Str::uuid();
            $recordName = sprintf('%s-%s-%s.mp3', $caller, $sessionUuid, $sessionUuid);
            $recordPath = sprintf(
                '/var/lib/freeswitch/recordings/%s/archive/%s/%s/%s',
                $domain->domain_name,
                $startLocal->format('Y'),
                $startLocal->format('M'),
                $startLocal->format('d')
            );
            $recordFile = $recordPath . '/' . $recordName;

            if ($dryRun) {
                $this->line("DRY RUN: {$basename} -> {$recordFile}");
                continue;
            }

            if (! is_dir($recordPath) && ! mkdir($recordPath, 0755, true) && ! is_dir($recordPath)) {
                $this->error("Unable to create directory: {$recordPath}");

                return self::FAILURE;
            }

            if (! $this->convertToMp3($sourcePath, $recordFile)) {
                return self::FAILURE;
            }

            $this->fixRecordingOwnership($recordPath, $recordFile);

            $duration = max(1, (int) round($this->probeDurationSeconds($recordFile)));
            $startUtc = $startLocal->copy()->utc();
            $endUtc = $startUtc->copy()->addSeconds($duration);
            $roomName = sprintf('%s-%s', $caller, $sessionUuid);
            $now = now();

            DB::table('v_xml_cdr')->insert([
                'xml_cdr_uuid' => $xmlCdrUuid,
                'domain_uuid' => $domain->domain_uuid,
                'extension_uuid' => null,
                'sip_call_id' => (string) Str::uuid(),
                'domain_name' => $domain->domain_name,
                'accountcode' => null,
                'direction' => 'recorder',
                'default_language' => null,
                'context' => $domain->domain_name,
                'caller_id_name' => $caller,
                'caller_id_number' => $caller,
                'caller_destination' => $destination,
                'source_number' => null,
                'destination_number' => $destinationNumber,
                'start_epoch' => (string) $startUtc->timestamp,
                'start_stamp' => $startUtc->toDateTimeString(),
                'answer_stamp' => $startUtc->toDateTimeString(),
                'answer_epoch' => (string) $startUtc->timestamp,
                'end_epoch' => (string) $endUtc->timestamp,
                'end_stamp' => $endUtc->toDateTimeString(),
                'duration' => (string) $duration,
                'mduration' => (string) ($duration * 1000),
                'billsec' => (string) $duration,
                'billmsec' => (string) ($duration * 1000),
                'bridge_uuid' => null,
                'read_codec' => 'PCMA',
                'read_rate' => '8000',
                'write_codec' => 'PCMA',
                'write_rate' => '8000',
                'remote_media_ip' => '127.0.0.1',
                'network_addr' => '127.0.0.1',
                'record_path' => $recordPath,
                'record_name' => $recordName,
                'record_length' => (string) $duration,
                'leg' => 'a',
                'originating_leg_uuid' => null,
                'pdd_ms' => '0',
                'rtp_audio_in_mos' => null,
                'last_app' => 'conference',
                'last_arg' => $roomName . '@recorder+flags{nomoh|endconf}',
                'voicemail_message' => 'false',
                'missed_call' => 'false',
                'waitsec' => '0',
                'conference_name' => $roomName,
                'conference_uuid' => (string) Str::uuid(),
                'conference_member_id' => (string) (400 + $index),
                'digits_dialed' => 'none',
                'pin_number' => null,
                'status' => 'answered',
                'hangup_cause' => 'NORMAL_CLEARING',
                'hangup_cause_q850' => '16',
                'sip_hangup_disposition' => 'recv_bye',
                'insert_date' => $now,
                'insert_user' => null,
                'update_date' => null,
                'update_user' => null,
            ]);

            $created[] = [
                'xml_cdr_uuid' => $xmlCdrUuid,
                'source' => basename($sourcePath),
                'record_file' => $recordFile,
                'start_local' => $startLocal->toDateTimeString(),
                'duration' => $duration,
            ];

            $this->line("Created {$xmlCdrUuid} from " . basename($sourcePath));
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        if ($this->option('transcribe')) {
            foreach ($created as $row) {
                TranscribeCdrJob::dispatch($row['xml_cdr_uuid'], $domain->domain_uuid)
                    ->onQueue('transcriptions');
            }

            $this->info('Queued transcription for ' . count($created) . ' call(s).');
        }

        $this->table(
            ['UUID', 'Source', 'Start (' . $timezone . ')', 'Duration', 'Recording'],
            collect($created)->map(fn (array $row) => [
                $row['xml_cdr_uuid'],
                $row['source'],
                $row['start_local'],
                $row['duration'] . 's',
                $row['record_file'],
            ])->all()
        );

        $this->info('Done. Open /recorder on the Development tenant to review the seeded calls.');

        return self::SUCCESS;
    }

    protected function seedCombinedConversation(Domain $domain, $files, SrsRecorderDialplanService $recorderDialplanService): int
    {
        $timezone = get_local_time_zone($domain->domain_uuid);
        $dryRun = (bool) $this->option('dry-run');
        $firstBasename = pathinfo($files->first(), PATHINFO_FILENAME);
        $startLocal = $this->parseTimestampFromFilename($firstBasename, $timezone)
            ?? Carbon::now($timezone);

        $this->info(sprintf(
            'Seeding 1 combined recorder conversation from %d file(s) for %s',
            $files->count(),
            $domain->domain_description ?: $domain->domain_name
        ));

        if ($dryRun) {
            foreach ($files as $sourcePath) {
                $this->line('DRY RUN combine segment: ' . basename($sourcePath));
            }

            return self::SUCCESS;
        }

        $tempMp3s = [];
        foreach ($files as $sourcePath) {
            $tempMp3 = tempnam(sys_get_temp_dir(), 'recorder-segment-') . '.mp3';
            if (! $this->convertToMp3($sourcePath, $tempMp3)) {
                return self::FAILURE;
            }
            $tempMp3s[] = $tempMp3;
        }

        $caller = (string) $this->option('caller');
        $sessionUuid = (string) Str::uuid();
        $recordName = sprintf('%s-%s-%s.mp3', $caller, $sessionUuid, $sessionUuid);
        $recordPath = sprintf(
            '/var/lib/freeswitch/recordings/%s/archive/%s/%s/%s',
            $domain->domain_name,
            $startLocal->format('Y'),
            $startLocal->format('M'),
            $startLocal->format('d')
        );
        $recordFile = $recordPath . '/' . $recordName;

        if (! is_dir($recordPath) && ! mkdir($recordPath, 0755, true) && ! is_dir($recordPath)) {
            $this->error("Unable to create directory: {$recordPath}");

            return self::FAILURE;
        }

        if (! $this->concatenateMp3Files($tempMp3s, $recordFile)) {
            return self::FAILURE;
        }

        foreach ($tempMp3s as $tempMp3) {
            @unlink($tempMp3);
        }

        $this->fixRecordingOwnership($recordPath, $recordFile);

        $xmlCdrUuid = (string) Str::uuid();
        $duration = max(1, (int) round($this->probeDurationSeconds($recordFile)));
        $this->insertRecorderCdr(
            $domain,
            $recorderDialplanService,
            $xmlCdrUuid,
            $caller,
            (string) $this->option('destination'),
            $startLocal,
            $duration,
            $recordPath,
            $recordName,
            0,
            $sessionUuid
        );

        $this->line("Created combined conversation {$xmlCdrUuid} ({$duration}s)");

        if ($this->option('transcribe')) {
            TranscribeCdrJob::dispatch($xmlCdrUuid, $domain->domain_uuid)->onQueue('transcriptions');
            $this->info('Queued transcription for the combined conversation.');
        }

        $this->info('Done. Open /recorder on the Development tenant to review the seeded call.');

        return self::SUCCESS;
    }

    protected function sortedSourceFiles(string $sourceDir)
    {
        return collect(glob($sourceDir . '/*.{m4a,M4A,mp3,MP3}', GLOB_BRACE))
            ->filter(fn (string $path) => is_file($path))
            ->sortBy(fn (string $path) => $this->sourceFileSortKey($path))
            ->values();
    }

    protected function sourceFileSortKey(string $path): string
    {
        $basename = pathinfo($path, PATHINFO_FILENAME);
        $timestamp = $this->parseTimestampFromFilename($basename, 'UTC');

        return ($timestamp?->format('Y-m-d H:i:s') ?? '9999') . '|' . $basename;
    }

    protected function insertRecorderCdr(
        Domain $domain,
        SrsRecorderDialplanService $recorderDialplanService,
        string $xmlCdrUuid,
        string $caller,
        string $destination,
        Carbon $startLocal,
        int $duration,
        string $recordPath,
        string $recordName,
        int $index,
        ?string $sessionUuid = null,
    ): void {
        $destinationNumber = $recorderDialplanService->recorderDestinationNumber($domain->domain_name);
        $startUtc = $startLocal->copy()->utc();
        $endUtc = $startUtc->copy()->addSeconds($duration);
        $sessionUuid ??= Str::before(Str::after($recordName, $caller . '-'), '.mp3');
        $roomName = sprintf('%s-%s', $caller, Str::before($sessionUuid, '-'));

        DB::table('v_xml_cdr')->insert([
            'xml_cdr_uuid' => $xmlCdrUuid,
            'domain_uuid' => $domain->domain_uuid,
            'extension_uuid' => null,
            'sip_call_id' => (string) Str::uuid(),
            'domain_name' => $domain->domain_name,
            'accountcode' => null,
            'direction' => 'recorder',
            'default_language' => null,
            'context' => $domain->domain_name,
            'caller_id_name' => $caller,
            'caller_id_number' => $caller,
            'caller_destination' => $destination,
            'source_number' => null,
            'destination_number' => $destinationNumber,
            'start_epoch' => (string) $startUtc->timestamp,
            'start_stamp' => $startUtc->toDateTimeString(),
            'answer_stamp' => $startUtc->toDateTimeString(),
            'answer_epoch' => (string) $startUtc->timestamp,
            'end_epoch' => (string) $endUtc->timestamp,
            'end_stamp' => $endUtc->toDateTimeString(),
            'duration' => (string) $duration,
            'mduration' => (string) ($duration * 1000),
            'billsec' => (string) $duration,
            'billmsec' => (string) ($duration * 1000),
            'bridge_uuid' => null,
            'read_codec' => 'PCMA',
            'read_rate' => '8000',
            'write_codec' => 'PCMA',
            'write_rate' => '8000',
            'remote_media_ip' => '127.0.0.1',
            'network_addr' => '127.0.0.1',
            'record_path' => $recordPath,
            'record_name' => $recordName,
            'record_length' => (string) $duration,
            'leg' => 'a',
            'originating_leg_uuid' => null,
            'pdd_ms' => '0',
            'rtp_audio_in_mos' => null,
            'last_app' => 'conference',
            'last_arg' => $roomName . '@recorder+flags{nomoh|endconf}',
            'voicemail_message' => 'false',
            'missed_call' => 'false',
            'waitsec' => '0',
            'conference_name' => $roomName,
            'conference_uuid' => (string) Str::uuid(),
            'conference_member_id' => (string) (400 + $index),
            'digits_dialed' => 'none',
            'pin_number' => null,
            'status' => 'answered',
            'hangup_cause' => 'NORMAL_CLEARING',
            'hangup_cause_q850' => '16',
            'sip_hangup_disposition' => 'recv_bye',
            'insert_date' => now(),
            'insert_user' => null,
            'update_date' => null,
            'update_user' => null,
        ]);
    }

    protected function concatenateMp3Files(array $inputFiles, string $outputFile): bool
    {
        $listFile = tempnam(sys_get_temp_dir(), 'recorder-concat-');
        $lines = array_map(
            fn (string $path) => "file '" . str_replace("'", "'\\''", $path) . "'",
            $inputFiles
        );
        file_put_contents($listFile, implode(PHP_EOL, $lines) . PHP_EOL);

        $process = new Process([
            'ffmpeg',
            '-y',
            '-f',
            'concat',
            '-safe',
            '0',
            '-i',
            $listFile,
            '-c',
            'copy',
            $outputFile,
        ]);

        $process->setTimeout(300);
        $process->run();
        @unlink($listFile);

        if (! $process->isSuccessful() || ! is_file($outputFile)) {
            $this->error('ffmpeg concat failed: ' . trim($process->getErrorOutput()));

            return false;
        }

        return true;
    }

    protected function resolveDomain(string $value): ?Domain
    {
        if (Str::isUuid($value)) {
            return Domain::query()->where('domain_uuid', $value)->first();
        }

        return Domain::query()
            ->where('domain_name', $value)
            ->orWhere('domain_description', $value)
            ->first();
    }

    protected function resolveSourceDir(): string
    {
        $source = trim((string) ($this->option('source') ?: ''));
        if ($source === '') {
            $source = storage_path('app/recorder-test-seed/incoming');
        }

        return str_starts_with($source, '/')
            ? $source
            : base_path($source);
    }

    protected function parseTimestampFromFilename(string $basename, string $timezone): ?Carbon
    {
        if (! preg_match(
            '/(\d{4})-(\d{2})-(\d{2})-(\d{2})-(\d{2})-(\d{2})/',
            $basename,
            $matches
        )) {
            return null;
        }

        return Carbon::create(
            (int) $matches[1],
            (int) $matches[2],
            (int) $matches[3],
            (int) $matches[4],
            (int) $matches[5],
            (int) $matches[6],
            $timezone
        );
    }

    protected function convertToMp3(string $sourcePath, string $targetPath): bool
    {
        if (str_ends_with(strtolower($sourcePath), '.mp3')) {
            if ($sourcePath === $targetPath) {
                return true;
            }

            return copy($sourcePath, $targetPath);
        }

        $process = new Process([
            'ffmpeg',
            '-y',
            '-i',
            $sourcePath,
            '-ac',
            '1',
            '-ar',
            '8000',
            '-codec:a',
            'libmp3lame',
            '-qscale:a',
            '4',
            $targetPath,
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('ffmpeg failed for ' . basename($sourcePath) . ': ' . trim($process->getErrorOutput()));

            return false;
        }

        return is_file($targetPath);
    }

    protected function probeDurationSeconds(string $filePath): float
    {
        $process = new Process([
            'ffprobe',
            '-v',
            'error',
            '-show_entries',
            'format=duration',
            '-of',
            'default=noprint_wrappers=1:nokey=1',
            $filePath,
        ]);

        $process->run();

        return (float) trim($process->getOutput());
    }

    protected function binaryExists(string $binary): bool
    {
        $process = new Process(['which', $binary]);
        $process->run();

        return $process->isSuccessful();
    }

    protected function fixRecordingOwnership(string $recordPath, string $recordFile): void
    {
        if (function_exists('posix_getpwnam')) {
            $account = posix_getpwnam('www-data');
            if (is_array($account)) {
                @chown($recordPath, $account['uid']);
                @chgrp($recordPath, $account['gid']);
                @chown($recordFile, $account['uid']);
                @chgrp($recordFile, $account['gid']);
            }
        }

        @chmod($recordPath, 0750);
        @chmod($recordFile, 0644);
    }
}
