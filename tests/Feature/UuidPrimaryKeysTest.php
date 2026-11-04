<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UuidPrimaryKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_record_gets_a_uuid_primary_key(): void
    {
        $user = User::factory()->create();
        $election = Election::create(['name' => 'Tes', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $field = Field::create(['name' => 'Presiden']);
        $candidate = Candidate::create(['name' => 'Andi']);
        $ballot = Ballot::create(['user_id' => $user->id]);
        $vote = Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
        $role = Role::create(['name' => 'tes', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'tes', 'guard_name' => 'web']);

        foreach ([$user, $election, $field, $candidate, $ballot, $vote, $role, $permission] as $record) {
            $this->assertTrue(Str::isUuid($record->getKey()), class_basename($record).' should have a UUID key.');
        }
    }

    public function test_a_user_can_be_given_a_role_with_uuid_keys(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'tes', 'guard_name' => 'web']);

        $user->assignRole($role);

        $this->assertTrue($user->fresh()->hasRole('tes'));
        $this->assertDatabaseHas('model_has_roles', ['role_id' => $role->id, 'model_id' => $user->id]);
    }

    public function test_simulated_ballots_and_votes_get_uuid_keys_and_unique_receipt_codes(): void
    {
        $this->artisan('simulasi:pemilihan', ['--voters' => 30, '--candidates' => 21, '--fields' => 2, '--turnout' => 50, '--hours' => 4])
            ->assertSuccessful();

        $this->artisan('simulasi:suara-masuk', ['--jumlah' => 5, '--putaran' => 1])
            ->assertSuccessful();

        $ballots = Ballot::query()->get();
        $votes = Vote::query()->get();

        $this->assertGreaterThan(0, $votes->count());
        $ballots->each(fn (Ballot $ballot) => $this->assertTrue(Str::isUuid($ballot->id)));
        $votes->each(fn (Vote $vote) => $this->assertTrue(Str::isUuid($vote->id)));
        $this->assertSame($ballots->count(), $ballots->pluck('receipt_code')->unique()->count());
        $this->assertSame($votes->count(), Vote::query()->whereIn('ballot_id', $ballots->pluck('id'))->count());
    }
}
