<?php

namespace Tests\Feature;

use App\Filament\Pages\ElectionDashboard;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Filament\Resources\Elections\Pages\ListElections;
use App\Filament\Widgets\AdminActivityFeed;
use App\Models\AdminActivity;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminActivityLog;
use App\Services\CandidateCsvImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_voting_now_is_recorded_with_the_admin_who_did_it(): void
    {
        $admin = $this->superAdmin();
        $election = Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        Livewire::actingAs($admin)
            ->test(ListElections::class)
            ->callTableAction('openNow', $election);

        $this->assertDatabaseHas('admin_activities', [
            'user_id' => $admin->id,
            'description' => 'Membuka voting "Ketua OSIS" sekarang.',
        ]);
    }

    public function test_closing_voting_now_is_recorded(): void
    {
        $election = Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->callTableAction('closeNow', $election);

        $this->assertDatabaseHas('admin_activities', ['description' => 'Menutup voting "Ketua OSIS" sekarang.']);
    }

    public function test_showing_early_results_is_recorded(): void
    {
        Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ElectionDashboard::class)
            ->callAction('toggleResults');

        $this->assertDatabaseHas('admin_activities', ['description' => 'Menampilkan hasil sementara kepada pemilih.']);
    }

    public function test_csv_import_is_recorded_with_how_many_candidates_arrived(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('kandidat.csv', "nama\nAndi\nBunga\n");

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callAction('import', data: ['file' => $file]);

        $this->assertDatabaseHas('admin_activities', ['description' => 'Mengimpor 2 kandidat dari CSV.']);
    }

    public function test_activity_feed_lists_the_latest_actions_with_their_author(): void
    {
        $admin = $this->superAdmin();
        AdminActivity::create(['user_id' => $admin->id, 'description' => 'Menutup voting "Ketua OSIS" sekarang.']);

        Livewire::actingAs($admin)
            ->test(AdminActivityFeed::class)
            ->assertSee('Menutup voting "Ketua OSIS" sekarang.')
            ->assertSee($admin->name);
    }

    public function test_activity_without_a_signed_in_user_has_no_author(): void
    {
        $entry = app(AdminActivityLog::class)->record('Tindakan sistem.');

        $this->assertNull($entry->user_id);
    }

    public function test_creating_renaming_and_deleting_candidates_fields_and_elections_is_recorded(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        $candidate = Candidate::create(['name' => 'Andi']);
        $candidate->update(['name' => 'Andi Pratama']);
        $field = Field::create(['name' => 'Presiden']);
        $field->update(['name' => 'Ketua']);
        $election = Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $election->update(['show_results_early' => true]);
        $election->update(['closes_at' => now()->addDays(3)]);

        $this->assertDatabaseHas('admin_activities', ['user_id' => $admin->id, 'description' => 'Menambahkan kandidat "Andi".']);
        $this->assertDatabaseHas('admin_activities', ['description' => 'Mengubah kandidat "Andi Pratama".']);
        $this->assertDatabaseHas('admin_activities', ['description' => 'Menambahkan bidang "Presiden".']);
        $this->assertDatabaseHas('admin_activities', ['description' => 'Mengubah bidang "Ketua".']);
        $this->assertDatabaseHas('admin_activities', ['description' => 'Menambahkan pemilihan "Ketua OSIS".']);
        $this->assertDatabaseHas('admin_activities', ['description' => 'Mengubah pemilihan "Ketua OSIS".']);
        // Changing only the closing time is recorded by the extend and close actions, not here.
        $this->assertDatabaseMissing('admin_activities', ['description' => 'Mengubah pemilihan "Ketua OSIS" dan jadwal.']);
        $this->assertSame(1, AdminActivity::where('description', 'Mengubah pemilihan "Ketua OSIS".')->count());

        $candidate->delete();

        $this->assertDatabaseHas('admin_activities', ['description' => 'Menghapus kandidat "Andi Pratama".']);
    }

    public function test_changes_made_without_a_signed_in_user_are_not_recorded(): void
    {
        Candidate::create(['name' => 'Tanpa admin']);

        $this->assertDatabaseMissing('admin_activities', ['description' => 'Menambahkan kandidat "Tanpa admin".']);
    }

    public function test_a_candidate_import_writes_one_summary_entry_instead_of_one_per_row(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kandidat');
        file_put_contents($path, "nama\nAndi\nBunga\nCakra\n");
        $this->actingAs($this->superAdmin());

        try {
            app(CandidateCsvImport::class)->import($path);
        } finally {
            unlink($path);
        }

        $this->assertSame(0, AdminActivity::where('description', 'like', 'Menambahkan kandidat%')->count());
        $this->assertSame(3, Candidate::count());
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
