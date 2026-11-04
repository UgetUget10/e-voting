<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use App\Models\Vote;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeritaAcaraTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_a_printable_record_with_the_winners_and_participation(): void
    {
        Election::create(['name' => 'Ketua OSIS 2026', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi Pratama']);
        $bunga = Candidate::create(['name' => 'Bunga Lestari']);
        User::factory()->count(3)->create(['is_admin' => false]);
        $voter = User::factory()->create(['is_admin' => false]);
        $ballot = Ballot::create(['user_id' => $voter->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $andi->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('results.berita-acara'))
            ->assertOk()
            ->assertSee('Berita acara hasil pemilihan')
            ->assertSee('Ketua OSIS 2026')
            ->assertSee('Presiden')
            ->assertSee('Andi Pratama')
            ->assertSee('25%')
            ->assertSee('Ketua panitia');
    }

    public function test_record_shows_how_many_registered_voters_picked_in_each_field(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $candidate = Candidate::create(['name' => 'Andi Pratama']);
        User::factory()->count(2)->create(['is_admin' => false]);
        $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('results.berita-acara'))
            ->assertOk()
            ->assertSee('Partisipasi per bidang')
            ->assertSeeInOrder(['Presiden', '1', '3', '33.3%']);
    }

    public function test_record_lists_ballots_sent_per_jakarta_hour(): void
    {
        $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
        $ballot->forceFill(['created_at' => Carbon::create(2026, 10, 5, 10, 30, 0, 'Asia/Jakarta')->utc()])->save();

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('results.berita-acara'))
            ->assertOk()
            ->assertSee('Pengiriman suara per jam (WIB)')
            ->assertSeeInOrder(['5 Okt 2026', '10.00 - 10.59', '1']);
    }

    public function test_voters_cannot_open_the_record(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('results.berita-acara'))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get(route('results.berita-acara'))->assertRedirect();
    }
}
