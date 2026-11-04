<?php

namespace Tests\Feature;

use App\Filament\Resources\Elections\ElectionResource;
use App\Filament\Resources\Elections\Pages\CreateElection;
use App\Filament\Resources\Elections\Pages\EditElection;
use App\Filament\Resources\Elections\Pages\ListElections;
use App\Filament\Resources\Elections\Pages\ViewElection;
use App\Filament\Resources\Elections\Schemas\ElectionForm;
use App\Filament\Widgets\ElectionStats;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ElectionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_count_elections_by_status_and_name_the_active_one(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        Election::create(['name' => 'Akan datang', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
        Election::create(['name' => 'Lampau', 'opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ElectionStats::class)
            ->assertSee('Lampau')
            ->assertSee('Sedang dibuka')
            ->assertSee('Belum dibuka')
            ->assertSee('Ditutup');
    }

    public function test_status_filter_narrows_the_list_to_one_status(): void
    {
        $open = Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        $upcoming = Election::create(['name' => 'Akan datang', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
        $closed = Election::create(['name' => 'Lampau', 'opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->filterTable('status', 'upcoming')
            ->assertCanSeeTableRecords([$upcoming])
            ->assertCanNotSeeTableRecords([$open, $closed])
            ->filterTable('status', 'closed')
            ->assertCanSeeTableRecords([$closed])
            ->assertCanNotSeeTableRecords([$open, $upcoming])
            ->filterTable('status', 'open')
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$upcoming, $closed]);
    }

    public function test_list_marks_the_election_voters_currently_use(): void
    {
        Election::create(['name' => 'Lama', 'opens_at' => now()->subDays(5), 'closes_at' => now()->subDays(4)]);
        Election::create(['name' => 'Terbaru', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertSee('Pemilihan aktif')
            ->assertSeeHtml('admin-election-active');
    }

    public function test_election_dates_are_shown_in_indonesian_wall_clock_time(): void
    {
        Election::create([
            'name' => 'Jadwal',
            'opens_at' => Carbon::create(2026, 10, 5, 10, 0, 0, 'Asia/Jakarta')->utc(),
            'closes_at' => Carbon::create(2026, 10, 6, 15, 30, 0, 'Asia/Jakarta')->utc(),
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertSee('5 Okt 2026, 10:00')
            ->assertSee('6 Okt 2026, 15:30');
    }

    public function test_close_now_ends_an_open_election(): void
    {
        $election = Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertTableActionHidden('openNow', $election)
            ->callTableAction('closeNow', $election);

        $this->assertTrue($election->fresh()->hasClosed());
    }

    public function test_open_now_starts_an_upcoming_election(): void
    {
        $election = Election::create(['name' => 'Akan datang', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertTableActionHidden('closeNow', $election)
            ->callTableAction('openNow', $election);

        $this->assertTrue($election->fresh()->isOpen());
    }

    public function test_closed_election_offers_neither_open_nor_close_now(): void
    {
        $election = Election::create(['name' => 'Lampau', 'opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertTableActionHidden('openNow', $election)
            ->assertTableActionHidden('closeNow', $election);
    }

    public function test_extend_actions_push_back_the_closing_time(): void
    {
        $closesAt = now()->addHour()->startOfSecond();
        $election = Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => $closesAt]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->callTableAction('extend30', $election)
            ->callTableAction('extend60', $election);

        $this->assertEquals($closesAt->copy()->addMinutes(90)->timestamp, $election->fresh()->closes_at->timestamp);
    }

    public function test_extend_actions_only_show_for_open_elections_with_a_closing_time(): void
    {
        $upcoming = Election::create(['name' => 'Akan datang', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
        $untimed = Election::create(['name' => 'Tanpa batas', 'opens_at' => now()->subDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertTableActionHidden('extend30', $upcoming)
            ->assertTableActionHidden('extend60', $untimed);
    }

    public function test_duplicate_creates_a_scheduled_copy_that_cannot_open_immediately(): void
    {
        $election = Election::create(['name' => 'OSIS 2026', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDays(2)->addHours(6)]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->callTableAction('duplicate', $election);

        $copy = Election::where('name', 'OSIS 2026 (salinan)')->firstOrFail();
        $this->assertTrue($copy->opens_at->isFuture());
        $this->assertSame(360, (int) $copy->opens_at->diffInMinutes($copy->closes_at));
        $this->assertFalse($copy->isOpen());
        $this->assertTrue($copy->is(Election::current()));
    }

    public function test_duplicate_picks_a_free_name_and_is_hidden_once_ballots_exist(): void
    {
        $election = Election::create(['name' => 'OSIS 2026', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);
        Election::create(['name' => 'OSIS 2026 (salinan)', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        $page = Livewire::actingAs($this->superAdmin())->test(ListElections::class)->callTableAction('duplicate', $election);

        $this->assertDatabaseHas('elections', ['name' => 'OSIS 2026 (salinan 2)']);

        Ballot::create(['user_id' => User::factory()->create()->id]);

        $page->assertTableActionHidden('duplicate', $election);
    }

    public function test_open_now_warns_when_there_are_no_fields_or_candidates(): void
    {
        $election = Election::create(['name' => 'Akan datang', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertTableActionExists('openNow', fn ($action): bool => str_contains((string) $action->getModalDescription(), 'Peringatan: belum ada bidang'), $election);
    }

    public function test_editing_an_older_election_explains_it_is_not_the_active_one(): void
    {
        $older = Election::create(['name' => 'Lama', 'opens_at' => now()->subDays(9), 'closes_at' => now()->subDays(8)]);
        $current = Election::create(['name' => 'Baru', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        $admin = $this->superAdmin();

        Livewire::actingAs($admin)
            ->test(EditElection::class, ['record' => $older->getKey()])
            ->assertSee('Pemilihan ini bukan yang aktif');

        Livewire::actingAs($admin)
            ->test(EditElection::class, ['record' => $current->getKey()])
            ->assertDontSee('Pemilihan ini bukan yang aktif');
    }

    public function test_election_with_ballots_cannot_be_deleted(): void
    {
        $election = Election::create(['name' => 'Sudah ramai', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
        Ballot::create(['user_id' => User::factory()->create()->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditElection::class, ['record' => $election->getKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_new_election_cannot_be_created_while_ballots_exist(): void
    {
        $this->actingAs($this->superAdmin());

        $this->assertTrue(ElectionResource::canCreate());

        Ballot::create(['user_id' => User::factory()->create()->id]);

        $this->assertFalse(ElectionResource::canCreate());
    }

    public function test_election_without_ballots_can_be_deleted(): void
    {
        $election = Election::create(['name' => 'Salah input', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditElection::class, ['record' => $election->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($election);
    }

    public function test_election_cannot_close_before_it_opens(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(CreateElection::class)
            ->fillForm([
                'name' => 'Terbalik',
                'opens_at' => now()->addDays(2),
                'closes_at' => now()->addDay(),
            ])
            ->call('create')
            ->assertHasFormErrors(['closes_at']);

        $this->assertDatabaseCount('elections', 0);
    }

    public function test_election_name_must_be_unique(): void
    {
        Election::create(['name' => 'Ketua OSIS', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateElection::class)
            ->fillForm(['name' => 'Ketua OSIS', 'opens_at' => now(), 'closes_at' => now()->addDay()])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertDatabaseCount('elections', 1);
    }

    public function test_status_column_shows_how_long_until_the_election_closes(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(2)]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertSee('Ditutup')
            ->assertSee('dari sekarang');
    }

    public function test_voting_window_shows_how_much_of_the_time_has_passed(): void
    {
        Carbon::setTestNow(now());
        Election::create(['name' => 'Setengah jalan', 'opens_at' => now()->subHours(2), 'closes_at' => now()->addHours(2)]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertSee('50%');

        Carbon::setTestNow();
    }

    public function test_voting_window_is_unlimited_when_a_date_is_missing(): void
    {
        Election::create(['name' => 'Tanpa jadwal']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertSee('Tidak dibatasi');
    }

    public function test_export_results_is_offered_only_for_the_closed_active_election(): void
    {
        $closed = Election::create(['name' => 'Selesai', 'opens_at' => now()->subDays(2), 'closes_at' => now()->subDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertTableActionVisible('exportResults', $closed);

        Election::create(['name' => 'Baru', 'opens_at' => now()->subHour(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertTableActionHidden('exportResults', $closed);
    }

    public function test_creating_an_election_that_overlaps_another_warns_the_admin(): void
    {
        Election::create(['name' => 'Pertama', 'opens_at' => now()->subHour(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateElection::class)
            ->fillForm([
                'name' => 'Kedua',
                'opens_at' => now()->addHours(2),
                'closes_at' => now()->addDays(2),
            ])
            ->call('create')
            ->assertNotified('Jadwal bertabrakan dengan pemilihan lain.');
    }

    public function test_creating_an_election_after_another_one_closes_gives_no_warning(): void
    {
        Election::create(['name' => 'Pertama', 'opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateElection::class)
            ->fillForm([
                'name' => 'Kedua',
                'opens_at' => now()->addHour(),
                'closes_at' => now()->addDays(2),
            ])
            ->call('create')
            ->assertNotNotified('Jadwal bertabrakan dengan pemilihan lain.');
    }

    public function test_quick_preset_fills_the_schedule_in_wib_for_eight_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 5, 9, 30, 0, 'Asia/Jakarta'));

        Livewire::actingAs($this->superAdmin())
            ->test(CreateElection::class)
            ->callAction(TestAction::make('presetNow')->schemaComponent('schedulePresets'))
            ->fillForm(['name' => 'Cepat'])
            ->call('create')
            ->assertHasNoFormErrors();

        $election = Election::where('name', 'Cepat')->firstOrFail();

        $this->assertSame('2026-10-05 02:30:00', $election->opens_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-05 10:30:00', $election->closes_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_duration_label_describes_the_window_or_asks_for_both_dates(): void
    {
        $this->assertSame(
            'Berlangsung 8 jam.',
            ElectionForm::durationLabel('2026-10-05 08:00:00', '2026-10-05 16:00:00'),
        );
        $this->assertSame(
            'Berlangsung 1 jam 30 menit.',
            ElectionForm::durationLabel('2026-10-05 08:00:00', '2026-10-05 09:30:00'),
        );
        $this->assertSame(
            'Jadwal tutup harus setelah jadwal buka.',
            ElectionForm::durationLabel('2026-10-05 08:00:00', '2026-10-05 07:00:00'),
        );
        $this->assertSame(
            'Isi kedua jadwal untuk melihat lama voting.',
            ElectionForm::durationLabel('2026-10-05 08:00:00', null),
        );
    }

    public function test_change_schedule_moves_both_times_without_the_full_form(): void
    {
        $election = Election::create(['name' => 'Geser', 'opens_at' => now()->addDay(), 'closes_at' => now()->addDays(2)]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->callTableAction('changeSchedule', $election, data: [
                'opens_at' => '2026-11-10 08:00:00',
                'closes_at' => '2026-11-10 17:00:00',
            ]);

        $election->refresh();

        $this->assertSame('2026-11-10 01:00:00', $election->opens_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 10:00:00', $election->closes_at->format('Y-m-d H:i:s'));
    }

    public function test_change_schedule_is_hidden_for_archived_elections(): void
    {
        $election = Election::create([
            'name' => 'Arsip',
            'opens_at' => now()->subDays(3),
            'closes_at' => now()->subDay(),
            'archived_at' => now(),
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->filterTable('archived', true)
            ->assertTableActionHidden('changeSchedule', $election);
    }

    public function test_custom_extension_adds_the_minutes_the_admin_types(): void
    {
        $closesAt = now()->addHour()->startOfSecond();
        $election = Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => $closesAt]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->callTableAction('extendCustom', $election, data: ['minutes' => 45]);

        $this->assertEquals($closesAt->copy()->addMinutes(45)->timestamp, $election->fresh()->closes_at->timestamp);
    }

    public function test_custom_extension_rejects_a_value_below_one_minute(): void
    {
        $election = Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addHour()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->callTableAction('extendCustom', $election, data: ['minutes' => 0])
            ->assertHasTableActionErrors(['minutes']);
    }

    public function test_schedule_filter_finds_elections_opening_this_week_and_incomplete_schedules(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 10, 7, 12, 0, 0, 'Asia/Jakarta'));

        $startOfWeek = Carbon::now('Asia/Jakarta')->startOfWeek();
        $thisWeek = Election::create([
            'name' => 'Minggu ini',
            'opens_at' => $startOfWeek->copy()->addDays(2)->setTime(8, 0),
            'closes_at' => $startOfWeek->copy()->addDays(2)->setTime(17, 0),
        ]);
        $nextWeek = Election::create([
            'name' => 'Minggu depan',
            'opens_at' => $startOfWeek->copy()->addWeek()->setTime(8, 0),
            'closes_at' => $startOfWeek->copy()->addWeek()->setTime(17, 0),
        ]);
        $incomplete = Election::create(['name' => 'Tanpa jadwal']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->filterTable('schedule', 'this_week')
            ->assertCanSeeTableRecords([$thisWeek])
            ->assertCanNotSeeTableRecords([$nextWeek, $incomplete])
            ->filterTable('schedule', 'incomplete')
            ->assertCanSeeTableRecords([$incomplete])
            ->assertCanNotSeeTableRecords([$thisWeek, $nextWeek]);

        Carbon::setTestNow();
    }

    public function test_empty_list_invites_the_admin_to_create_an_election(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertSee('Belum ada pemilihan')
            ->assertSee('Buat pemilihan pertama');
    }

    public function test_copy_voter_link_is_offered_on_the_list(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertActionExists('copyVoterLink');
    }

    public function test_closing_soon_is_flagged_in_the_status_column(): void
    {
        Election::create(['name' => 'Hampir tutup', 'opens_at' => now()->subHour(), 'closes_at' => now()->addMinutes(20)]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertSee('Segera ditutup');
    }

    public function test_recently_changed_rows_are_marked_so_the_change_is_noticed(): void
    {
        Election::create(['name' => 'Baru saja', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListElections::class)
            ->assertSeeHtml('admin-row-enter');
    }

    public function test_election_detail_shows_its_schedule_and_archived_results(): void
    {
        $election = Election::create([
            'name' => 'Lengkap',
            'opens_at' => Carbon::create(2026, 10, 5, 10, 0, 0, 'Asia/Jakarta')->utc(),
            'closes_at' => Carbon::create(2026, 10, 6, 15, 30, 0, 'Asia/Jakarta')->utc(),
            'archived_at' => now(),
            'results_snapshot' => ['voters' => 10, 'ballots' => 7, 'turnout' => 70, 'fields' => []],
        ]);

        Livewire::actingAs($this->superAdmin())
            ->test(ViewElection::class, ['record' => $election->getKey()])
            ->assertSee('Lengkap')
            ->assertSee('5 Okt 2026, 10:00')
            ->assertSee('Hasil akhir')
            ->assertSee('70%');
    }

    public function test_stats_count_up_from_zero_with_the_real_figure_in_the_markup(): void
    {
        Election::create(['name' => 'Berjalan', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ElectionStats::class)
            ->assertSeeHtml('data-count-to="1"');
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
