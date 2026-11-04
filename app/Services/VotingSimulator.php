<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fills a database with a realistic election for simulation: many registered voters, a list of fields and
 * candidates, an election schedule, and ballots whose send times follow a voting day's curve. Only the
 * command that calls this may run it, and only against a database set aside for simulation.
 */
class VotingSimulator
{
    /**
     * Share of voters who pick in a given field. The rest leave it blank, as real voters sometimes do.
     */
    public const FIELD_PICK_PERCENT = 95;

    /**
     * Rows written per insert. Large enough to be fast, small enough to keep memory flat.
     */
    private const BATCH_SIZE = 2000;

    /**
     * How many rows between progress lines, so a run of a million rows still shows it is moving.
     */
    private const REPORT_EVERY = 100000;

    /**
     * Ballots sent in the first part of the window, as a share of all ballots: the opening surge.
     */
    private const OPENING_SHARE = 25;

    /**
     * Ballots sent in the last part of the window, as a share of all ballots: the closing rush.
     */
    private const CLOSING_SHARE = 12;

    /**
     * @return array{voters: int, ballots: int, votes: int, election: Election}
     */
    public function run(
        int $voters,
        int $candidates,
        int $fields,
        int $turnoutPercent,
        int $hours,
        int $seed,
        Closure $report,
    ): array {
        mt_srand($seed);

        $faker = FakerFactory::create('id_ID');
        $faker->seed($seed);

        $windowEnd = now()->startOfSecond();
        $windowStart = $windowEnd->copy()->subHours($hours);
        $windowSeconds = (int) $windowStart->diffInSeconds($windowEnd);

        $election = Election::create([
            'name' => 'Simulasi Pemilihan '.number_format($voters, 0, ',', '.').' pemilih',
            'opens_at' => $windowStart,
            'closes_at' => $windowEnd->copy()->addHours(2),
        ]);

        $admin = User::create([
            'name' => 'Admin Simulasi',
            'email' => 'admin@simulasi.test',
            'password' => 'password',
            'is_admin' => true,
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $fieldIds = [];

        foreach (range(1, $fields) as $position) {
            $fieldIds[] = Field::create(['name' => self::fieldName($position), 'position' => $position])->id;
        }

        $candidateIds = [];

        foreach (range(1, $candidates) as $position) {
            $candidateIds[] = Candidate::create([
                'name' => $faker->unique()->name(),
                'position' => $position,
            ])->id;
        }

        $report('Mendaftarkan '.number_format($voters, 0, ',', '.').' pemilih...');

        $this->registerVoters($voters, $faker, $windowStart, $report);

        $report('Mengirim suara sesuai kurva waktu pemungutan...');

        $cumulativeWeights = self::cumulativeWeights(count($candidateIds));
        $random = self::randomFloat(...);
        $ballotCount = 0;
        $voteCount = 0;
        $lastId = '';
        $nextReport = self::REPORT_EVERY;

        do {
            $voterIds = DB::table('users')
                ->where('is_admin', false)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->pluck('id')
                ->all();

            if ($voterIds === []) {
                break;
            }

            $lastId = end($voterIds);

            $ballotRows = [];
            $picksByCode = [];

            foreach ($voterIds as $voterId) {
                if (mt_rand(1, 100) > $turnoutPercent) {
                    continue;
                }

                $code = Ballot::newReceiptCode();
                $sentAt = $windowStart->copy()->addSeconds(self::sentAtOffset($windowSeconds, $random(), $random()));
                $timestamp = $sentAt->utc()->format('Y-m-d H:i:s');

                $ballotRows[] = [
                    'user_id' => $voterId,
                    'receipt_code' => $code,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                $picksByCode[$code] = [
                    'timestamp' => $timestamp,
                    'picks' => self::picks($fieldIds, $candidateIds, $cumulativeWeights, $random),
                ];
            }

            if ($ballotRows === []) {
                continue;
            }

            $voteCount += $this->storeBallots($ballotRows, $picksByCode);
            $ballotCount += count($ballotRows);

            if ($ballotCount >= $nextReport) {
                $report('  '.number_format($ballotCount, 0, ',', '.').' suara terkirim...');
                $nextReport += self::REPORT_EVERY;
            }
        } while (count($voterIds) === self::BATCH_SIZE);

        return [
            'voters' => $voters,
            'ballots' => $ballotCount,
            'votes' => $voteCount,
            'election' => $election,
        ];
    }

    /**
     * Send times as an offset in seconds from the start of the window. A share of ballots arrives in the
     * opening minutes, a share in the closing stretch, and the rest spreads across the window.
     */
    public static function sentAtOffset(int $windowSeconds, float $shareRandom, float $timeRandom): int
    {
        $share = (int) floor($shareRandom * 100);

        return match (true) {
            $share < self::OPENING_SHARE => (int) floor($windowSeconds * 0.08 * $timeRandom),
            $share < self::OPENING_SHARE + self::CLOSING_SHARE => (int) floor($windowSeconds * (0.9 + 0.1 * $timeRandom)),
            default => (int) floor($windowSeconds * $timeRandom),
        };
    }

    /**
     * Cumulative weights for choosing a candidate, most popular first. Weights fall off with rank, so a few
     * candidates lead and the rest trail, without any one of them taking every vote.
     *
     * @return list<float>
     */
    public static function cumulativeWeights(int $count): array
    {
        $weights = array_map(fn (int $rank): float => 1 / ($rank ** 0.7), range(1, $count));
        $total = array_sum($weights);
        $running = 0.0;

        return array_map(function (float $weight) use (&$running, $total): float {
            $running += $weight / $total;

            return $running;
        }, $weights);
    }

    /**
     * One voter's picks as field id => candidate id. Each field is picked with FIELD_PICK_PERCENT chance, at
     * least one field is always picked, and no candidate is picked in more fields than the ballot allows.
     *
     * @param  list<string>  $fieldIds
     * @param  list<string>  $candidateIds
     * @param  list<float>  $cumulativeWeights
     * @param  Closure(): float  $random
     * @return array<string, string>
     */
    public static function picks(array $fieldIds, array $candidateIds, array $cumulativeWeights, Closure $random): array
    {
        $picks = [];
        $perCandidate = [];

        foreach ($fieldIds as $fieldId) {
            if ($random() * 100 >= self::FIELD_PICK_PERCENT) {
                continue;
            }

            $candidateId = self::chooseCandidate($candidateIds, $cumulativeWeights, $perCandidate, $random);

            if ($candidateId !== null) {
                $picks[$fieldId] = $candidateId;
                $perCandidate[$candidateId] = ($perCandidate[$candidateId] ?? 0) + 1;
            }
        }

        if ($picks === []) {
            $candidateId = self::chooseCandidate($candidateIds, $cumulativeWeights, [], $random);
            $picks[$fieldIds[0]] = $candidateId;
        }

        return $picks;
    }

    /**
     * @param  list<string>  $candidateIds
     * @param  list<float>  $cumulativeWeights
     * @param  array<string, int>  $perCandidate  Fields each candidate already has on this ballot.
     */
    private static function chooseCandidate(array $candidateIds, array $cumulativeWeights, array $perCandidate, Closure $random): ?string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $roll = $random();

            foreach ($cumulativeWeights as $index => $cumulative) {
                if ($roll < $cumulative) {
                    $candidateId = $candidateIds[$index];

                    if (($perCandidate[$candidateId] ?? 0) < BallotService::MAX_FIELDS_PER_CANDIDATE) {
                        return $candidateId;
                    }

                    break;
                }
            }
        }

        return null;
    }

    /**
     * Adds ballots dated now, from voters who have not voted yet, the way live voting adds them. Returns how
     * many were added, which is fewer than asked once every voter has voted.
     */
    public function addLiveBallots(int $count): int
    {
        $fieldIds = Field::query()->ordered()->pluck('id')->all();
        $candidateIds = Candidate::query()->orderBy('position')->orderBy('id')->pluck('id')->all();

        if ($fieldIds === [] || $candidateIds === []) {
            return 0;
        }

        $voterIds = DB::table('users')
            ->where('is_admin', false)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('ballots')->whereColumn('ballots.user_id', 'users.id'))
            ->inRandomOrder()
            ->limit($count)
            ->pluck('id')
            ->all();

        if ($voterIds === []) {
            return 0;
        }

        $weights = self::cumulativeWeights(count($candidateIds));
        $random = self::randomFloat(...);
        $timestamp = now()->utc()->format('Y-m-d H:i:s');
        $ballotRows = [];
        $picksByCode = [];

        foreach ($voterIds as $voterId) {
            $code = Ballot::newReceiptCode();

            $ballotRows[] = [
                'user_id' => $voterId,
                'receipt_code' => $code,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $picksByCode[$code] = [
                'timestamp' => $timestamp,
                'picks' => self::picks($fieldIds, $candidateIds, $weights, $random),
            ];
        }

        $this->storeBallots($ballotRows, $picksByCode);

        return count($voterIds);
    }

    /**
     * The reason this database may not be used for simulation, or null when it may.
     */
    public static function simulationDatabaseProblem(string $database): ?string
    {
        if (app()->environment('production')) {
            return 'Simulasi tidak boleh dijalankan di production.';
        }

        if (! Str::contains($database, ['simulasi', '_test'])) {
            return "Database \"{$database}\" bukan database simulasi. Atur DB_DATABASE ke database simulasi, misalnya db_voting_simulasi.";
        }

        return null;
    }

    /**
     * Writes ballot rows, then writes each pick under its ballot. $picksByCode maps a receipt code to its
     * timestamp and picks. Rows go in through the query builder, which skips the model's UUID generation, so
     * each row gets its id here. Returns how many picks were written.
     *
     * @param  list<array<string, mixed>>  $ballotRows
     * @param  array<string, array{timestamp: string, picks: array<string, string>}>  $picksByCode
     */
    private function storeBallots(array $ballotRows, array $picksByCode): int
    {
        $ballotRows = array_map(fn (array $row): array => ['id' => (string) Str::uuid7()] + $row, $ballotRows);

        DB::table('ballots')->insert($ballotRows);

        $ballotIds = DB::table('ballots')
            ->whereIn('receipt_code', array_keys($picksByCode))
            ->pluck('id', 'receipt_code')
            ->all();

        $voteRows = [];

        foreach ($picksByCode as $code => $ballot) {
            foreach ($ballot['picks'] as $fieldId => $candidateId) {
                $voteRows[] = [
                    'id' => (string) Str::uuid7(),
                    'ballot_id' => $ballotIds[$code],
                    'field_id' => $fieldId,
                    'candidate_id' => $candidateId,
                    'created_at' => $ballot['timestamp'],
                    'updated_at' => $ballot['timestamp'],
                ];
            }
        }

        foreach (array_chunk($voteRows, 5000) as $chunk) {
            DB::table('votes')->insert($chunk);
        }

        return count($voteRows);
    }

    private function registerVoters(int $voters, Generator $faker, CarbonInterface $windowStart, Closure $report): void
    {
        $password = Hash::make('password');
        $registeredAt = $windowStart->copy()->subDays(30)->utc()->format('Y-m-d H:i:s');
        $nextReport = self::REPORT_EVERY;

        $registered = 0;

        for ($offset = 1; $offset <= $voters; $offset += self::BATCH_SIZE) {
            $rows = [];

            for ($number = $offset; $number < min($offset + self::BATCH_SIZE, $voters + 1); $number++) {
                $rows[] = [
                    'id' => (string) Str::uuid7(),
                    'name' => $faker->name(),
                    'email' => 'pemilih'.$number.'@simulasi.test',
                    'password' => $password,
                    'is_admin' => false,
                    'created_at' => $registeredAt,
                    'updated_at' => $registeredAt,
                ];
            }

            DB::table('users')->insert($rows);

            $registered += count($rows);

            if ($registered >= $nextReport) {
                $report('  '.number_format($registered, 0, ',', '.').' pemilih terdaftar...');
                $nextReport += self::REPORT_EVERY;
            }
        }
    }

    private static function fieldName(int $position): string
    {
        return [
            1 => 'Ketua',
            2 => 'Wakil Ketua',
            3 => 'Bendahara',
            4 => 'Sekretaris',
        ][$position] ?? 'Bidang '.$position;
    }

    private static function randomFloat(): float
    {
        return mt_rand() / (mt_getrandmax() + 1);
    }
}
