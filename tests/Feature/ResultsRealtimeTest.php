<?php

namespace Tests\Feature;

use App\Filament\Voter\Pages\Results;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use App\Services\BallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResultsRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private Field $presiden;

    private Candidate $andi;

    private Candidate $bunga;

    protected function setUp(): void
    {
        parent::setUp();

        Election::create([
            'name' => 'Test',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'show_results_early' => true,
        ]);

        $this->presiden = Field::create(['name' => 'Presiden']);
        $this->andi = Candidate::create(['name' => 'Andi']);
        $this->bunga = Candidate::create(['name' => 'Bunga']);
    }

    public function test_results_page_polls_so_new_votes_appear_without_reload(): void
    {
        $this->actingAs($this->adminViewer())
            ->get('/pemilih/hasil')
            ->assertOk()
            ->assertSee('wire:poll.visible.5s', escape: false);
    }

    public function test_a_new_ballot_changes_the_counts_on_the_next_refresh(): void
    {
        $this->castBallot(User::factory()->create(), $this->andi);

        $page = Livewire::actingAs($this->adminViewer())->test(Results::class);
        $page->assertSeeHtml('<strong>1</strong> suara');

        $this->castBallot(User::factory()->create(), $this->bunga);
        $this->castBallot(User::factory()->create(), $this->bunga);
        User::factory()->create();

        $page->call('$refresh')
            ->assertSeeHtml('<strong>2</strong> suara')
            ->assertSee('3 dari 4 pemilih sudah memilih');
    }

    public function test_results_stay_hidden_until_they_are_visible(): void
    {
        Election::query()->update(['show_results_early' => false, 'closes_at' => now()->addDay()]);
        $this->castBallot(User::factory()->create(), $this->andi);

        Livewire::actingAs($this->adminViewer())
            ->test(Results::class)
            ->assertSee('Hasil akan ditampilkan setelah voting ditutup')
            ->assertDontSee('Andi');
    }

    public function test_polling_stops_and_the_page_shows_final_results_once_voting_closes(): void
    {
        Election::query()->update(['opens_at' => now()->subDays(2), 'closes_at' => now()->subHour()]);

        $this->actingAs($this->adminViewer())
            ->get('/pemilih/hasil')
            ->assertOk()
            ->assertDontSee('wire:poll', escape: false)
            ->assertSee('Hasil final');
    }

    public function test_participation_trend_shows_the_share_of_voters_who_have_cast_a_ballot(): void
    {
        $this->castBallot(User::factory()->create(), $this->andi);
        $this->castBallot(User::factory()->create(), $this->bunga);
        User::factory()->create();

        $this->actingAs($this->adminViewer())
            ->get('/pemilih/hasil')
            ->assertOk()
            ->assertSee('Tren partisipasi')
            ->assertSee('67%')
            ->assertSeeHtml('class="voter-trend-line"');
    }

    public function test_summary_counts_who_has_not_voted_yet(): void
    {
        $this->castBallot(User::factory()->create(), $this->andi);
        $this->castBallot(User::factory()->create(), $this->bunga);
        User::factory()->create();
        User::factory()->create();

        $this->actingAs($this->adminViewer())
            ->get('/pemilih/hasil')
            ->assertOk()
            ->assertSeeHtml('<span class="voter-stat-value">4</span>')
            ->assertSeeHtml('<span class="voter-stat-value">2</span>')
            ->assertSeeHtml('<span class="voter-stat-value">2</span>');
    }

    public function test_official_recap_lists_every_candidate_with_totals(): void
    {
        $this->castBallot(User::factory()->create(), $this->andi);
        $this->castBallot(User::factory()->create(), $this->andi);
        $this->castBallot(User::factory()->create(), $this->bunga);

        $this->actingAs($this->adminViewer())
            ->get('/pemilih/hasil')
            ->assertOk()
            ->assertSee('Rekapitulasi perolehan suara')
            ->assertDontSee('Cetak berita acara')
            ->assertSee('Total suara sah')
            ->assertSeeHtml('<td class="is-num">2</td>');
    }

    public function test_participation_trend_waits_for_the_first_ballot(): void
    {
        $this->actingAs($this->adminViewer())
            ->get('/pemilih/hasil')
            ->assertOk()
            ->assertSee('Grafik akan muncul setelah suara pertama masuk')
            ->assertDontSeeHtml('class="voter-trend-line"');
    }

    /**
     * Results are open to admins, who monitor them without a ballot and are not counted as voters.
     */
    private function adminViewer(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function castBallot(User $user, Candidate $candidate): void
    {
        app(BallotService::class)->submit($user, [$this->presiden->id => $candidate->id]);
    }
}
