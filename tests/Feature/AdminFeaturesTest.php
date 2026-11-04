<?php

namespace Tests\Feature;

use App\Filament\Pages\ElectionDashboard;
use App\Filament\Resources\Ballots\Pages\ListBallots;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Filament\Resources\Elections\Pages\ListElections;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Widgets\BallotStats;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use App\Services\BallotService;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Field $presiden;

    private Field $osis;

    private Candidate $andi;

    private Candidate $bunga;

    protected function setUp(): void
    {
        parent::setUp();

        Election::create(['name' => 'Test', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $this->presiden = Field::create(['name' => 'Presiden']);
        $this->osis = Field::create(['name' => 'Ketua OSIS']);
        $this->andi = Candidate::create(['name' => 'Andi', 'nip' => '198501012010011001']);
        $this->bunga = Candidate::create(['name' => 'Bunga']);

        $ballots = app(BallotService::class);
        $ballots->submit(User::factory()->create(), [$this->presiden->id => $this->andi->id, $this->osis->id => $this->andi->id]);
        $ballots->submit(User::factory()->create(), [$this->presiden->id => $this->andi->id, $this->osis->id => $this->bunga->id]);
    }

    public function test_admin_results_page_shows_turnout_and_refreshes_live(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/results')
            ->assertOk()
            ->assertSee('Sudah memilih')
            ->assertSee('Live setiap 5 detik')
            ->assertSeeHtml('wire:poll.visible.5s')
            ->assertSee('Tren partisipasi')
            ->assertSee('Pemenang sementara per bidang')
            ->assertSee('Lolos');
    }

    public function test_ballot_list_names_the_field_next_to_each_pick(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSee('Presiden: Andi')
            ->assertSee('Ketua OSIS: Bunga');
    }

    public function test_candidate_list_shows_nip_and_total_votes(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSee('198501012010011001')
            ->assertSee('Total suara');
    }

    public function test_election_list_shows_whether_voting_is_open_closed_or_upcoming(): void
    {
        Election::create(['name' => 'Akan datang', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
        Election::create(['name' => 'Lampau', 'opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertSee('Belum dibuka')
            ->assertSee('Ditutup');
    }

    public function test_voter_table_can_filter_to_voters_who_have_not_voted_yet(): void
    {
        $voted = User::factory()->create(['name' => 'Sudah Memilih']);
        $pending = User::factory()->create(['name' => 'Belum Memilih']);
        app(BallotService::class)->submit($voted, [$this->presiden->id => $this->andi->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->filterTable('voted', false)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$voted]);
    }

    public function test_dashboard_toggles_early_results_for_voters(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ElectionDashboard::class)
            ->callAction('toggleResults')
            ->assertHasNoActionErrors();

        $this->assertTrue(Election::current()->show_results_early);

        Livewire::actingAs($this->superAdmin())
            ->test(ElectionDashboard::class)
            ->callAction('toggleResults');

        $this->assertFalse(Election::current()->show_results_early);
    }

    public function test_admin_dashboard_offers_the_early_results_control(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('Tampilkan hasil sekarang');
    }

    public function test_ballot_page_summarises_submissions_and_exports_csv(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin/ballots')
            ->assertOk()
            ->assertSee('Ekspor CSV');

        Livewire::actingAs($this->superAdmin())
            ->test(BallotStats::class)
            ->assertSee('Suara masuk')
            ->assertSee('Suara terakhir');

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertActionExists('export')
            ->callAction('export')
            ->assertHasNoActionErrors();
    }

    public function test_candidates_show_how_many_fields_they_already_fill(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSee('2 / 2 bidang')
            ->assertSee('1 / 2 bidang');
    }

    public function test_bulk_delete_refuses_candidates_that_already_have_votes(): void
    {
        $votesBefore = Vote::count();

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$this->andi]);

        $this->assertDatabaseHas('candidates', ['id' => $this->andi->id]);
        $this->assertSame($votesBefore, Vote::count());
    }

    public function test_bulk_delete_still_removes_candidates_nobody_has_picked(): void
    {
        $unpicked = Candidate::create(['name' => 'Citra']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$unpicked]);

        $this->assertDatabaseMissing('candidates', ['id' => $unpicked->id]);
    }

    public function test_edit_page_only_offers_delete_for_unpicked_candidates(): void
    {
        $admin = $this->superAdmin();
        $unpicked = Candidate::create(['name' => 'Citra']);

        Livewire::actingAs($admin)
            ->test(EditCandidate::class, ['record' => $this->andi->getKey()])
            ->assertActionHidden('delete');

        Livewire::actingAs($admin)
            ->test(EditCandidate::class, ['record' => $unpicked->getKey()])
            ->assertActionVisible('delete');
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
