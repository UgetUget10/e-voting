<?php

namespace Tests\Feature;

use App\Filament\Resources\Ballots\BallotResource;
use App\Filament\Resources\Ballots\Pages\ListBallots;
use App\Filament\Widgets\BallotIntegrity;
use App\Filament\Widgets\BallotParticipation;
use App\Filament\Widgets\BallotsByHour;
use App\Filament\Widgets\BallotStats;
use App\Models\Ballot;
use App\Models\BallotNote;
use App\Models\Candidate;
use App\Models\Field;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use App\Services\BallotCsvExport;
use App\Services\BallotReset;
use App\Services\BallotTally;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBallotsTest extends TestCase
{
    use RefreshDatabase;

    private Field $presiden;

    private Field $bendahara;

    private Candidate $andi;

    private Candidate $bunga;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $this->bendahara = Field::create(['name' => 'Bendahara', 'position' => 2]);
        $this->andi = Candidate::create(['name' => 'Andi']);
        $this->bunga = Candidate::create(['name' => 'Bunga']);

        $this->ballot('Pemilih Satu', [$this->presiden->id => $this->andi->id]);
        $this->ballot('Pemilih Dua', [$this->presiden->id => $this->bunga->id, $this->bendahara->id => $this->andi->id]);
    }

    public function test_ballot_time_is_shown_in_jakarta_time_with_indonesian_month(): void
    {
        $this->ballot('Pemilih Waktu', [$this->presiden->id => $this->andi->id], Carbon::create(2026, 10, 5, 10, 0, 0, 'Asia/Jakarta')->utc());

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSee('5 Okt 2026, 10:00');
    }

    public function test_field_filter_keeps_ballots_that_picked_in_that_field(): void
    {
        $bendaharaBallot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Dua'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->filterTable('field', $this->bendahara->id)
            ->assertCanSeeTableRecords([$bendaharaBallot])
            ->assertCountTableRecords(1);
    }

    public function test_candidate_filter_keeps_ballots_that_picked_that_candidate(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->filterTable('candidate', $this->bunga->id)
            ->assertCountTableRecords(1)
            ->assertSee($this->receiptOf('Pemilih Dua'));
    }

    public function test_picks_are_listed_in_voting_order(): void
    {
        $this->presiden->update(['position' => 2]);
        $this->bendahara->update(['position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeInOrder(['Bendahara: Andi', 'Presiden: Bunga']);
    }

    public function test_stats_count_ballots_sent_today_in_jakarta(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(BallotStats::class)
            ->assertSee('Suara hari ini');
    }

    public function test_date_filter_uses_jakarta_days_inclusively(): void
    {
        $lateOnFifth = $this->ballot('Kirim Malam', [$this->presiden->id => $this->andi->id], Carbon::create(2026, 10, 5, 23, 30, 0, 'Asia/Jakarta')->utc());
        $earlyOnSixth = $this->ballot('Kirim Pagi', [$this->presiden->id => $this->andi->id], Carbon::create(2026, 10, 6, 0, 30, 0, 'Asia/Jakarta')->utc());

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->filterTable('sent_between', ['from' => '2026-10-05', 'until' => '2026-10-05'])
            ->assertCanSeeTableRecords([$lateOnFifth])
            ->assertCanNotSeeTableRecords([$earlyOnSixth])
            ->assertCountTableRecords(1);
    }

    public function test_export_contains_only_ballots_matching_the_filtered_query(): void
    {
        $query = Ballot::query()->whereHas('votes', fn ($query) => $query->where('field_id', $this->bendahara->id));

        ob_start();
        app(BallotCsvExport::class)->stream($query)->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString($this->receiptOf('Pemilih Dua'), $csv);
        $this->assertStringNotContainsString($this->receiptOf('Pemilih Satu'), $csv);
        $this->assertStringNotContainsString('Pemilih Dua', $csv);
    }

    public function test_export_action_runs_with_the_active_table_filters(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->filterTable('field', $this->bendahara->id)
            ->callAction('export')
            ->assertHasNoActionErrors();
    }

    public function test_integrity_check_reports_clean_data(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(BallotIntegrity::class)
            ->assertSee('Bersih')
            ->assertSee('Semua surat suara berisi pilihan');
    }

    public function test_integrity_check_flags_a_ballot_without_picks(): void
    {
        Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(BallotIntegrity::class)
            ->assertSee('Perlu diperiksa')
            ->assertSee('1 masalah ditemukan');
    }

    public function test_hourly_chart_counts_todays_ballots_by_jakarta_hour(): void
    {
        $widget = Livewire::actingAs($this->superAdmin())->test(BallotsByHour::class)->assertSee('Suara masuk per jam');

        $data = (new \ReflectionMethod(BallotsByHour::class, 'getData'))->invoke($widget->instance());
        $hour = (int) now('Asia/Jakarta')->format('G');

        $this->assertCount(24, $data['labels']);
        $this->assertSame(2, $data['datasets'][0]['data'][$hour]);
        $this->assertSame(2, array_sum($data['datasets'][0]['data']));
    }

    public function test_page_links_to_voters_who_have_not_voted_and_shows_export_size(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertActionExists('notVoted')
            ->assertSee('Ekspor CSV (2)')
            ->filterTable('candidate', $this->bunga->id)
            ->assertSee('Ekspor CSV (1)');
    }

    public function test_super_admin_sees_the_voter_name_and_email_next_to_each_receipt_code(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSee($this->receiptOf('Pemilih Satu'))
            ->assertSee('Pemilih Satu')
            ->assertSee('Pemilih Dua');
    }

    public function test_other_admins_identify_ballots_by_receipt_code_and_never_see_the_voter(): void
    {
        Livewire::actingAs($this->plainAdmin())
            ->test(ListBallots::class)
            ->assertSee($this->receiptOf('Pemilih Satu'))
            ->assertDontSee('Pemilih Satu')
            ->assertDontSee('Pemilih Dua');
    }

    public function test_super_admin_export_includes_voter_name_and_email(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Satu'))->firstOrFail();

        ob_start();
        app(BallotCsvExport::class)->stream(Ballot::query(), includeVoters: true)->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Nama pemilih', $csv);
        $this->assertStringContainsString('Pemilih Satu', $csv);
        $this->assertStringContainsString($ballot->user->email, $csv);
    }

    public function test_export_loads_picks_in_bulk_instead_of_one_query_per_ballot(): void
    {
        DB::enableQueryLog();

        $queriesFor = function (): int {
            DB::flushQueryLog();

            ob_start();
            app(BallotCsvExport::class)->stream(Ballot::query())->sendContent();
            ob_end_clean();

            return count(DB::getQueryLog());
        };

        $queriesWithTwoBallots = $queriesFor();

        foreach (range(1, 5) as $number) {
            $this->ballot('Pemilih Tambahan '.$number, [$this->presiden->id => $this->andi->id, $this->bendahara->id => $this->bunga->id]);
        }

        $this->assertSame($queriesWithTwoBallots, $queriesFor());
    }

    public function test_export_filename_is_stamped_with_the_jakarta_download_time(): void
    {
        $response = app(BallotCsvExport::class)->stream(Ballot::query());

        $this->assertMatchesRegularExpression(
            '/^attachment; filename=suara-pemilih-\d{8}-\d{6}\.csv$/',
            $response->headers->get('Content-Disposition'),
        );
    }

    public function test_export_without_voter_columns_leaves_names_out(): void
    {
        ob_start();
        app(BallotCsvExport::class)->stream(Ballot::query())->sendContent();
        $csv = ob_get_clean();

        $this->assertStringNotContainsString('Pemilih Satu', $csv);
        $this->assertStringNotContainsString('Nama pemilih', $csv);
    }

    public function test_recent_ballots_are_marked_as_new(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSee('Baru');
    }

    public function test_detail_slide_over_lists_the_picks_of_one_ballot(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Dua'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->mountTableAction('view', $ballot)
            ->assertMountedActionModalSee(['Detail suara', 'Pilihan per bidang', 'Presiden', 'Bunga', 'Bendahara', 'Andi']);
    }

    public function test_recent_filter_hides_ballots_sent_more_than_five_minutes_ago(): void
    {
        $older = $this->ballot('Pemilih Lama', [$this->presiden->id => $this->andi->id], now()->subHour());

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->filterTable('recent', true)
            ->assertCanNotSeeTableRecords([$older])
            ->assertCountTableRecords(2);
    }

    public function test_issue_filter_keeps_only_ballots_without_picks(): void
    {
        $empty = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->filterTable('issue', 'without_picks')
            ->assertCanSeeTableRecords([$empty])
            ->assertCountTableRecords(1);
    }

    public function test_integrity_cards_link_to_the_matching_issue_filter(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(BallotIntegrity::class)
            ->assertSeeHtml(BallotResource::getUrl('index', ['tableFilters' => ['issue' => ['value' => 'without_picks']]]));
    }

    public function test_integrity_counts_follow_the_ballots_without_waiting_for_a_cache_window(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(BallotIntegrity::class)
            ->assertSee('Semua surat suara berisi pilihan');

        Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(BallotIntegrity::class)
            ->assertSee('1 masalah ditemukan');
    }

    public function test_the_list_keeps_its_tab_counts_in_the_cache_between_refreshes(): void
    {
        Livewire::actingAs($this->superAdmin())->test(ListBallots::class);

        $this->assertTrue(Cache::has('ballots.tab-counts.'.Ballot::liveFingerprint().'.'.now()->format('YmdHi')));
    }

    public function test_integrity_card_for_ballots_without_a_voter_links_to_its_filter(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(BallotIntegrity::class)
            ->assertSeeHtml(BallotResource::getUrl('index', ['tableFilters' => ['issue' => ['value' => 'without_voter']]]));
    }

    public function test_tally_summarises_the_picks_of_the_filtered_ballots(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeText('2 suara sesuai filter')
            ->filterTable('candidate', $this->bunga->id)
            ->assertSeeText('1 suara sesuai filter');
    }

    public function test_list_checks_for_new_ballots_every_five_seconds(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeHtml('wire:poll.5s="checkForChanges"');
    }

    public function test_page_counts_ballots_sent_after_it_was_opened_as_new(): void
    {
        $component = Livewire::actingAs($this->superAdmin())->test(ListBallots::class)->assertDontSeeHtml('masuk sejak halaman dibuka');

        $this->travel(1)->minute();
        $this->ballot('Pemilih Baru', [$this->presiden->id => $this->andi->id]);

        $component->call('$refresh')->assertSeeHtml('<strong>1 suara baru</strong>');
    }

    public function test_receipt_lookup_narrows_the_list_to_that_ballot(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Satu'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callAction('findReceipt', data: ['receiptCode' => strtolower(' '.$ballot->receipt_code.' ')])
            ->assertHasNoActionErrors()
            ->assertSet('tableSearch', $ballot->receipt_code)
            ->assertNotified('Suara ditemukan.');
    }

    public function test_receipt_lookup_reports_an_unknown_code(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callAction('findReceipt', data: ['receiptCode' => 'TIDAKADA123'])
            ->assertNotified('Kode tidak ditemukan.');
    }

    public function test_opening_voter_details_is_recorded_in_the_activity_log(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Satu'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->mountTableAction('view', $ballot);

        $this->assertDatabaseHas('admin_activities', [
            'description' => 'Membuka identitas pemilih pada surat suara '.$ballot->receipt_code.'.',
        ]);
    }

    public function test_exporting_voter_identities_is_recorded_in_the_activity_log(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callAction('export');

        $this->assertDatabaseHas('admin_activities', [
            'description' => 'Mengekspor CSV dengan identitas pemilih (2 suara).',
        ]);
    }

    public function test_exports_without_voter_identities_are_not_logged_as_voter_access(): void
    {
        Livewire::actingAs($this->plainAdmin())
            ->test(ListBallots::class)
            ->callAction('export');

        $this->assertDatabaseMissing('admin_activities', [
            'description' => 'Mengekspor CSV dengan identitas pemilih (2 suara).',
        ]);
    }

    public function test_participation_shows_how_many_registered_voters_picked_in_each_field(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(BallotParticipation::class)
            ->assertSee('Partisipasi per bidang')
            ->assertSee('2 dari 2')
            ->assertSee('1 dari 2');
    }

    public function test_tabs_narrow_the_list_and_show_how_many_ballots_each_holds(): void
    {
        $empty = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
        $yesterday = $this->ballot('Pemilih Kemarin', [$this->presiden->id => $this->andi->id], now('Asia/Jakarta')->subDay()->setTime(12, 0)->utc());

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeText('Bermasalah')
            ->set('activeTab', 'problems')
            ->assertCanSeeTableRecords([$empty])
            ->assertCanNotSeeTableRecords([$yesterday])
            ->assertCountTableRecords(1)
            ->set('activeTab', 'today')
            ->assertCanNotSeeTableRecords([$yesterday])
            ->assertCountTableRecords(3)
            ->set('activeTab', 'recent')
            ->assertCountTableRecords(3);
    }

    public function test_staff_can_add_an_internal_note_that_is_logged_and_counted(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Satu'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callTableAction('note', $ballot, data: ['body' => 'Perlu dicek ulang saat rekap.'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Catatan disimpan.');

        $this->assertDatabaseHas('ballot_notes', [
            'ballot_id' => $ballot->id,
            'body' => 'Perlu dicek ulang saat rekap.',
        ]);
        $this->assertDatabaseHas('admin_activities', [
            'description' => 'Menambahkan catatan pada surat suara '.$ballot->receipt_code.'.',
        ]);
    }

    public function test_internal_note_must_have_text(): void
    {
        $ballot = Ballot::query()->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callTableAction('note', $ballot, data: ['body' => ''])
            ->assertHasTableActionErrors(['body' => 'required']);

        $this->assertDatabaseCount('ballot_notes', 0);
    }

    public function test_notes_appear_in_the_ballot_detail_and_the_notes_column(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Dua'))->firstOrFail();
        BallotNote::factory()->for($ballot)->create(['body' => 'Saksi meminta pengecekan.']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->mountTableAction('view', $ballot)
            ->assertMountedActionModalSee(['Catatan internal', 'Saksi meminta pengecekan.']);
    }

    public function test_notes_are_removed_together_with_their_ballot(): void
    {
        $ballot = Ballot::query()->firstOrFail();
        BallotNote::factory()->for($ballot)->create();

        app(BallotReset::class)->reset();

        $this->assertDatabaseCount('ballot_notes', 0);
    }

    public function test_selected_ballots_can_be_exported_and_the_export_is_logged_for_super_admins(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Satu'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callTableBulkAction('exportSelected', [$ballot])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('admin_activities', [
            'description' => 'Mengekspor CSV dengan identitas pemilih (1 suara dipilih).',
        ]);
    }

    public function test_live_updates_can_be_paused_and_resumed(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSet('liveUpdates', true)
            ->assertSeeText('Memantau suara masuk')
            ->callAction('toggleLive')
            ->assertSet('liveUpdates', false)
            ->assertSeeText('Pembaruan dijeda')
            ->callAction('toggleLive')
            ->assertSet('liveUpdates', true);
    }

    public function test_a_burst_of_ballots_in_one_hour_raises_a_spike_banner(): void
    {
        foreach (range(1, 6) as $number) {
            $this->ballot('Pemilih Lonjakan '.$number, [$this->presiden->id => $this->andi->id], now());
        }

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSee('Lonjakan suara terdeteksi')
            ->assertSee('8 suara');
    }

    public function test_quiet_hours_raise_no_spike_banner(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertDontSee('Lonjakan suara terdeteksi');
    }

    public function test_recently_sent_ballots_are_highlighted_in_the_table(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeHtml('admin-ballot-row-new');
    }

    public function test_the_page_has_a_keyboard_shortcut_script_and_the_overview_panel(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeHtml('adminBallotShortcuts')
            ->assertSeeHtml('admin-overview')
            ->assertSeeText('Hasil sesuai filter');
    }

    public function test_summary_pdf_is_offered_and_leaves_out_receipts_and_voter_names(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertActionExists('printSummary')
            ->callAction('printSummary')
            ->assertHasNoActionErrors();

        $html = view('admin.ringkasan-suara', [
            'summary' => app(BallotTally::class)->forBallots(Ballot::query()),
            'election' => null,
            'printedAt' => now('Asia/Jakarta'),
        ])->render();

        $this->assertStringContainsString('Ringkasan suara sesuai filter', $html);
        $this->assertStringContainsString('Andi', $html);
        $this->assertStringNotContainsString($this->receiptOf('Pemilih Satu'), $html);
        $this->assertStringNotContainsString('Pemilih Satu', $html);
    }

    public function test_overview_cards_show_the_counts_over_every_ballot(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeText('Suara masuk')
            ->assertSeeText('Perlu ditinjau')
            ->assertSeeHtml('data-count-to="2"')
            ->assertSeeHtml('data-count-to="0"');
    }

    public function test_overview_card_is_wired_to_open_its_tab(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeHtml("wire:click=\"\$set('activeTab', 'review')\"")
            ->assertSeeHtml("wire:click=\"\$set('activeTab', 'problems')\"");
    }

    public function test_staff_can_flag_a_ballot_for_review_and_the_flag_is_logged(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Satu'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callTableAction('toggleReview', $ballot)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Ditandai untuk ditinjau.');

        $this->assertTrue($ballot->fresh()->needs_review);
        $this->assertDatabaseHas('admin_activities', [
            'description' => 'Menandai surat suara '.$ballot->receipt_code.' untuk ditinjau.',
        ]);
    }

    public function test_flag_can_be_removed_again(): void
    {
        $ballot = Ballot::query()->firstOrFail();
        $ballot->update(['needs_review' => true]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callTableAction('toggleReview', $ballot)
            ->assertNotified('Tanda tinjauan dihapus.');

        $this->assertFalse($ballot->fresh()->needs_review);
    }

    public function test_review_tab_lists_only_flagged_ballots(): void
    {
        $flagged = Ballot::factory()->needsReview()->create();
        $flagged->votes()->create(['field_id' => $this->presiden->id, 'candidate_id' => $this->andi->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->set('activeTab', 'review')
            ->assertCanSeeTableRecords([$flagged])
            ->assertCountTableRecords(1);
    }

    public function test_selected_ballots_can_be_flagged_and_unflagged_together(): void
    {
        $first = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Satu'))->firstOrFail();
        $second = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Dua'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callTableBulkAction('flagSelected', [$first, $second])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(2, Ballot::query()->where('needs_review', true)->count());

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callTableBulkAction('unflagSelected', [$first]);

        $this->assertFalse($first->fresh()->needs_review);
        $this->assertTrue($second->fresh()->needs_review);
    }

    public function test_one_note_can_be_added_to_every_selected_ballot(): void
    {
        $first = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Satu'))->firstOrFail();
        $second = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Dua'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callTableBulkAction('noteSelected', [$first, $second], data: ['body' => 'Dicek bersama saksi.'])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified('Catatan disimpan ke 2 suara.');

        $this->assertDatabaseCount('ballot_notes', 2);
        $this->assertDatabaseHas('ballot_notes', ['ballot_id' => $first->id, 'body' => 'Dicek bersama saksi.']);
        $this->assertDatabaseHas('ballot_notes', ['ballot_id' => $second->id, 'body' => 'Dicek bersama saksi.']);
        $this->assertDatabaseHas('admin_activities', [
            'description' => 'Menambahkan catatan pada 2 surat suara dipilih.',
        ]);
    }

    public function test_bulk_note_must_have_text(): void
    {
        $ballot = Ballot::query()->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callTableBulkAction('noteSelected', [$ballot], data: ['body' => ''])
            ->assertHasTableBulkActionErrors(['body' => 'required']);

        $this->assertDatabaseCount('ballot_notes', 0);
    }

    public function test_secondary_actions_sit_under_a_more_menu(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeText('Lainnya')
            ->assertActionExists('printSummary')
            ->assertActionExists('verifyIntegrity')
            ->assertActionExists('resetBallots');
    }

    public function test_density_can_be_switched_and_is_remembered_for_the_session(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSet('compactRows', false)
            ->callAction('toggleDensity')
            ->assertSet('compactRows', true)
            ->assertSeeHtml('admin-ballot-compact')
            ->assertSeeText('Tampilan lega');
    }

    public function test_receipt_code_in_the_address_opens_the_list_on_that_ballot(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Satu'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->withQueryParams(['kode' => strtolower($ballot->receipt_code)])
            ->test(ListBallots::class)
            ->assertSet('tableSearch', $ballot->receipt_code)
            ->assertCanSeeTableRecords([$ballot])
            ->assertCountTableRecords(1);
    }

    public function test_finding_a_receipt_puts_its_code_in_the_address_bar(): void
    {
        $ballot = Ballot::query()->whereHas('user', fn ($query) => $query->where('name', 'Pemilih Dua'))->firstOrFail();

        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->callAction('findReceipt', data: ['receiptCode' => $ballot->receipt_code])
            ->assertHasNoActionErrors()
            ->assertSet('tableSearch', $ballot->receipt_code);
    }

    public function test_the_page_has_the_shortcut_help_toast_and_sound_controls(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeHtml('admin-shortcuts')
            ->assertSeeHtml('data-shortcuts-open')
            ->assertSeeHtml('data-sound-toggle')
            ->assertSeeHtml('data-new-ballots="0"')
            ->assertSeeHtml('data-live="1"');
    }

    public function test_the_tally_shows_a_loading_state_while_filters_apply(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(ListBallots::class)
            ->assertSeeHtml('wire:loading.class="is-loading"')
            ->assertSeeHtml('tableFilters,tableSearch,activeTab');
    }

    private function receiptOf(string $voterName): string
    {
        return Ballot::query()->whereHas('user', fn ($query) => $query->where('name', $voterName))->value('receipt_code');
    }

    private function ballot(string $voterName, array $picks, ?Carbon $sentAt = null): Ballot
    {
        $ballot = Ballot::create([
            'user_id' => User::factory()->create(['name' => $voterName, 'is_admin' => false])->id,
        ]);

        if ($sentAt !== null) {
            $ballot->forceFill(['created_at' => $sentAt])->save();
        }

        foreach ($picks as $fieldId => $candidateId) {
            Vote::create(['ballot_id' => $ballot->id, 'field_id' => $fieldId, 'candidate_id' => $candidateId]);
        }

        return $ballot;
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    private function plainAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'panitia', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'ViewAny:Ballot', 'guard_name' => 'web']));

        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole($role);

        return $admin;
    }
}
