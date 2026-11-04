<?php

namespace Tests\Feature;

use App\Filament\Resources\Elections\Pages\ListElections;
use App\Filament\Widgets\BallotsByHour;
use App\Models\AdminActivity;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Role;
use App\Models\User;
use App\Services\UserCsvImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ArchiveDiffAndSpikeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        return User::factory()->create(['is_admin' => true])->assignRole($role);
    }

    public function test_archiving_a_closed_election_keeps_a_snapshot_and_clears_the_current_one(): void
    {
        $election = Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);
        $this->assertTrue($election->is(Election::current()));

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->callTableAction('archive', $election);

        $election->refresh();
        $this->assertTrue($election->isArchived());
        $this->assertArrayHasKey('fields', $election->results_snapshot);
        $this->assertNull(Election::current());
    }

    public function test_editing_a_candidate_records_old_and_new_values(): void
    {
        $candidate = Candidate::create(['name' => 'Andi']);
        $this->actingAs($this->superAdmin());

        $candidate->update(['name' => 'Budi']);

        $entry = AdminActivity::query()->latest('id')->first();
        $this->assertEquals(['name' => ['old' => 'Andi', 'new' => 'Budi']], $entry->changes);
    }

    public function test_hours_far_above_the_norm_are_flagged_as_spikes(): void
    {
        $counts = array_fill(0, 24, 1);
        $counts[14] = 40;

        $this->assertSame([14], BallotsByHour::spikeHours($counts));
        $this->assertSame([], BallotsByHour::spikeHours(array_fill(0, 24, 2)));
    }

    public function test_dry_run_import_counts_rows_without_saving(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "nama,email\nAni,ani@example.com\nBudi,tidak-valid\n");

        $result = app(UserCsvImport::class)->import($path, dryRun: true);
        unlink($path);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['invalid']);
        $this->assertDatabaseMissing('users', ['email' => 'ani@example.com']);
    }
}
