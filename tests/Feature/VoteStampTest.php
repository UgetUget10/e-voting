<?php

namespace Tests\Feature;

use App\Filament\Voter\Pages\Vote;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoteStampTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_selected_candidate_gets_the_ballot_stamp(): void
    {
        Election::create(['name' => 'Test', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $presiden = Field::create(['name' => 'Presiden']);
        $andi = Candidate::create(['name' => 'Andi']);
        Candidate::create(['name' => 'Bunga']);

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->assertDontSeeHtml('voter-candidate-stamp')
            ->set('data.picks.'.$presiden->id, $andi->id)
            ->assertSeeHtml('voter-candidate-stamp')
            ->assertSee('Pilihan Anda');
    }
}
