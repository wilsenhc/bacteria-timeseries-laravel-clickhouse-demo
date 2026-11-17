<?php

namespace Database\Seeders;

use App\Models\Bacteria;
use App\Models\Lab;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoStressSeeder extends Seeder
{
    use WithoutModelEvents;

    private const RANDOM_SEED = 20261112;

    /**
     * Multiplier for readings per sample per week. The normal demo seed uses 1;
     * `demo:seed --scale=10` produces the 100M-row stress dataset.
     */
    public static int $observationScale = 1;

    /**
     * One small, one medium, and one large lab per organization: 10,800 sample
     * places in total, five times the previous 2,160-place dataset. Every
     * dimension stays within the 1–20 limit, so each lab is also valid through
     * the normal create form.
     *
     * @var array<int, array{letter: string, rows: int, shelves: int, levels: int}>
     */
    private const LAB_PROFILES = [
        ['letter' => 'A', 'rows' => 10, 'shelves' => 10, 'levels' => 12], // 1,200 sample places (5x 240)
        ['letter' => 'B', 'rows' => 20, 'shelves' => 10, 'levels' => 16], // 3,200 sample places (5x 640)
        ['letter' => 'C', 'rows' => 20, 'shelves' => 20, 'levels' => 16], // 6,400 sample places (5x 1,280)
    ];

    /**
     * Seed a deterministic, high-volume demo dataset.
     */
    public function run(): void
    {
        mt_srand(self::RANDOM_SEED);
        fake()->seed(self::RANDOM_SEED);

        $this->truncateDemoData();
        $this->call(BacteriaSeeder::class);

        $users = $this->ensureUsers();
        $levelBacteria = Bacteria::query()->where('observation_type', 'level')->get();
        $countBacteria = Bacteria::query()->where('observation_type', 'count')->get();
        $generator = new ObservationGenerator($levelBacteria, $countBacteria, self::$observationScale);

        $organizations = [
            [
                'name' => 'Instituto de Análises Clínicas',
                'address' => 'Av. Paulista, 1000 — São Paulo, SP',
                'timezone' => 'America/Sao_Paulo',
            ],
            [
                'name' => 'Laboratório Central',
                'address' => 'Rua Augusta, 500 — Lisboa',
                'timezone' => 'Europe/Lisbon',
            ],
            [
                'name' => 'Centro de Microbiologia',
                'address' => '350 Fifth Avenue — New York, NY',
                'timezone' => 'America/New_York',
            ],
        ];

        $output = $this->command?->getOutput();
        $output?->progressStart(count($organizations) * count(self::LAB_PROFILES));

        foreach ($organizations as $organizationData) {
            $organization = $this->createOrganization($organizationData, $users);

            foreach (self::LAB_PROFILES as $profile) {
                // The hierarchy commits once per lab; observations commit in 1,000-row chunks.
                $this->createLab($organization, $profile, $generator);
                $output?->progressAdvance();
            }
        }

        $generator->flush();
        $this->resetObservationSequences();

        $this->call(FilterSeeder::class);

        $output?->progressFinish();

        $this->command?->info(sprintf(
            'Seeded %d organizations, %d labs, %d samples, %d observations, %d bacteria observations.',
            Organization::query()->count(),
            Lab::query()->count(),
            DB::table('samples')->count(),
            DB::table('observations')->count(),
            DB::table('bacteria_observations')->count(),
        ));
    }

    private function truncateDemoData(): void
    {
        DB::statement('TRUNCATE TABLE bacteria_observations, observations, samples, lab_levels, lab_shelves, lab_rows, labs, organization_user, organizations RESTART IDENTITY CASCADE');
    }

    /**
     * @return array{User, User, User}
     */
    private function ensureUsers(): array
    {
        $users = User::query()->orderBy('id')->limit(3)->get();

        $demoUsers = [
            ['name' => 'Demo Admin', 'email' => 'demo.admin@example.com'],
            ['name' => 'Demo Member', 'email' => 'demo.member@example.com'],
        ];

        foreach ($demoUsers as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => 'password'],
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->saveQuietly();
            }

            if (! $users->contains('id', $user->id)) {
                $users->push($user);
            }
        }

        if ($users->count() < 3) {
            $users->push(User::factory()->create([
                'name' => 'Demo Owner',
                'email' => 'demo.owner@example.com',
            ]));
        }

        $users = $users->take(3)->values();

        return [$users[0], $users[1], $users[2]];
    }

    /**
     * @param  array{name: string, address: string, timezone: string}  $data
     * @param  array{User, User, User}  $users
     */
    private function createOrganization(array $data, array $users): Organization
    {
        $createdAt = CarbonImmutable::now($data['timezone'])->subWeeks(27);

        $organization = Organization::create([
            'name' => $data['name'],
            'address' => $data['address'],
            'timezone' => $data['timezone'],
        ]);

        $organization->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        $organization->users()->attach($users[0]->id, ['role' => 'owner']);
        $organization->users()->attach($users[1]->id, ['role' => 'admin']);
        $organization->users()->attach($users[2]->id, ['role' => 'member']);

        return $organization;
    }

    /**
     * @param  array{letter: string, rows: int, shelves: int, levels: int}  $profile
     */
    private function createLab(Organization $organization, array $profile, ObservationGenerator $generator): void
    {
        $timezone = $organization->timezone;
        $labCreatedAt = CarbonImmutable::now($timezone)->subWeeks(26)->addDays(mt_rand(0, 42));

        $labLetter = $profile['letter'];

        $lab = Lab::create([
            'organization_id' => $organization->id,
            'name' => 'Lab '.$labLetter,
            'code' => 'LAB-'.$labLetter,
            'notes' => 'Demo stress lab.',
            'row_count' => $profile['rows'],
            'shelves_per_row' => $profile['shelves'],
            'levels_per_shelf' => $profile['levels'],
        ]);

        $lab->forceFill([
            'created_at' => $labCreatedAt,
            'updated_at' => $labCreatedAt,
        ])->saveQuietly();

        $sampleIds = DB::transaction(fn (): array => $this->createHierarchyAndSamples($lab));
        $weeks = $this->observationWeeks($lab, $timezone);

        foreach ($sampleIds as $sampleId) {
            $generator->generateForSample($sampleId, $weeks, $timezone);
        }

        $generator->flush();
    }

    /**
     * @return array<int, int>
     */
    private function createHierarchyAndSamples(Lab $lab): array
    {
        $now = now()->toDateTimeString();
        $sampleIds = [];

        for ($row = 1; $row <= $lab->row_count; $row++) {
            $rowId = DB::table('lab_rows')->insertGetId([
                'lab_id' => $lab->id,
                'number' => $row,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            for ($shelf = 1; $shelf <= $lab->shelves_per_row; $shelf++) {
                $shelfId = DB::table('lab_shelves')->insertGetId([
                    'lab_row_id' => $rowId,
                    'number' => $shelf,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                for ($level = 1; $level <= $lab->levels_per_shelf; $level++) {
                    $levelId = DB::table('lab_levels')->insertGetId([
                        'lab_shelf_id' => $shelfId,
                        'number' => $level,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    for ($sample = 1; $sample <= 3; $sample++) {
                        $sampleIds[] = DB::table('samples')->insertGetId([
                            'lab_id' => $lab->id,
                            'lab_level_id' => $levelId,
                            'code' => sprintf('R%d-S%d-L%d-%02d', $row, $shelf, $level, $sample),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }

        return $sampleIds;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function observationWeeks(Lab $lab, string $timezone): array
    {
        $lastWeekStart = CarbonImmutable::now($timezone)
            ->startOfWeek(CarbonInterface::MONDAY)
            ->subWeek();

        $windowStart = $lastWeekStart->subWeeks(25);
        $creationWeek = $lab->created_at
            ->toImmutable()
            ->setTimezone($timezone)
            ->startOfWeek(CarbonInterface::MONDAY);

        $firstWeekStart = ($creationWeek->greaterThan($windowStart) ? $creationWeek : $windowStart)
            ->addWeeks(mt_rand(0, 4));

        $weeks = [];

        for ($week = $firstWeekStart; $week->lessThanOrEqualTo($lastWeekStart); $week = $week->addWeek()) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    private function resetObservationSequences(): void
    {
        DB::statement("SELECT setval('observations_id_seq', COALESCE((SELECT MAX(id) FROM observations), 1))");
        DB::statement("SELECT setval('bacteria_observations_id_seq', COALESCE((SELECT MAX(id) FROM bacteria_observations), 1))");
    }
}
