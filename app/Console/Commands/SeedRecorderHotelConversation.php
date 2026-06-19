<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\OpenAIService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class SeedRecorderHotelConversation extends Command
{
    protected $signature = 'recorder:seed-hotel-conversation
        {--domain=devel.talk.cloudplay.cloud : Domain name or UUID}
        {--room=512 : Guest room number shown in the conversation}
        {--caller= : Recorder caller extension (defaults to room number)}
        {--destination=1008 : Dialed extension}
        {--language=en : Conversation language (en, th, zh, or zh-tw)}
        {--scenario=towels : Conversation scenario (towels or dirty-room)}
        {--transcribe : Queue transcription after seeding}
        {--dry-run : Generate audio only, do not write recorder rows}';

    protected $description = 'Generate a synthetic hotel guest/front desk conversation and insert it into Recorder';

    public function handle(OpenAIService $openAIService, SeedRecorderTestCalls $seeder): int
    {
        if (! $openAIService->isConfigured()) {
            $this->error('OpenAI is not configured. TTS is required to generate the conversation audio.');

            return self::FAILURE;
        }

        if (! $this->binaryExists('ffmpeg') || ! $this->binaryExists('ffprobe')) {
            $this->error('ffmpeg and ffprobe are required.');

            return self::FAILURE;
        }

        $domain = $this->resolveDomain((string) $this->option('domain'));
        if (! $domain) {
            $this->error('Domain not found.');

            return self::FAILURE;
        }

        $room = trim((string) $this->option('room'));
        $caller = trim((string) ($this->option('caller') ?: $room));
        $destination = (string) $this->option('destination');
        $dryRun = (bool) $this->option('dry-run');

        $language = $this->normalizeLanguage((string) $this->option('language'));
        $scenario = $this->normalizeScenario((string) $this->option('scenario'));

        $lines = $this->conversationLines($room, $language, $scenario);
        $this->info(sprintf(
            'Generating hotel conversation (%s, %s) for %s (%d lines)',
            strtoupper($language),
            $scenario,
            $domain->domain_description ?: $domain->domain_name,
            count($lines)
        ));

        $segmentFiles = [];
        $tempDir = sys_get_temp_dir() . '/recorder-hotel-' . Str::uuid();

        try {
            if (! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
                throw new \RuntimeException("Unable to create temp directory: {$tempDir}");
            }

            foreach ($lines as $index => $line) {
                $monoWavPath = $tempDir . '/segment-' . $index . '-mono.wav';
                $stereoWavPath = $tempDir . '/segment-' . $index . '.wav';
                $this->line(sprintf('[%s] %s', $line['speaker'], $line['text']));

                $audio = $openAIService->textToSpeech(
                    'gpt-4o-mini-tts-2025-12-15',
                    $line['text'],
                    $line['voice'],
                    'wav',
                    '1.0'
                );

                file_put_contents($monoWavPath, $audio);

                if (! $this->panMonoToStereoChannel($monoWavPath, $stereoWavPath, $line['channel'])) {
                    return self::FAILURE;
                }

                $segmentFiles[] = $stereoWavPath;
                $segmentFiles[] = $this->createSilenceWav($tempDir, 1800);
            }

            array_pop($segmentFiles);

            $combinedWav = $tempDir . '/conversation.wav';
            $outputMp3 = $tempDir . '/conversation.mp3';

            if (! $this->concatenateWavFiles($segmentFiles, $combinedWav)) {
                return self::FAILURE;
            }

            if (! $this->convertWavToMp3($combinedWav, $outputMp3)) {
                return self::FAILURE;
            }

            if ($dryRun) {
                $this->info("DRY RUN: generated {$outputMp3}");

                return self::SUCCESS;
            }

            $result = $seeder->seedFromMp3(
                $domain,
                $outputMp3,
                $caller,
                $destination,
                Carbon::now(get_local_time_zone($domain->domain_uuid)),
                (bool) $this->option('transcribe'),
                $this->transcriptionOverrides(),
            );

            $this->table(
                ['Field', 'Value'],
                [
                    ['UUID', $result['xml_cdr_uuid']],
                    ['Caller', $caller],
                    ['Destination', $destination],
                    ['Start', $result['start_local']],
                    ['Duration', $result['duration'] . 's'],
                    ['Recording', $result['record_file']],
                ]
            );

            $this->info('Done. Open /recorder to review the seeded hotel conversation.');

            return self::SUCCESS;
        } finally {
            if (is_dir($tempDir)) {
                foreach (glob($tempDir . '/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($tempDir);
            }
        }
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function conversationLines(string $room, string $language = 'en', string $scenario = 'towels'): array
    {
        if ($language === 'th') {
            return $scenario === 'dirty-room'
                ? $this->dirtyRoomComplaintLinesThai($room)
                : $this->towelRequestLinesThai($room);
        }

        if ($language === 'zh-tw') {
            return $scenario === 'dirty-room'
                ? $this->dirtyRoomComplaintLinesChineseTraditional($room)
                : $this->towelRequestLinesChineseTraditional($room);
        }

        if ($language === 'zh') {
            return $scenario === 'dirty-room'
                ? $this->dirtyRoomComplaintLinesChinese($room)
                : $this->towelRequestLinesChinese($room);
        }

        if ($scenario === 'dirty-room') {
            return $this->dirtyRoomComplaintLinesEnglish($room);
        }

        return $this->towelRequestLinesEnglish($room);
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function towelRequestLinesThai(string $room): array
    {
        return [
                [
                    'speaker' => 'Guest',
                    'voice' => 'shimmer',
                    'channel' => 'left',
                    'text' => "สวัสดีครับ ห้อง {$room} ครับ ขอผ้าเช็ดตัวเพิ่มได้ไหมครับ",
                ],
                [
                    'speaker' => 'Front Desk',
                    'voice' => 'echo',
                    'channel' => 'right',
                    'text' => 'ได้เลยค่ะ เราจะส่งผ้าเช็ดตัวเพิ่มให้ในอีกสักครู่ค่ะ',
                ],
                [
                    'speaker' => 'Guest',
                    'voice' => 'shimmer',
                    'channel' => 'left',
                    'text' => 'ขอบคุณมากครับ',
                ],
                [
                    'speaker' => 'Front Desk',
                    'voice' => 'echo',
                    'channel' => 'right',
                    'text' => 'ยินดีค่ะ ขอให้พักผ่อนสบายนะคะ',
                ],
            ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function towelRequestLinesChinese(string $room): array
    {
        return [
                [
                    'speaker' => 'Guest',
                    'voice' => 'shimmer',
                    'channel' => 'left',
                    'text' => "你好，我是{$room}号房，可以多送几条毛巾吗？",
                ],
                [
                    'speaker' => 'Front Desk',
                    'voice' => 'echo',
                    'channel' => 'right',
                    'text' => '好的，我们马上给您送毛巾上去。',
                ],
                [
                    'speaker' => 'Guest',
                    'voice' => 'shimmer',
                    'channel' => 'left',
                    'text' => '非常感谢。',
                ],
                [
                    'speaker' => 'Front Desk',
                    'voice' => 'echo',
                    'channel' => 'right',
                    'text' => '不客气，祝您住得愉快。',
                ],
            ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function towelRequestLinesChineseTraditional(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}號房，可以多送幾條毛巾嗎？",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '好的，我們馬上給您送毛巾上去。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '非常感謝。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '不客氣，祝您住得愉快。',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function towelRequestLinesEnglish(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "Hello, this is room {$room}. Could I please have some extra towels sent up?",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'Certainly. We will send extra towels to your room in just a moment.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'Thank you very much.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'You are welcome. Enjoy your stay.',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function dirtyRoomComplaintLinesEnglish(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "Hello, this is room {$room}. I just checked in and my room is very dirty.",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'I am so sorry to hear that. Can you tell me what you found?',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'The bathroom was not cleaned, and there are stains on the carpet.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'I apologize. We will send housekeeping to reclean your room right away.',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'Thank you. I appreciate the quick response.',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'You are welcome. Again, we are sorry for the inconvenience.',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function dirtyRoomComplaintLinesThai(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "สวัสดีครับ ห้อง {$room} ครับ เพิ่งเช็คอินมา ห้องสกปรกมากครับ",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ขอโทษด้วยนะคะ ช่วยบอกได้ไหมคะว่าเจออะไรบ้าง',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'ห้องน้ำยังไม่ได้ทำความสะอาด และพรมมีรอยเปื้อนครับ',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ต้องขอโทษอีกครั้งค่ะ เราจะส่งแม่บ้านเข้าไปทำความสะอาดให้ทันทีค่ะ',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => 'ขอบคุณครับ ขอบคุณที่ช่วยเหลืออย่างรวดเร็วครับ',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => 'ยินดีค่ะ ขอโทษอีกครั้งสำหรับความไม่สะดวกนะคะ',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function dirtyRoomComplaintLinesChinese(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}号房，我刚入住，房间很脏。",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '非常抱歉，请问您具体看到了什么问题？',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '浴室没有打扫，而且地毯上有污渍。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '再次向您道歉，我们会马上派客房服务去重新打扫。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '谢谢，感谢你们的快速处理。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '不客气，再次为给您带来的不便表示歉意。',
            ],
        ];
    }

    /**
     * @return array<int, array{speaker: string, voice: string, channel: string, text: string}>
     */
    protected function dirtyRoomComplaintLinesChineseTraditional(string $room): array
    {
        return [
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => "你好，我是{$room}號房，我剛入住，房間很髒。",
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '非常抱歉，請問您具體看到了什麼問題？',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '浴室沒有打掃，而且地毯上有污漬。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '再次向您道歉，我們會馬上派客房服務去重新打掃。',
            ],
            [
                'speaker' => 'Guest',
                'voice' => 'shimmer',
                'channel' => 'left',
                'text' => '謝謝，感謝你們的快速處理。',
            ],
            [
                'speaker' => 'Front Desk',
                'voice' => 'echo',
                'channel' => 'right',
                'text' => '不客氣，再次為給您帶來的不便表示歉意。',
            ],
        ];
    }

    protected function normalizeScenario(string $scenario): string
    {
        $scenario = strtolower(trim($scenario));

        return in_array($scenario, ['towels', 'dirty-room', 'dirty_room', 'dirtyroom'], true)
            ? str_replace('_', '-', $scenario === 'dirtyroom' ? 'dirty-room' : $scenario)
            : 'towels';
    }

    /**
     * @return array<string, mixed>
     */
    protected function transcriptionOverrides(): array
    {
        $language = $this->normalizeLanguage((string) $this->option('language'));

        $overrides = [
            // Non-English hotel seeds use stereo multichannel for two-speaker output.
            'multichannel' => true,
            'speaker_labels' => false,
            'speakers_expected' => null,
            'speaker_options' => null,
        ];

        if ($language === 'th') {
            $overrides = array_merge($overrides, $this->explicitLanguageOverrides('th'));
        }

        if (in_array($language, ['zh', 'zh-tw'], true)) {
            // AssemblyAI only accepts zh; Traditional script comes from TTS dialogue.
            $overrides = array_merge($overrides, $this->explicitLanguageOverrides('zh'));
        }

        return $overrides;
    }

    /**
     * @return array<string, mixed>
     */
    protected function explicitLanguageOverrides(string $languageCode): array
    {
        return [
            'language_code' => $languageCode,
            'language_detection' => false,
            'language_detection_options' => null,
            'auto_chapters' => false,
            'auto_highlights' => false,
            'sentiment_analysis' => false,
            'entity_detection' => false,
            'iab_categories' => false,
        ];
    }

    protected function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));

        if (in_array($language, ['zh-tw', 'zh-hant', 'traditional'], true)) {
            return 'zh-tw';
        }

        if (in_array($language, ['zh', 'zh-cn', 'mandarin', 'cmn'], true)) {
            return 'zh';
        }

        return in_array($language, ['en', 'th'], true) ? $language : 'en';
    }

    protected function createSilenceWav(string $tempDir, int $milliseconds): string
    {
        $path = $tempDir . '/silence-' . $milliseconds . '-' . Str::uuid() . '.wav';
        $seconds = max(0.1, $milliseconds / 1000);

        $process = new Process([
            'ffmpeg',
            '-y',
            '-f',
            'lavfi',
            '-i',
            'anullsrc=r=16000:cl=stereo',
            '-t',
            (string) $seconds,
            $path,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($path)) {
            throw new \RuntimeException('Failed to generate silence segment: ' . trim($process->getErrorOutput()));
        }

        return $path;
    }

    protected function concatenateWavFiles(array $inputFiles, string $outputFile): bool
    {
        $listFile = tempnam(sys_get_temp_dir(), 'recorder-hotel-concat-');
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
            '-c:a',
            'pcm_s16le',
            '-ar',
            '16000',
            '-ac',
            '2',
            $outputFile,
        ]);
        $process->setTimeout(300);
        $process->run();
        @unlink($listFile);

        if (! $process->isSuccessful() || ! is_file($outputFile)) {
            $this->error('ffmpeg wav concat failed: ' . trim($process->getErrorOutput()));

            return false;
        }

        return true;
    }

    protected function panMonoToStereoChannel(string $inputPath, string $outputPath, string $channel): bool
    {
        $inputs = $channel === 'right'
            ? ['-f', 'lavfi', '-i', 'anullsrc=r=16000:cl=mono', '-i', $inputPath]
            : ['-i', $inputPath, '-f', 'lavfi', '-i', 'anullsrc=r=16000:cl=mono'];

        $filter = $channel === 'right'
            ? '[1:a]aresample=16000,aformat=channel_layouts=mono[speech];[0:a][speech]amerge=inputs=2'
            : '[0:a]aresample=16000,aformat=channel_layouts=mono[speech];[speech][1:a]amerge=inputs=2';

        $process = new Process(array_merge(
            ['ffmpeg', '-y'],
            $inputs,
            ['-filter_complex', $filter, $outputPath]
        ));
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($outputPath)) {
            $this->error('ffmpeg channel pan failed: ' . trim($process->getErrorOutput()));

            return false;
        }

        return true;
    }

    protected function convertWavToMp3(string $sourcePath, string $targetPath): bool
    {
        $process = new Process([
            'ffmpeg',
            '-y',
            '-i',
            $sourcePath,
            '-ac',
            '2',
            '-ar',
            '16000',
            '-codec:a',
            'libmp3lame',
            '-qscale:a',
            '4',
            $targetPath,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($targetPath)) {
            $this->error('ffmpeg mp3 conversion failed: ' . trim($process->getErrorOutput()));

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

    protected function binaryExists(string $binary): bool
    {
        $process = new Process(['which', $binary]);
        $process->run();

        return $process->isSuccessful();
    }
}
