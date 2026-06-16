<?php

namespace App\Console\Commands;

use App\Console\Commands\Updates\Update097;
use App\Console\Commands\Updates\Update101;
use App\Console\Commands\Updates\Update102;
use App\Console\Commands\Updates\Update110;
use App\Console\Commands\Updates\Update111;
use App\Console\Commands\Updates\Update112;
use App\Console\Commands\Updates\Update113;
use App\Console\Commands\Updates\Update114;
use App\Console\Commands\Updates\Update120;
use App\Console\Commands\Updates\Update121;
use App\Console\Commands\Updates\Update131;
use App\Console\Commands\Updates\Update140;
use App\Console\Commands\Updates\Update145;
use App\Console\Commands\Updates\Update150;
use App\Console\Commands\Updates\Update160;
use App\Console\Commands\Updates\Update161;
use App\Console\Commands\Updates\Update162;
use App\Console\Commands\Updates\Update163;
use App\Console\Commands\Updates\Update164;
use App\Console\Commands\Updates\Update165;
use App\Console\Commands\Updates\Update166;
use App\Console\Commands\Updates\Update167;
use App\Console\Commands\Updates\Update168;
use App\Console\Commands\Updates\Update169;
use App\Console\Commands\Updates\Update170;
use App\Console\Commands\Updates\Update171;
use App\Console\Commands\Updates\Update172;
use App\Console\Commands\Updates\Update174;
use App\Console\Commands\Updates\Update175;
use App\Console\Commands\Updates\Update176;
use App\Console\Commands\Updates\Update177;
use App\Console\Commands\Updates\Update178;
use App\Console\Commands\Updates\Update179;
use App\Console\Commands\Updates\Update180;
use App\Console\Commands\Updates\Update181;
use App\Console\Commands\Updates\Update182;
use App\Console\Commands\Updates\Update183;
use App\Console\Commands\Updates\Update184;
use App\Console\Commands\Updates\Update185;
use App\Console\Commands\Updates\Update186;
use App\Console\Commands\Updates\Update187;
use App\Console\Commands\Updates\Update188;
use App\Console\Commands\Updates\Update189;
use App\Console\Commands\Updates\Update190;
use App\Console\Commands\Updates\Update191;
use App\Console\Commands\Updates\Update192;
use App\Console\Commands\Updates\Update193;
use App\Console\Commands\Updates\Update194;
use App\Console\Commands\Updates\Update195;
use App\Console\Commands\Updates\Update196;
use App\Console\Commands\Updates\Update197;
use App\Console\Commands\Updates\Update198;
use App\Console\Commands\Updates\Update200;
use App\Console\Commands\Updates\Update201;
use App\Console\Commands\Updates\Update202;
use App\Console\Commands\Updates\Update203;
use App\Console\Commands\Updates\Update204;
use App\Console\Commands\Updates\Update205;
use App\Console\Commands\Updates\Update206;
use App\Console\Commands\Updates\Update207;
use App\Console\Commands\Updates\Update208;
use App\Console\Commands\Updates\Update209;
use App\Console\Commands\Updates\Update210;
use App\Console\Commands\Updates\Update211;
use App\Console\Commands\Updates\Update212;
use App\Console\Commands\Updates\Update213;
use App\Console\Commands\Updates\Update214;
use App\Console\Commands\Updates\Update215;
use App\Console\Commands\Updates\Update216;
use App\Console\Commands\Updates\Update217;
use App\Console\Commands\Updates\Update218;
use App\Console\Commands\Updates\Update219;
use App\Console\Commands\Updates\Update220;
use App\Console\Commands\Updates\Update221;
use App\Console\Commands\Updates\Update222;
use App\Console\Commands\Updates\Update223;
use App\Console\Commands\Updates\Update224;
use App\Console\Commands\Updates\Update225;
use App\Console\Commands\Updates\Update226;
use App\Console\Commands\Updates\Update227;
use App\Console\Commands\Updates\Update228;
use App\Console\Commands\Updates\Update229;
use App\Console\Commands\Updates\Update230;
use App\Console\Commands\Updates\Update231;
use App\Console\Commands\Updates\Update232;
use App\Console\Commands\Updates\Update233;
use App\Console\Commands\Updates\Update234;
use App\Console\Commands\Updates\Update235;
use App\Console\Commands\Updates\Update236;
use App\Console\Commands\Updates\Update237;
use App\Console\Commands\Updates\Update238;
use App\Console\Commands\Updates\Update0917;
use App\Console\Commands\Updates\Update0918;
use App\Console\Commands\Updates\Update0924;
use App\Console\Commands\Updates\Update0925;
use App\Console\Commands\Updates\Update0926;
use App\Console\Commands\Updates\Update0940;
use App\Console\Commands\Updates\Update0941;
use App\Console\Commands\Updates\Update0942;
use App\Console\Commands\Updates\Update0951;
use App\Console\Commands\Updates\Update0955;
use App\Console\Commands\Updates\Update0961;
use App\Console\Commands\Updates\Update0965;
use App\Console\Commands\Updates\Update0966;
use App\Console\Commands\Updates\Update0967;
use App\Console\Commands\Updates\Update0969;
use App\Console\Commands\Updates\Update0970;
use App\Services\GitHubApiService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class UpdateApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply recent updates';

    protected $githubApiService;


    public function __construct(GitHubApiService $githubApiService)
    {
        parent::__construct();
        $this->githubApiService = $githubApiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting update...');

        $currentVersion = config('app.version');

        // Always get latest version directly from the file (not the config cache)
        $versionConfigFile = base_path('config/version.php');
        $downloadedVersionArray = include($versionConfigFile);
        $downloadedVersion = $downloadedVersionArray['release'] ?? null;

        // Define version-specific steps using an array
        $updateSteps = [
            '0.9.7' => Update097::class,
            '0.9.11' => Update097::class,
            '0.9.17' => Update0917::class,
            '0.9.18' => Update0918::class,
            '0.9.24' => Update0924::class,
            '0.9.25' => Update0925::class,
            '0.9.26' => Update0926::class,
            '0.9.40' => Update0940::class,
            '0.9.41' => Update0941::class,
            '0.9.42' => Update0942::class,
            '0.9.51' => Update0951::class,
            '0.9.55' => Update0955::class,
            '0.9.61' => Update0961::class,
            '0.9.65' => Update0965::class,
            '0.9.66' => Update0966::class,
            '0.9.67' => Update0967::class,
            '0.9.69' => Update0969::class,
            '0.9.70' => Update0970::class,
            '1.0.1' => Update101::class,
            '1.0.2' => Update102::class,
            '1.1.0' => Update110::class,
            '1.1.1' => Update111::class,
            '1.1.2' => Update112::class,
            '1.1.3' => Update113::class,
            '1.1.4' => Update114::class,
            '1.2.0' => Update120::class,
            '1.2.1' => Update121::class,
            '1.3.1' => Update131::class,
            '1.4.0' => Update140::class,
            '1.4.5' => Update145::class,
            '1.5.0' => Update150::class,
            '1.6.0' => Update160::class,
            '1.6.1' => Update161::class,
            '1.6.2' => Update162::class,
            '1.6.3' => Update163::class,
            '1.6.4' => Update164::class,
            '1.6.5' => Update165::class,
            '1.6.6' => Update166::class,
            '1.6.7' => Update167::class,
            '1.6.8' => Update168::class,
            '1.6.9' => Update169::class,
            '1.7.0' => Update170::class,
            '1.7.1' => Update171::class,
            '1.7.2' => Update172::class,
            '1.7.4' => Update174::class,
            '1.7.5' => Update175::class,
            '1.7.6' => Update176::class,
            '1.7.7' => Update177::class,
            '1.7.8' => Update178::class,
            '1.7.9' => Update179::class,
            '1.8.0' => Update180::class,
            '1.8.1' => Update181::class,
            '1.8.2' => Update182::class,
            '1.8.3' => Update183::class,
            '1.8.4' => Update184::class,
            '1.8.5' => Update185::class,
            '1.8.6' => Update186::class,
            '1.8.7' => Update187::class,
            '1.8.7.1' => Update188::class,
            '1.8.7.2' => Update189::class,
            '1.8.7.3' => Update190::class,
            '1.8.7.4' => Update191::class,
            '1.8.8' => Update192::class,
            '1.8.8.1' => Update193::class,
            '1.8.8.2' => Update194::class,
            '1.8.8.3' => Update195::class,
            '1.8.8.4' => Update196::class,
            '1.8.8.5' => Update197::class,
            '1.8.8.6' => Update198::class,
            '1.8.8.7' => Update200::class,
            '1.8.8.8' => Update201::class,
            '1.8.8.9' => Update202::class,
            '1.8.8.10' => Update203::class,
            '1.8.8.11' => Update204::class,
            '1.8.8.12' => Update205::class,
            '1.8.8.13' => Update206::class,
            '1.8.8.14' => Update207::class,
            '1.8.8.15' => Update208::class,
            '1.8.8.16' => Update209::class,
            '1.8.8.17' => Update210::class,
            '1.8.8.18' => Update211::class,
            '1.8.8.19' => Update212::class,
            '1.8.8.20' => Update213::class,
            '1.8.8.21' => Update214::class,
            '1.8.8.22' => Update215::class,
            '1.8.8.23' => Update216::class,
            '1.8.8.24' => Update217::class,
            '1.8.8.25' => Update218::class,
            '1.8.8.26' => Update219::class,
            '1.8.8.27' => Update220::class,
            '1.8.8.28' => Update221::class,
            '1.8.8.29' => Update222::class,
            '1.8.8.30' => Update223::class,
            '1.8.8.31' => Update224::class,
            '1.8.8.32' => Update225::class,
            '1.8.8.33' => Update226::class,
            '1.8.8.34' => Update227::class,
            '1.8.8.35' => Update228::class,
            '1.8.8.36' => Update229::class,
            '1.8.8.37' => Update230::class,
            '1.8.8.38' => Update231::class,
            '1.8.8.39' => Update232::class,
            '1.8.8.40' => Update233::class,
            '1.8.8.41' => Update234::class,
            '1.8.8.42' => Update235::class,
            '1.8.8.43' => Update236::class,
            '1.8.8.44' => Update237::class,
            '1.8.8.45' => Update238::class,
            // Add more versions as needed
        ];

        $supervisorProgramsToRestart = [];

        foreach ($updateSteps as $version => $updateClass) {
            if (version_compare($currentVersion, $version, '<')) {
                $this->info("Applying update steps for version $version...");
                // Create instance of the class and call the apply() method
                $updateInstance = new $updateClass();
                if (!$updateInstance->apply()) {
                    // If the update fails, stop further updates and exit with failure
                    $this->error("Update to version $version failed. Stopping further updates.");
                    exit(1);
                }

                if (method_exists($updateInstance, 'getSupervisorProgramsToRestart')) {
                    $supervisorProgramsToRestart = array_unique(array_merge(
                        $supervisorProgramsToRestart,
                        $updateInstance->getSupervisorProgramsToRestart()
                    ));
                }

                // If the update is successful, call the version:set command
                $this->call('version:set', ['version' => $version, '--force' => true]);
                $this->info("Version successfully updated to $version.");
            }
        }

        if (version_compare($currentVersion, $downloadedVersion, '<')) {
            // Call version:set to update the version to the latest one, even if no steps were needed
            $this->call('version:set', ['version' => $downloadedVersion, '--force' => true]);
            $this->info("Version successfully updated to $downloadedVersion.");
        }

        // Composer install
        $this->executeCommand('composer install --no-interaction --ignore-platform-reqs');
        $this->executeCommand('composer dump-autoload --no-interaction --ignore-platform-reqs');

        // Cache config and routes
        $this->executeCommand('php artisan config:cache');

        // Refresh enabled modules using the DB license
        $this->runArtisanCommand('modules:refresh');

        $this->executeCommand('php artisan route:cache');
        $this->executeCommand('php artisan queue:restart');
        $this->runArtisanCommand('horizon:terminate');

        //Seed the db
        $this->executeCommand('php artisan db:seed --force');

        $this->info("Seeding Templates...");
        // Run prov:templates:seed in a subprocess too
        $this->executeCommand('php artisan prov:templates:seed --no-interaction', 300);

        // Create storage link
        $this->runArtisanCommand('storage:link', ['--force' => true]);

        // Update Vue files
        $this->executeCommand('npm install');
        $this->executeCommand('npm run build', 300);

        // Output the current working directory
        $currentDirectory = $this->getCurrentDirectory();
        $this->info('Current working directory: ' . $currentDirectory);

        // Change ownership of the current directory
        $this->changeDirectoryOwnership($currentDirectory);

        if (!empty($supervisorProgramsToRestart)) {
            $this->restartSupervisorPrograms($supervisorProgramsToRestart);
        }

        $this->info('Update completed successfully!');
    }


    protected function executeCommand($command, $timeout = 60, $failOnError = true)
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeout);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->output->write($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $this->error("Command '$command' failed.");

            if ($failOnError) {
                exit(1);
            }
        }
    }

    /**
     * Run artisan command with optional array of options.
     */
    protected function runArtisanCommand($command, array $options = [])
    {
        $exitCode = $this->call($command, $options);
        if ($exitCode !== 0) {
            $this->error("Artisan command '$command' failed.");
            exit(1);
        }
    }

    /**
     * Retrieve the current working directory
     */
    protected function getCurrentDirectory()
    {
        return getcwd();
    }

    /**
     * Change ownership of the specified directory to www-data:www-data.
     */
    protected function changeDirectoryOwnership($directory)
    {
        $this->info("Changing ownership of directory: $directory");

        // Execute the chown command
        $this->executeCommand("chown -R www-data:www-data $directory");
    }

    protected function restartSupervisorPrograms(array $programs)
    {
        $programs = array_values(array_filter(array_unique($programs)));

        if (empty($programs)) {
            return;
        }

        $this->info('Restarting Supervisor programs: ' . implode(', ', $programs));

        $escapedPrograms = implode(' ', array_map('escapeshellarg', $programs));

        $this->executeCommand("supervisorctl restart {$escapedPrograms}", 120, false);
    }
}
