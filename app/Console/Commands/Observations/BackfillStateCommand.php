<?php

namespace App\Console\Commands\Observations;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('observations:backfill-state {--chunk=500000 : Rows per update batch}')]
#[Description('Backfill sample_id and observed_at on bacteria observations')]
class BackfillStateCommand extends Command
{
    public function handle(): int
    {
        $chunk = max(1000, (int) $this->option('chunk'));

        $missing = DB::table('bacteria_observations')
            ->whereNull('sample_id')
            ->orWhereNull('observed_at')
            ->count();

        if ($missing === 0) {
            $this->components->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'Backfilling %s bacteria observations in %s-row batches.',
            number_format($missing),
            number_format($chunk),
        ));

        $maxId = (int) DB::table('bacteria_observations')->max('id');
        $batches = (int) ceil($maxId / $chunk);
        $progress = $this->output->createProgressBar($batches);
        $updated = 0;

        for ($start = 1; $start <= $maxId; $start += $chunk) {
            $updated += DB::update(
                'UPDATE bacteria_observations AS bo
                 SET sample_id = o.sample_id, observed_at = o.observed_at
                 FROM observations AS o
                 WHERE o.id = bo.observation_id
                   AND bo.id >= ? AND bo.id < ?
                   AND (bo.sample_id IS NULL OR bo.observed_at IS NULL)',
                [$start, $start + $chunk],
            );

            $progress->advance();
        }

        $progress->finish();
        $this->newLine();

        DB::statement('SET max_parallel_maintenance_workers = 0');
        DB::statement('VACUUM (ANALYZE, PARALLEL 0) bacteria_observations');

        $this->components->info(sprintf('Backfilled %s rows.', number_format($updated)));

        return self::SUCCESS;
    }
}
