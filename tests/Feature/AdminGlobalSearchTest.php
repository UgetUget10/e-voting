<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_find_voters_by_name_with_their_role(): void
    {
        $this->superAdmin();
        User::factory()->create(['name' => 'Budi Santoso', 'is_admin' => false]);

        $results = $this->searchFor('Budi');

        $this->assertContains('Budi Santoso', array_column($results, 'title'));
        $this->assertContains('Pemilih', array_column(array_column($results, 'details'), 'Peran'));
    }

    public function test_candidates_are_searchable_by_name_and_show_their_nip(): void
    {
        $this->superAdmin();
        Candidate::create(['name' => 'Andi Pratama', 'nip' => '198705012010011001']);

        $results = $this->searchFor('Andi');

        $this->assertContains('Andi Pratama', array_column($results, 'title'));
        $this->assertContains('198705012010011001', array_column(array_column($results, 'details'), 'NIP'));
    }

    public function test_fields_are_searchable_by_name(): void
    {
        $this->superAdmin();
        Field::create(['name' => 'Ketua Bidang Keagamaan', 'position' => 1]);

        $this->assertContains('Ketua Bidang Keagamaan', array_column($this->searchFor('Keagamaan'), 'title'));
    }

    public function test_elections_are_searchable_by_name_with_their_status(): void
    {
        $this->superAdmin();
        Election::create(['name' => 'Pemilihan Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $results = $this->searchFor('OSIS');

        $this->assertContains('Pemilihan Ketua OSIS', array_column($results, 'title'));
    }

    public function test_admins_without_view_permission_do_not_see_voters_in_search(): void
    {
        User::factory()->create(['name' => 'Budi Santoso', 'is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertContains('Budi Santoso', array_column($this->searchFor('Budi', $this->superAdmin()), 'title'));
        $this->assertNotContains('Budi Santoso', array_column($this->searchFor('Budi', $admin), 'title'));
    }

    /**
     * @return list<array{title: string, details: array<string, string>}>
     */
    private function searchFor(string $query, ?User $admin = null): array
    {
        $this->actingAs($admin ?? User::query()->where('is_admin', true)->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $categories = Filament::getGlobalSearchProvider()->getResults($query)?->getCategories() ?? collect();

        return $categories
            ->flatMap(fn ($results): array => collect($results)->map(fn ($result): array => [
                'title' => (string) $result->title,
                'details' => $result->details,
            ])->all())
            ->values()
            ->all();
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
