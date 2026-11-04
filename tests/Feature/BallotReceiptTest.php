<?php

namespace Tests\Feature;

use App\Filament\Voter\Pages\CheckReceipt;
use App\Filament\Voter\Pages\Vote;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BallotReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Field $presiden;

    private Candidate $andi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presiden = Field::create(['name' => 'Presiden']);
        $this->andi = Candidate::create(['name' => 'Andi']);

        Filament::setCurrentPanel(Filament::getPanel('pemilih'));
    }

    public function test_every_ballot_gets_a_unique_receipt_code_when_created(): void
    {
        $first = Ballot::create(['user_id' => User::factory()->create()->id]);
        $second = Ballot::create(['user_id' => User::factory()->create()->id]);

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', $first->receipt_code);
        $this->assertNotSame($first->receipt_code, $second->receipt_code);
    }

    public function test_sending_a_ballot_leads_to_the_status_page_without_showing_the_receipt_code(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $voter = User::factory()->create();

        Livewire::actingAs($voter)
            ->test(Vote::class)
            ->set('data.picks', [$this->presiden->id => $this->andi->id])
            ->call('submit')
            ->assertRedirect('/pemilih/status-memilih');

        $code = Ballot::query()->where('user_id', $voter->id)->value('receipt_code');

        Livewire::actingAs($voter)
            ->test(CheckReceipt::class)
            ->assertSee('Terima kasih, Anda sudah memilih.')
            ->assertDontSee('Tanda terima Anda')
            ->assertDontSee($code);
    }

    public function test_receipt_check_says_thank_you_once_the_voter_has_voted(): void
    {
        $voter = User::factory()->create(['name' => 'Pemilih Rahasia']);
        $ballot = Ballot::create(['user_id' => $voter->id]);
        $ballot->votes()->create(['field_id' => $this->presiden->id, 'candidate_id' => $this->andi->id]);

        Livewire::actingAs($voter)
            ->test(CheckReceipt::class)
            ->assertSee('Terima kasih, Anda sudah memilih.')
            ->assertDontSee('Pilih Sekarang')
            ->assertDontSee('Andi')
            ->assertDontSee('Pemilih Rahasia');
    }

    public function test_receipt_check_asks_a_voter_who_has_not_voted_to_vote_now(): void
    {
        Election::create(['name' => 'Tes', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs(User::factory()->create())
            ->test(CheckReceipt::class)
            ->assertSee('Anda belum memilih, mohon segera memilih.')
            ->assertSee('Pilih Sekarang')
            ->assertSeeHtml('href="'.Vote::getUrl(panel: 'pemilih').'?akses=');
    }

    public function test_receipt_check_does_not_offer_to_vote_once_voting_has_closed(): void
    {
        Election::create(['name' => 'Tes', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subHour()]);

        Livewire::actingAs(User::factory()->create())
            ->test(CheckReceipt::class)
            ->assertSee('Voting sudah ditutup. Anda tidak bisa lagi mengirim suara.')
            ->assertDontSee('Pilih Sekarang');
    }

    public function test_receipt_check_no_longer_offers_a_code_lookup(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pemilih/status-memilih')
            ->assertOk()
            ->assertDontSee('Kode tanda terima')
            ->assertDontSee('name="code"', escape: false);
    }

    public function test_the_old_status_address_redirects_to_the_new_one(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pemilih/cek-tanda-terima')
            ->assertRedirect('/pemilih/status-memilih');
    }
}
