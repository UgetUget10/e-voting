<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminActivities\Pages\ListAdminActivities;
use App\Filament\Resources\Ballots\Pages\ListBallots;
use App\Filament\Widgets\NotVotedVoters;
use App\Models\AdminActivity;
use App\Models\Ballot;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Services\FieldCsvImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminWaveThreeTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        return User::factory()->create(['is_admin' => true])->assignRole($role);
    }

    public function test_field_import_dry_run_saves_nothing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "bidang\nKeuangan\n\nHumas\n");

        $result = app(FieldCsvImport::class)->import($path, dryRun: true);
        unlink($path);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, Field::count());
    }

    public function test_not_voted_widget_counts_waiting_voters(): void
    {
        $done = User::factory()->create();
        User::factory()->create();
        Ballot::create(['user_id' => $done->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(NotVotedVoters::class)
            ->assertSeeHtml('<strong>1</strong> dari 2')
            ->assertSee('Lihat dan kirim pengingat');
    }

    public function test_ballots_can_be_filtered_by_hour(): void
    {
        $this->travelTo(now('Asia/Jakarta')->setTime(14, 30));
        $inHour = Ballot::create(['user_id' => User::factory()->create()->id]);
        $this->travelTo(now('Asia/Jakarta')->setTime(9, 15));
        $other = Ballot::create(['user_id' => User::factory()->create()->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->filterTable('hour', 14)
            ->assertCanSeeTableRecords([$inHour])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_activity_log_filters_by_action_type(): void
    {
        $added = AdminActivity::create(['description' => 'Menambahkan kandidat "A".', 'category' => 'tambah']);
        $deleted = AdminActivity::create(['description' => 'Menghapus kandidat "B".', 'category' => 'hapus']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListAdminActivities::class)
            ->filterTable('category', 'hapus')
            ->assertCanSeeTableRecords([$deleted])
            ->assertCanNotSeeTableRecords([$added]);
    }
}
