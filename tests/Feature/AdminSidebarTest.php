<?php

namespace Tests\Feature;

use App\Filament\Resources\Ballots\BallotResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_groups_menus_and_shows_the_election_status(): void
    {
        Election::create(['name' => 'Ketua OSIS 2026', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Pemilihan')
            ->assertSee('Data')
            ->assertSee('Ketua OSIS 2026')
            ->assertSee('Dibuka');
    }

    public function test_badges_count_ballots_and_voters_who_have_not_voted(): void
    {
        $voted = User::factory()->create(['is_admin' => false]);
        User::factory()->count(2)->create(['is_admin' => false]);
        Ballot::create(['user_id' => $voted->id]);

        $this->assertSame('1', BallotResource::getNavigationBadge());
        $this->assertSame('2', UserResource::getNavigationBadge());
    }

    public function test_badges_are_hidden_when_there_is_nothing_to_count(): void
    {
        $this->assertNull(BallotResource::getNavigationBadge());
        $this->assertNull(UserResource::getNavigationBadge());
    }
}
