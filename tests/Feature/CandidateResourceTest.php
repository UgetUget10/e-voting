<?php

namespace Tests\Feature;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Filament\Resources\Candidates\Pages\ViewCandidate;
use App\Filament\Widgets\CandidateStats;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use App\Services\CandidateNameSimilarity;
use App\Services\CandidatePhotoImport;
use App\Services\ResultService;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CandidateResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_count_candidates_who_are_unpicked_or_at_the_field_limit(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $bendahara = Field::create(['name' => 'Bendahara', 'position' => 2]);
        $sarpras = Field::create(['name' => 'Sarpras', 'position' => 3]);
        $andi = Candidate::create(['name' => 'Andi']);
        Candidate::create(['name' => 'Bunga']);
        $this->castVote($presiden, $andi);
        $this->castVote($bendahara, $andi);
        $this->castVote($sarpras, $andi);

        Livewire::actingAs($this->superAdmin())
            ->test(CandidateStats::class)
            ->assertSee('Jumlah kandidat')
            ->assertSee('Belum dipilih')
            ->assertSee('Mencapai batas')
            ->assertSee('Sudah dipilih di 2 bidang');
    }

    public function test_new_candidate_is_added_to_the_end_of_the_list(): void
    {
        Candidate::create(['name' => 'Andi', 'position' => 4]);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateCandidate::class)
            ->fillForm(['name' => 'Bunga'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(5, Candidate::where('name', 'Bunga')->value('position'));
    }

    public function test_candidate_name_must_be_unique(): void
    {
        Candidate::create(['name' => 'Andi']);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateCandidate::class)
            ->fillForm(['name' => 'Andi'])
            ->call('create')
            ->assertHasFormErrors(['name']);

        $this->assertDatabaseCount('candidates', 1);
    }

    public function test_dragging_rows_changes_the_order_voters_and_results_see(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $bunga = Candidate::create(['name' => 'Bunga', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->call('reorderTable', [$bunga->getKey(), $andi->getKey()]);

        $this->assertSame(['Bunga', 'Andi'], Candidate::query()->ordered()->pluck('name')->all());
        $this->assertSame(
            ['Bunga', 'Andi'],
            app(ResultService::class)->candidatesFor($field)->pluck('name')->all(),
        );
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('candidates/lama.jpg', 'lama');
        $candidate = Candidate::create(['name' => 'Andi', 'photo_path' => 'candidates/lama.jpg']);

        $candidate->update(['photo_path' => 'candidates/baru.jpg']);

        Storage::disk('public')->assertMissing('candidates/lama.jpg');
    }

    public function test_deleting_a_candidate_deletes_its_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('candidates/andi.jpg', 'foto');
        $candidate = Candidate::create(['name' => 'Andi', 'photo_path' => 'candidates/andi.jpg']);

        $candidate->delete();

        Storage::disk('public')->assertMissing('candidates/andi.jpg');
    }

    public function test_photo_filter_separates_candidates_with_and_without_photos(): void
    {
        $withPhoto = Candidate::create(['name' => 'Andi', 'photo_path' => 'candidates/andi.jpg']);
        $withoutPhoto = Candidate::create(['name' => 'Bunga']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->filterTable('photo', false)
            ->assertCanSeeTableRecords([$withoutPhoto])
            ->assertCanNotSeeTableRecords([$withPhoto]);
    }

    public function test_profile_status_shows_whether_a_photo_is_still_missing(): void
    {
        Candidate::create(['name' => 'Andi', 'photo_path' => 'candidates/andi.jpg']);
        Candidate::create(['name' => 'Bunga']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSee('Profil lengkap')
            ->assertSee('Belum ada foto');
    }

    public function test_golongan_filter_narrows_the_list_to_one_rank(): void
    {
        $match = Candidate::create(['name' => 'Andi', 'golongan' => 'III/a']);
        $other = Candidate::create(['name' => 'Bunga', 'golongan' => 'IV/b']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->filterTable('golongan', 'III/a')
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_preview_action_is_titled_with_the_candidate_and_renders_the_voter_card(): void
    {
        $candidate = Candidate::create(['name' => 'Andi']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertTableActionExists('preview', fn ($action): bool => $action->getModalHeading() === 'Pratinjau Andi', $candidate);

        $html = view('filament.candidates.preview', ['candidate' => $candidate])->render();

        $this->assertStringContainsString('Andi', $html);
        $this->assertStringContainsString('Seperti inilah kartu kandidat tampil bagi pemilih.', $html);
    }

    public function test_incomplete_profiles_link_to_the_edit_page(): void
    {
        $incomplete = Candidate::create(['name' => 'Bunga']);
        $complete = Candidate::create(['name' => 'Andi', 'photo_path' => 'candidates/andi.jpg']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSeeHtml(CandidateResource::getUrl('edit', ['record' => $incomplete]))
            ->assertDontSeeHtml('href="'.CandidateResource::getUrl('edit', ['record' => $complete]).'" class="fi-ta-text-item-ctn');
    }

    public function test_candidate_with_votes_cannot_be_deleted_from_the_edit_page(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $this->castVote($field, $andi);

        Livewire::actingAs($this->superAdmin())
            ->test(EditCandidate::class, ['record' => $andi->getKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_bulk_delete_still_refuses_candidates_with_votes(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $this->castVote($field, $andi);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$andi]);

        $this->assertDatabaseHas('candidates', ['id' => $andi->id]);
    }

    public function test_completeness_filter_matches_the_profile_status_labels(): void
    {
        $complete = Candidate::create(['name' => 'Lengkap', 'photo_path' => 'candidates/a.jpg']);
        $noPhoto = Candidate::create(['name' => 'Tanpa foto']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->filterTable('completeness', 'no_photo')
            ->assertCanSeeTableRecords([$noPhoto])
            ->assertCanNotSeeTableRecords([$complete])
            ->resetTableFilters()
            ->filterTable('completeness', 'complete')
            ->assertCanSeeTableRecords([$complete])
            ->assertCanNotSeeTableRecords([$noPhoto]);
    }

    public function test_export_downloads_one_row_per_candidate_with_photo_and_vote_counts(): void
    {
        Candidate::create(['name' => 'Andi', 'golongan' => 'III/a', 'photo_path' => 'candidates/a.jpg']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callAction('export')
            ->assertFileDownloaded('daftar-kandidat.csv');
    }

    public function test_detail_page_lists_each_field_the_candidate_is_picked_in_with_its_votes(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $bendahara = Field::create(['name' => 'Bendahara', 'position' => 2]);
        $andi = Candidate::create(['name' => 'Andi', 'nip' => '198501012010011001']);
        $this->castVote($presiden, $andi);
        $this->castVote($presiden, $andi);
        $this->castVote($bendahara, $andi);

        Livewire::actingAs($this->superAdmin())
            ->test(ViewCandidate::class, ['record' => $andi->getKey()])
            ->assertSee('Presiden · 2 suara')
            ->assertSee('Bendahara · 1 suara')
            ->assertSee('198501012010011001');

        $this->assertSame(['Presiden', 'Bendahara'], $andi->fields()->get()->pluck('name')->all());
    }

    public function test_detail_page_says_when_a_candidate_is_not_picked_anywhere(): void
    {
        $bunga = Candidate::create(['name' => 'Bunga']);

        Livewire::actingAs($this->superAdmin())
            ->test(ViewCandidate::class, ['record' => $bunga->getKey()])
            ->assertSee('Belum dipilih di bidang mana pun');
    }

    public function test_field_filter_shows_only_candidates_picked_in_that_field(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $bendahara = Field::create(['name' => 'Bendahara', 'position' => 2]);
        $andi = Candidate::create(['name' => 'Andi']);
        $bunga = Candidate::create(['name' => 'Bunga']);
        $this->castVote($presiden, $andi);
        $this->castVote($bendahara, $bunga);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->filterTable('field', $presiden->getKey())
            ->assertCanSeeTableRecords([$andi])
            ->assertCanNotSeeTableRecords([$bunga]);
    }

    public function test_similar_names_are_found_ignoring_case_spacing_and_punctuation(): void
    {
        $andi = Candidate::create(['name' => 'Andi Pratama']);
        Candidate::create(['name' => 'Budi Santoso']);
        Candidate::create(['name' => 'Ani']);

        $similarity = app(CandidateNameSimilarity::class);

        $this->assertSame(['Andi Pratama'], $similarity->similarTo('  andi   pratama! ')->pluck('name')->all());
        $this->assertSame(['Budi Santoso'], $similarity->similarTo('Budi Santosa')->pluck('name')->all());
        $this->assertSame([], $similarity->similarTo('Ana')->pluck('name')->all());
        $this->assertSame([], $similarity->similarTo('Andi Pratama', $andi->getKey())->pluck('name')->all());
    }

    public function test_form_warns_about_a_similar_name_while_it_is_typed(): void
    {
        Candidate::create(['name' => 'Andi Pratama']);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateCandidate::class)
            ->set('data.name', 'andi pratama')
            ->assertSee('Mirip dengan Andi Pratama');
    }

    public function test_live_preview_shows_the_name_being_typed(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(CreateCandidate::class)
            ->set('data.name', 'Calon Baru')
            ->assertSee('Calon Baru');
    }

    public function test_duplicate_copies_the_golongan_and_photo_under_a_free_name_at_the_end(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('candidates/andi.jpg', 'foto');
        $andi = Candidate::create(['name' => 'Andi', 'golongan' => 'III/a', 'photo_path' => 'candidates/andi.jpg', 'position' => 1]);
        Candidate::create(['name' => 'Andi (salinan)', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callTableAction('duplicate', $andi);

        $copy = Candidate::where('name', 'Andi (salinan 2)')->firstOrFail();

        $this->assertSame('III/a', $copy->golongan);
        $this->assertNull($copy->nip);
        $this->assertSame(3, $copy->position);
        $this->assertNotSame('candidates/andi.jpg', $copy->photo_path);
        Storage::disk('public')->assertExists($copy->photo_path);
        Storage::disk('public')->assertExists('candidates/andi.jpg');
    }

    public function test_bulk_photo_upload_matches_files_to_candidates_by_name(): void
    {
        Storage::fake('public');
        $andi = Candidate::create(['name' => 'Andi Pratama']);
        Storage::fake('local');
        $path = Storage::disk('local')->path(Storage::disk('local')->putFileAs('imports', UploadedFile::fake()->image('andi-pratama.jpg'), 'andi-pratama.jpg'));
        $unknown = Storage::disk('local')->path(Storage::disk('local')->putFileAs('imports', UploadedFile::fake()->image('tidak-dikenal.jpg'), 'tidak-dikenal.jpg'));

        $result = app(CandidatePhotoImport::class)->import([$path, $unknown]);

        $this->assertSame(['matched' => 1, 'unmatched' => ['tidak-dikenal.jpg']], $result);
        $this->assertNotNull($andi->fresh()->photo_path);
        Storage::disk('public')->assertExists($andi->fresh()->photo_path);
    }

    public function test_bulk_photo_action_attaches_photos_uploaded_in_the_modal(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $andi = Candidate::create(['name' => 'Andi Pratama']);
        Storage::disk('local')->putFileAs('imports', UploadedFile::fake()->image('andi-pratama.jpg'), 'andi-pratama.jpg');

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callAction('photos', data: ['files' => ['imports/andi-pratama.jpg']])
            ->assertHasNoActionErrors();

        $this->assertNotNull($andi->fresh()->photo_path);
        Storage::disk('local')->assertMissing('imports/andi-pratama.jpg');
    }

    public function test_list_explains_why_candidates_cannot_change_while_the_election_is_locked(): void
    {
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSee('Data pemilihan sedang dikunci');
    }

    public function test_photo_stat_shows_how_many_candidates_have_a_photo_as_a_bar(): void
    {
        Candidate::create(['name' => 'Andi', 'photo_path' => 'candidates/andi.jpg']);
        Candidate::create(['name' => 'Bunga']);

        Livewire::actingAs($this->superAdmin())
            ->test(CandidateStats::class)
            ->assertSeeHtml('aria-valuenow="50"')
            ->assertSee('1 kandidat belum ada foto');
    }

    public function test_recently_changed_candidate_row_is_marked_so_the_change_is_noticed(): void
    {
        Candidate::create(['name' => 'Andi']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSeeHtml('admin-row-enter');
    }

    public function test_photo_cell_names_the_candidate_in_its_alt_text_and_falls_back_to_an_initial(): void
    {
        Candidate::create(['name' => 'Andi', 'photo_path' => 'candidates/andi.jpg']);
        Candidate::create(['name' => 'Bunga']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->assertSeeHtml('alt="Foto Andi"')
            ->assertSeeHtml('aria-label="Belum ada foto untuk Bunga"');
    }

    public function test_candidate_appears_once_per_field_even_when_picked_many_times(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        $this->castVote($field, $andi);
        $this->castVote($field, $andi);

        $this->assertCount(1, $andi->fields()->get());
    }

    public function test_edit_page_opens_read_only_while_the_election_is_locked(): void
    {
        $admin = $this->superAdmin();
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);
        $andi = Candidate::create(['name' => 'Andi', 'golongan' => 'III/a']);

        $this->actingAs($admin)
            ->get(CandidateResource::getUrl('edit', ['record' => $andi]))
            ->assertOk()
            ->assertSee('Data pemilihan sedang dikunci');

        Livewire::actingAs($admin)
            ->test(EditCandidate::class, ['record' => $andi->getKey()])
            ->assertFormFieldIsDisabled('name')
            ->set('data.golongan', 'IV/a')
            ->call('save');

        $this->assertSame('III/a', $andi->fresh()->golongan);
    }

    public function test_locked_candidate_cannot_be_deleted_from_its_edit_page(): void
    {
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);
        $andi = Candidate::create(['name' => 'Andi']);

        Livewire::actingAs($this->superAdmin())
            ->test(EditCandidate::class, ['record' => $andi->getKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_candidate_edit_page_saves_normally_when_not_locked(): void
    {
        $andi = Candidate::create(['name' => 'Andi', 'golongan' => 'III/a']);

        Livewire::actingAs($this->superAdmin())
            ->test(EditCandidate::class, ['record' => $andi->getKey()])
            ->set('data.golongan', 'IV/a')
            ->call('save');

        $this->assertSame('IV/a', $andi->fresh()->golongan);
    }

    public function test_nip_and_golongan_are_saved_from_the_candidate_form(): void
    {
        $andi = Candidate::create(['name' => 'Andi']);

        Livewire::actingAs($this->superAdmin())
            ->test(EditCandidate::class, ['record' => $andi->getKey()])
            ->fillForm(['nip' => '198501012010011001', 'golongan' => 'III/b'])
            ->call('save')
            ->assertHasNoFormErrors();

        $andi->refresh();
        $this->assertSame('198501012010011001', $andi->nip);
        $this->assertSame('III/b', $andi->golongan);
    }

    public function test_candidate_nip_must_be_unique_and_digits_only(): void
    {
        Candidate::create(['name' => 'Andi', 'nip' => '198501012010011001']);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateCandidate::class)
            ->fillForm(['name' => 'Bunga', 'nip' => '198501012010011001'])
            ->call('create')
            ->assertHasFormErrors(['nip']);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateCandidate::class)
            ->fillForm(['name' => 'Bunga', 'nip' => '19850x'])
            ->call('create')
            ->assertHasFormErrors(['nip']);
    }

    public function test_candidate_golongan_must_be_one_from_the_list(): void
    {
        Livewire::actingAs($this->superAdmin())
            ->test(CreateCandidate::class)
            ->fillForm(['name' => 'Bunga', 'golongan' => 'V/z'])
            ->call('create')
            ->assertHasFormErrors(['golongan']);
    }

    public function test_table_can_be_searched_by_nip_as_well_as_name(): void
    {
        $andi = Candidate::create(['name' => 'Andi', 'nip' => '198501012010011001']);
        $bunga = Candidate::create(['name' => 'Bunga', 'nip' => '199912312020011002']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->searchTable('198501012010011001')
            ->assertCanSeeTableRecords([$andi])
            ->assertCanNotSeeTableRecords([$bunga]);
    }

    private function castVote(Field $field, Candidate $candidate): void
    {
        $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);

        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
