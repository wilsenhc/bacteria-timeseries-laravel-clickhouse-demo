<?php

namespace App\Console\Commands\Demo;

use Database\Seeders\DemoStressSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:seed {--scale=1 : Observation reading multiplier}')]
#[Description('Seed the demo stress dataset, optionally with scaled observation readings')]
class SeedCommand extends Command
{
    public function handle(): int
    {
        $scale = max(1, (int) $this->option('scale'));

        $this->laravel->instance(DemoStressSeeder::class, new DemoStressSeeder($scale));

        $this->components->info(sprintf('Seeding demo data with observation scale %d.', $scale));
        $this->components->warn('This truncates demo organizations, labs, samples, observations, and filters.');

        $this->call('db:seed', [
            '--class' => DemoStressSeeder::class,
            '--no-interaction' => true,
        ]);

        return self::SUCCESS;
    }
}
