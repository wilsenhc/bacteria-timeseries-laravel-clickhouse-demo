<?php

namespace App\Providers;

use App\Enums\LabMapEngine;
use App\Services\LabMap\ClickHouseLabMapService;
use App\Services\LabMap\ClickHouseSnapshotLabMapService;
use App\Services\LabMap\LabMapService;
use App\Services\LabMap\PostgresLabMapService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PostgresLabMapService::class);
        $this->app->singleton(ClickHouseLabMapService::class);
        $this->app->singleton(ClickHouseSnapshotLabMapService::class);

        $this->app->bind(LabMapService::class, function (Application $app): LabMapService {
            $request = $app->make('request');

            $engine = $request->hasSession()
                ? (LabMapEngine::tryFrom(
                    (string) $request->session()->get('lab_map_engine', LabMapEngine::Postgres->value)
                ) ?? LabMapEngine::Postgres)
                : LabMapEngine::Postgres;

            return match ($engine) {
                LabMapEngine::Postgres => $app->make(PostgresLabMapService::class),
                LabMapEngine::ClickHouse => $app->make(ClickHouseLabMapService::class),
                LabMapEngine::ClickHouseSnapshot => $app->make(ClickHouseSnapshotLabMapService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
