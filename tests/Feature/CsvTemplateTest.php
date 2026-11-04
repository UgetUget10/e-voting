<?php

namespace Tests\Feature;

use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Filament\Resources\Fields\Pages\ListFields;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\User;
use App\Services\CsvTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CsvTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_template_contains_only_the_header_row(): void
    {
        $response = CsvTemplate::download('template-tes.csv', ['nama', 'nip', 'golongan']);

        ob_start();
        $response->sendContent();

        $this->assertSame("nama,nip,golongan\n", ob_get_clean());
        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('template-tes.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_candidate_list_offers_the_template_action(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListCandidates::class)
            ->assertActionExists('template');
    }

    public function test_field_list_offers_the_template_action(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListFields::class)
            ->assertActionExists('template');
    }

    public function test_user_list_offers_the_template_action(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->assertActionExists('template');
    }
}
