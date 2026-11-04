<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminActivities\AdminActivityResource;
use App\Filament\Resources\AdminActivities\Pages\ListAdminActivities;
use App\Filament\Widgets\AdminActivitySummary;
use App\Models\AdminActivity;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminActivityClassifier;
use App\Services\AdminActivityCsvExport;
use App\Services\AdminActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class AdminActivityPageToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_description_gets_a_category_and_a_sensitivity_flag(): void
    {
        $classifier = app(AdminActivityClassifier::class);

        $this->assertSame('tambah', $classifier->category('Menambahkan kandidat "Andi".'));
        $this->assertSame('ubah', $classifier->category('Mengubah golongan 2 kandidat menjadi III/a.'));
        $this->assertSame('ubah', $classifier->category('Mengacak urutan 6 kandidat.'));
        $this->assertSame('hapus', $classifier->category('Menghapus kandidat "Bunga".'));
        $this->assertSame('ekspor', $classifier->category('Mengekspor hasil voting ke CSV.'));
        $this->assertSame('sistem', $classifier->category('Mengirim pengingat ke 4 pemilih.'));
        $this->assertSame('keamanan', $classifier->category('Membuka identitas pemilih pada surat suara AB12CD.'));

        $this->assertFalse($classifier->isSensitive('Mengubah golongan 2 kandidat menjadi III/a.'));
        $this->assertFalse($classifier->isSensitive('Menambahkan kandidat "Andi".'));
        $this->assertTrue($classifier->isSensitive('Membuka identitas pemilih pada surat suara AB12CD.'));
        $this->assertTrue($classifier->isSensitive('Mengekspor CSV dengan identitas pemilih.'));
        $this->assertTrue($classifier->isSensitive('Mereset suara, 4 surat suara dihapus.'));
        $this->assertTrue($classifier->isSensitive('Membuka voting "Ketua OSIS" sekarang.'));
        $this->assertTrue($classifier->isSensitive('Mengubah jadwal pemilihan "Ketua OSIS".'));
    }

    public function test_entries_store_their_category_sensitivity_and_jakarta_day_when_written(): void
    {
        $this->actingAs($this->superAdmin());

        app(AdminActivityLog::class)->record('Membuka identitas pemilih pada surat suara AB12CD.');

        $entry = AdminActivity::query()->latest('id')->first();

        $this->assertSame('keamanan', $entry->category);
        $this->assertTrue($entry->sensitive);
        $this->assertSame(now('Asia/Jakarta')->toDateString(), $entry->day);
    }

    public function test_an_entry_cannot_be_changed_or_removed(): void
    {
        $entry = AdminActivity::create(['description' => 'Menutup pemilihan.']);

        $this->expectException(LogicException::class);

        $entry->update(['description' => 'Diubah']);
    }

    public function test_an_entry_cannot_be_deleted(): void
    {
        $entry = AdminActivity::create(['description' => 'Menutup pemilihan.']);

        $this->expectException(LogicException::class);

        $entry->delete();
    }

    public function test_sensitive_filter_keeps_only_the_entries_that_need_review(): void
    {
        $routine = AdminActivity::create(['description' => 'Mengubah golongan 1 kandidat menjadi III/a.', 'category' => 'ubah', 'sensitive' => false]);
        $sensitive = AdminActivity::create(['description' => 'Membuka identitas pemilih pada surat suara AB12CD.', 'category' => 'keamanan', 'sensitive' => true]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListAdminActivities::class)
            ->filterTable('sensitive', true)
            ->assertCanSeeTableRecords([$sensitive])
            ->assertCanNotSeeTableRecords([$routine]);
    }

    public function test_subject_filter_shows_only_entries_about_that_kind_of_record(): void
    {
        $candidate = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $about = AdminActivity::create(['description' => 'Mengubah kandidat "Andi".', 'category' => 'ubah', 'subject_type' => Candidate::class, 'subject_id' => $candidate->getKey()]);
        $elsewhere = AdminActivity::create(['description' => 'Mengunci data pemilihan "Pemilu".', 'category' => 'keamanan', 'sensitive' => true]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListAdminActivities::class)
            ->filterTable('subject_type', Candidate::class)
            ->assertCanSeeTableRecords([$about])
            ->assertCanNotSeeTableRecords([$elsewhere]);
    }

    public function test_an_entry_about_a_candidate_links_to_that_candidate(): void
    {
        $candidate = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $entry = AdminActivity::create(['description' => 'Mengubah kandidat "Andi".', 'subject_type' => Candidate::class, 'subject_id' => $candidate->getKey()]);

        $this->assertSame(
            route('filament.admin.resources.candidates.view', ['record' => $candidate]),
            AdminActivityResource::subjectUrl($entry),
        );
    }

    public function test_an_entry_about_a_ballot_opens_the_ballot_list_with_its_receipt_code(): void
    {
        $ballot = Ballot::create(['user_id' => User::factory()->create()->id]);
        $entry = AdminActivity::create(['description' => 'Membuka identitas pemilih pada surat suara '.$ballot->receipt_code.'.', 'subject_type' => Ballot::class, 'subject_id' => $ballot->getKey()]);

        $this->assertStringContainsString('kode='.$ballot->receipt_code, AdminActivityResource::subjectUrl($entry));
    }

    public function test_an_entry_whose_record_was_removed_has_no_link(): void
    {
        $entry = AdminActivity::create(['description' => 'Menghapus kandidat "Bunga".', 'subject_type' => Candidate::class, 'subject_id' => 999]);

        $this->assertNull(AdminActivityResource::subjectUrl($entry));
    }

    public function test_summary_counts_today_sensitive_entries_and_active_admins(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        AdminActivity::create(['user_id' => $admin->id, 'description' => 'Mengubah golongan.', 'category' => 'ubah', 'sensitive' => false]);
        AdminActivity::create(['user_id' => $admin->id, 'description' => 'Mengunci data pemilihan.', 'category' => 'keamanan', 'sensitive' => true]);

        Livewire::actingAs($this->superAdmin())
            ->test(AdminActivitySummary::class, ['seenThrough' => (int) AdminActivity::query()->max('id')])
            ->assertSee('Tindakan penting')
            ->assertSeeHtml('data-count-to="1"')
            ->assertSeeHtml('data-count-to="2"')
            ->assertDontSee('aktivitas baru menunggu');
    }

    public function test_summary_announces_entries_that_arrived_after_the_page_was_opened(): void
    {
        $seen = (int) AdminActivity::query()->max('id');
        AdminActivity::create(['description' => 'Menghapus kandidat "Bunga".', 'category' => 'hapus', 'sensitive' => true]);
        AdminActivity::create(['description' => 'Menambahkan kandidat "Cahya".', 'category' => 'tambah', 'sensitive' => false]);

        Livewire::actingAs($this->superAdmin())
            ->test(AdminActivitySummary::class, ['seenThrough' => $seen])
            ->assertSee('2 aktivitas baru menunggu')
            ->assertSee('Muat yang baru');
    }

    public function test_the_page_hands_the_banner_the_newest_entry_it_has_shown(): void
    {
        $page = Livewire::actingAs($this->superAdmin())->test(ListAdminActivities::class);
        AdminActivity::create(['description' => 'Menghapus kandidat "Bunga".', 'category' => 'hapus', 'sensitive' => true]);

        $seen = $page->instance()->getWidgetData()['seenThrough'];

        Livewire::actingAs($this->superAdmin())
            ->test(AdminActivitySummary::class, ['seenThrough' => $seen])
            ->assertSee('1 aktivitas baru menunggu')
            ->assertSee('Muat yang baru');

        $page->call('refreshFeed')->assertDispatched('activity-seen');
    }

    public function test_the_banner_clears_once_the_page_has_shown_the_new_entries(): void
    {
        AdminActivity::create(['description' => 'Menghapus kandidat "Bunga".', 'category' => 'hapus', 'sensitive' => true]);

        Livewire::actingAs($this->superAdmin())
            ->test(AdminActivitySummary::class, ['seenThrough' => 0])
            ->assertSee('1 aktivitas baru menunggu')
            ->dispatch('activity-seen', seenThrough: (int) AdminActivity::query()->max('id'))
            ->assertDontSee('aktivitas baru menunggu');
    }

    public function test_refreshing_shows_the_new_entries_and_flashes_only_those_rows(): void
    {
        $old = AdminActivity::create(['description' => 'Menambahkan kandidat "Andi".', 'category' => 'tambah', 'sensitive' => false]);
        $page = Livewire::actingAs($this->superAdmin())->test(ListAdminActivities::class);
        $new = AdminActivity::create(['description' => 'Menghapus kandidat "Bunga".', 'category' => 'hapus', 'sensitive' => true]);

        $page->call('refreshFeed')
            ->assertSet('freshIds', [$new->getKey()])
            ->assertSet('seenThrough', $new->getKey())
            ->assertCanSeeTableRecords([$old, $new])
            ->assertSeeHtml('admin-row-enter');
    }

    public function test_entries_are_grouped_under_their_day(): void
    {
        AdminActivity::create(['description' => 'Menambahkan kandidat "Andi".', 'category' => 'tambah', 'sensitive' => false, 'day' => now('Asia/Jakarta')->toDateString()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListAdminActivities::class)
            ->assertSee('Hari ini');
    }

    public function test_csv_includes_category_sensitivity_subject_and_every_change(): void
    {
        $candidate = Candidate::create(['name' => 'Andi', 'position' => 1]);
        AdminActivity::create([
            'description' => 'Mengubah kandidat "Andi".',
            'category' => 'ubah',
            'sensitive' => false,
            'changes' => ['nip' => ['old' => null, 'new' => '198501012010011001'], 'golongan' => ['old' => null, 'new' => 'III/a']],
            'subject_type' => Candidate::class,
            'subject_id' => $candidate->getKey(),
        ]);

        $response = app(AdminActivityCsvExport::class)->stream(AdminActivity::query());
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Kategori', $csv);
        $this->assertStringContainsString('Perubahan', $csv);
        $this->assertStringContainsString('Ubah', $csv);
        $this->assertStringContainsString('Kandidat', $csv);
        $this->assertStringContainsString('NIP: — → 198501012010011001; Golongan: — → III/a', $csv);
    }

    public function test_change_labels_are_readable_and_a_photo_is_not_shown_by_its_path(): void
    {
        $this->assertSame('NIP', AdminActivityClassifier::fieldLabel('nip'));
        $this->assertSame('Total suara', AdminActivityClassifier::fieldLabel('total'));
        $this->assertSame('Ada foto', AdminActivityClassifier::formatChangeValue('photo_path', 'candidates/andi.jpg'));
        $this->assertSame('—', AdminActivityClassifier::formatChangeValue('golongan', null));
        $this->assertSame('AP', AdminActivityClassifier::initials('andi pratama'));
        $this->assertSame('S', AdminActivityClassifier::initials('Sistem'));
        $this->assertSame('', AdminActivityClassifier::initials(null));
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
