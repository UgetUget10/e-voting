<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use App\Models\Election;
use App\Services\VotingSimulator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulasiSuaraMasuk extends Command
{
    /**
     * @var string
     */
    protected $signature = 'simulasi:suara-masuk
        {--jumlah=50 : Ballots added in each round}
        {--putaran=10 : Number of rounds}
        {--jeda=30 : Seconds between rounds}';

    /**
     * @var string
     */
    protected $description = 'Add ballots now, from voters who have not voted yet, to watch the live features in the admin';

    public function handle(VotingSimulator $simulator): int
    {
        $problem = VotingSimulator::simulationDatabaseProblem(DB::connection()->getDatabaseName());

        if ($problem !== null) {
            $this->error($problem);

            return self::FAILURE;
        }

        $election = Election::current();

        if ($election === null) {
            $this->error('Belum ada pemilihan. Jalankan simulasi:pemilihan terlebih dahulu.');

            return self::FAILURE;
        }

        $perRound = max(1, (int) $this->option('jumlah'));
        $rounds = max(1, (int) $this->option('putaran'));
        $pause = max(0, (int) $this->option('jeda'));

        // Keep the election open for the whole run, so the ballots are accepted like real ones.
        $election->update([
            'closes_at' => max($election->closes_at, now()->addSeconds($rounds * $pause)->addMinutes(15)),
        ]);

        for ($round = 1; $round <= $rounds; $round++) {
            $added = $simulator->addLiveBallots($perRound);

            $this->line(sprintf(
                'Putaran %d: +%s suara (total %s)',
                $round,
                number_format($added, 0, ',', '.'),
                number_format(Ballot::query()->count(), 0, ',', '.'),
            ));

            if ($added === 0) {
                $this->warn('Semua pemilih sudah memilih. Berhenti.');

                break;
            }

            if ($round < $rounds && $pause > 0) {
                sleep($pause);
            }
        }

        return self::SUCCESS;
    }
}
