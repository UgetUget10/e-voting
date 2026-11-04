<?php

namespace Tests\Feature;

use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Models\AdminActivity;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CandidateListToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_drag_reorder_is_refused_while_the_election_is_locked(): void
    {
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $bunga = Candidate::create(['name' => 'Bunga', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->call('reorderTable', [$bunga->getKey(), $andi->getKey()]);

        $this->assertSame(['Andi', 'Bunga'], Candidate::query()->ordered()->pluck('name')->all());
    }

    public function test_drag_reorder_is_only_offered_in_table_layout(): void
    {
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $bunga = Candidate::create(['name' => 'Bunga', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->set('displayMode', 'grid')
            ->call('reorderTable', [$bunga->getKey(), $andi->getKey()]);

        $this->assertSame(['Andi', 'Bunga'], Candidate::query()->ordered()->pluck('name')->all());

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->set('displayMode', 'table')
            ->call('reorderTable', [$bunga->getKey(), $andi->getKey()]);

        $this->assertSame(['Bunga', 'Andi'], Candidate::query()->ordered()->pluck('name')->all());
    }

    public function test_shuffle_gives_every_candidate_a_new_unique_position(): void
    {
        foreach (range(1, 6) as $position) {
            Candidate::create(['name' => 'Kandidat '.$position, 'position' => $position]);
        }

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callAction('shuffle')
            ->assertHasNoActionErrors();

        $this->assertSame([1, 2, 3, 4, 5, 6], Candidate::query()->orderBy('position')->pluck('position')->all());
        $this->assertNotNull(AdminActivity::query()->where('description', 'like', 'Mengacak urutan 6 kandidat%')->first());
    }

    public function test_shuffle_is_hidden_while_the_election_is_locked(): void
    {
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertActionHidden('shuffle');
    }

    public function test_layout_toggle_switches_between_table_and_card_grid(): void
    {
        Candidate::create(['name' => 'Andi', 'position' => 1, 'golongan' => 'III/a']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSet('displayMode', 'table')
            ->callAction('layout')
            ->assertSet('displayMode', 'grid')
            ->assertSeeHtml('admin-candidate-card')
            ->callAction('layout')
            ->assertSet('displayMode', 'table');
    }

    public function test_name_column_shows_nip_and_warns_about_a_similar_name(): void
    {
        Candidate::create(['name' => 'Andi Pratama', 'position' => 1, 'nip' => '198501012010011001']);
        Candidate::create(['name' => 'Andi Pratamaa', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSee('NIP 198501012010011001')
            ->assertSee('Mirip dengan Andi Pratamaa');
    }

    public function test_golongan_can_be_edited_inline_from_the_table(): void
    {
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->call('updateTableColumnState', 'golongan', (string) $andi->getKey(), 'III/a');

        $this->assertSame('III/a', $andi->fresh()->golongan);
    }

    public function test_golongan_cannot_be_edited_inline_while_the_election_is_locked(): void
    {
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->call('updateTableColumnState', 'golongan', (string) $andi->getKey(), 'III/a');

        $this->assertNull($andi->fresh()->golongan);
    }

    public function test_bulk_change_sets_golongan_for_every_selected_candidate_with_one_log_entry(): void
    {
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $bunga = Candidate::create(['name' => 'Bunga', 'position' => 2]);
        $cahya = Candidate::create(['name' => 'Cahya', 'position' => 3]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callTableBulkAction('changeGolongan', [$andi, $bunga], data: ['golongan' => 'IV/a'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('IV/a', $andi->fresh()->golongan);
        $this->assertSame('IV/a', $bunga->fresh()->golongan);
        $this->assertNull($cahya->fresh()->golongan);
        $this->assertSame(1, AdminActivity::query()->where('description', 'like', 'Mengubah golongan 2 kandidat%')->count());
        $this->assertSame(0, AdminActivity::query()->where('description', 'like', 'Mengubah kandidat%')->count());
    }

    public function test_replacing_a_photo_from_the_row_swaps_the_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('candidates/lama.jpg', 'lama');
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1, 'photo_path' => 'candidates/lama.jpg']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callTableAction('replacePhoto', $andi, data: ['photo_path' => UploadedFile::fake()->image('baru.jpg', 800, 1000)])
            ->assertHasNoTableActionErrors();

        $this->assertNotSame('candidates/lama.jpg', $andi->fresh()->photo_path);
        $this->assertStringStartsWith('candidates/', $andi->fresh()->photo_path);
        Storage::disk('public')->assertMissing('candidates/lama.jpg');
    }

    public function test_pdf_download_is_logged(): void
    {
        Candidate::create(['name' => 'Andi', 'position' => 1]);

        $this->actingAs($this->superAdmin())
            ->get(route('candidates.export.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertNotNull(AdminActivity::query()->where('description', 'Mengunduh daftar kandidat dalam PDF.')->first());
    }

    public function test_empty_list_offers_a_way_to_add_a_candidate(): void
    {
        Field::create(['name' => 'Presiden', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSee('Belum ada kandidat')
            ->assertSee('Tambah kandidat');
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
