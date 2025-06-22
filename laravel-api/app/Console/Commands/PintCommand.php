<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class PintCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pint {--fix : Fix code style issues} {--test : Test code style without fixing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run Laravel Pint for code style checking and fixing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Running Laravel Pint...');

        $command = ['vendor/bin/pint'];

        if ($this->option('fix')) {
            $this->info('Fixing code style issues...');
        } elseif ($this->option('test')) {
            $command[] = '--test';
            $this->info('Testing code style (no fixes will be applied)...');
        } else {
            $this->info('Checking code style...');
        }

        $process = new Process($command);
        $process->setTimeout(300); // 5分のタイムアウト

        $process->run(function ($type, $buffer) {
            if ($type === Process::ERR) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });

        if ($process->isSuccessful()) {
            $this->info('Pint completed successfully!');

            return Command::SUCCESS;
        } else {
            $this->error('Pint failed with exit code: '.$process->getExitCode());

            return Command::FAILURE;
        }
    }
}
