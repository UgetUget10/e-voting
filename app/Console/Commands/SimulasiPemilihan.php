<?php

namespace App\Console\Commands;

use App\Models\Election;
use App\Models\User;
use App\Services\BallotService;
use App\Services\VotingSimulator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulasiPemilihan extends Command
{
    /**
     * @var string
     */
    protected $signature = 'simulasi:pemilihan
        {--voters=1200000 : Registered voters (more than one million by default)}
        {--candidates=24 : Number of candidates}
        {--fields=4 : Number of fields}
        {--turnout=75 : Percentage of voters who send a ballot}
        {--hours=8 : Length of the voting window, which ends now}
        {--seed=2026 : Random seed, so the same numbers come out again}
        {--fresh : Empty the database first (migrate:fresh)}';

    /**
     * @var string
     */
    protected $description = 'Fill a simulation database with a large dummy election and a voting schedule';

    public function handle(VotingSimulator $simulator): int
    {
        $database = DB::connection()->getDatabaseName();
        $problem = VotingSimulator::simulationDatabaseProblem($database);

        if ($problem !== null) {
            $this->error($problem);

            return self::FAILURE;
        }

        $options = [
            'voters' => (int) $this->option('voters'),
            'candidates' => (int) $this->option('candidates'),
            'fields' => (int) $this->option('fields'),
            'turnout' => (int) $this->option('turnout'),
            'hours' => (int) $this->option('hours'),
            'seed' => (int) $this->option('seed'),
        ];

        if ($error = $this->invalidOption($options)) {
            $this->error($error);

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        } elseif (User::query()->where('is_admin', false)->exists() || Election::query()->exists()) {
            $this->error('Database sudah berisi data pemilihan. Tambahkan --fresh untuk mengosongkannya terlebih dahulu.');

            return self::FAILURE;
        }

        $this->info("Mengisi database \"{$database}\" dengan {$options['voters']} pemilih, {$options['candidates']} kandidat, {$options['fields']} bidang.");

        $started = microtime(true);

        $result = $simulator->run(
            voters: $options['voters'],
            candidates: $options['candidates'],
            fields: $options['fields'],
            turnoutPercent: $options['turnout'],
            hours: $options['hours'],
            seed: $options['seed'],
            report: fn (string $line) => $this->line($line),
        );

        $election = $result['election'];

        $this->newLine();
        $this->info('Simulasi selesai dalam '.number_format(microtime(true) - $started, 1, ',', '.').' detik.');
        $this->table(['Data', 'Jumlah'], [
            ['Pemilih terdaftar', number_format($result['voters'], 0, ',', '.')],
            ['Suara masuk', number_format($result['ballots'], 0, ',', '.')],
            ['Pilihan (votes)', number_format($result['votes'], 0, ',', '.')],
            ['Partisipasi', number_format($result['ballots'] / max(1, $result['voters']) * 100, 1, ',', '.').'%'],
            ['Kandidat', $options['candidates']],
            ['Bidang', $options['fields']],
        ]);
        $this->table(['Jadwal pemilihan', ''], [
            ['Nama', $election->name],
            ['Dibuka', $election->opens_at->timezone('Asia/Jakarta')->translatedFormat('j M Y, H:i').' WIB'],
            ['Ditutup', $election->closes_at->timezone('Asia/Jakarta')->translatedFormat('j M Y, H:i').' WIB'],
            ['Login admin', 'admin@simulasi.test / password'],
            ['Login pemilih (contoh)', 'pemilih1@simulasi.test / password'],
        ]);

        return self::SUCCESS;
    }

    /**
     * @param  array{voters: int, candidates: int, fields: int, turnout: int, hours: int, seed: int}  $options
     */
    private function invalidOption(array $options): ?string
    {
        return match (true) {
            $options['voters'] < 1 => 'Jumlah pemilih harus minimal 1.',
            $options['candidates'] < 1 => 'Jumlah kandidat harus minimal 1.',
            $options['fields'] < 1 => 'Jumlah bidang harus minimal 1.',
            $options['fields'] > $options['candidates'] * BallotService::MAX_FIELDS_PER_CANDIDATE => 'Kandidat terlalu sedikit: setiap kandidat hanya boleh dipilih di '.BallotService::MAX_FIELDS_PER_CANDIDATE.' bidang.',
            $options['turnout'] < 1 || $options['turnout'] > 100 => 'Partisipasi harus antara 1 dan 100 persen.',
            $options['hours'] < 1 => 'Jendela pemungutan suara harus minimal 1 jam.',
            default => null,
        };
    }
}
