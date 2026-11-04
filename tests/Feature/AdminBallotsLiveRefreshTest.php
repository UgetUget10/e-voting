<?php

namespace Tests\Feature;

use App\Filament\Resources\Ballots\Pages\ListBallots;
use App\Models\Ballot;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBallotsLiveRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_ballot_is_picked_up_on_the_next_check(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(ListBallots::class);
        $before = $component->instance()->fingerprint;

        $this->ballot();

        $component
            ->call('checkForChanges')
            ->assertSet('fingerprint', Ballot::liveFingerprint());

        $this->assertNotSame($before, Ballot::liveFingerprint());
    }

    public function test_a_check_with_nothing_new_leaves_the_page_as_it_was(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(ListBallots::class);
        $lastChangedAt = $component->instance()->lastChangedAt;

        $component
            ->call('checkForChanges')
            ->assertSet('lastChangedAt', $lastChangedAt);
    }

    public function test_a_paused_page_does_not_pick_up_changes(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(ListBallots::class);
        $before = $component->instance()->fingerprint;

        $component->callAction('toggleLive')->assertSet('liveUpdates', false);

        $this->ballot();

        $component
            ->call('checkForChanges')
            ->assertSet('fingerprint', $before);
    }

    public function test_the_live_bar_polls_only_while_updates_are_on(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(ListBallots::class);

        $component->assertSeeHtml('wire:poll.5s="checkForChanges"');

        $component
            ->callAction('toggleLive')
            ->assertDontSeeHtml('wire:poll.5s="checkForChanges"');
    }

    public function test_counts_are_right_straight_after_a_change_without_waiting_for_a_cache_window(): void
    {
        $this->assertSame(0, Ballot::liveCounts()['all']);

        $this->ballot();

        $this->assertSame(1, Ballot::liveCounts()['all']);
    }

    private function ballot(): Ballot
    {
        return Ballot::create([
            'user_id' => User::factory()->create(['is_admin' => false])->id,
        ]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
