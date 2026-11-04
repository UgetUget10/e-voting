<?php

namespace Tests\Feature;

use App\Filament\Voter\Pages\Vote;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoteCandidateClickTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_buttons_quote_their_ids_so_clicks_reach_the_picker(): void
    {
        Election::create(['name' => 'Tes', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $field = Field::create(['name' => 'Ketua OSIS']);
        $candidate = Candidate::create(['name' => 'Aisyah Rahma']);
        Filament::setCurrentPanel(Filament::getPanel('pemilih'));

        Livewire::actingAs(User::factory()->create())
            ->test(Vote::class)
            ->assertSeeHtml("wire:click=\"\$set('data.picks.{$field->id}', &#039;{$candidate->id}&#039;)\"");
    }
}
