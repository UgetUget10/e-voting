<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_pages_render_with_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        $voter = User::factory()->create();

        $field = Field::create(['name' => 'Presiden']);
        $candidate = Candidate::create(['name' => 'Jokowi']);
        Election::create(['name' => 'Tes', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $ballot = Ballot::create(['user_id' => $voter->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);

        foreach (['/admin', '/admin/fields', '/admin/candidates', '/admin/elections', '/admin/users', '/admin/ballots', '/admin/results'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_results_render_with_data_for_an_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $field = Field::create(['name' => 'Presiden']);
        $candidate = Candidate::create(['name' => 'Jokowi']);
        Election::create(['name' => 'Tes', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subHour()]);

        $this->actingAs($admin)->get('/pemilih/hasil')->assertOk()->assertSee('Jokowi');
    }
}
