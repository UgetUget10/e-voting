<?php

namespace Tests\Feature;

use App\Filament\Resources\Fields\Pages\ListFields;
use App\Filament\Resources\Fields\Pages\ViewField;
use App\Filament\Widgets\FieldStats;
use App\Models\AdminActivity;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteCorrection;
use App\Services\FieldStandings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FieldListToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_drag_reorder_is_refused_while_the_election_is_locked(): void
    {
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $bendahara = Field::create(['name' => 'Bendahara', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->call('reorderTable', [$bendahara->getKey(), $presiden->getKey()]);

        $this->assertSame(['Presiden', 'Bendahara'], Field::query()->ordered()->pluck('name')->all());
    }

    public function test_locked_list_explains_why_fields_cannot_change(): void
    {
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->assertSee('Data pemilihan sedang dikunci');
    }

    public function test_a_correction_alone_counts_as_a_field_with_votes_in_the_stats(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        Field::create(['name' => 'Bendahara', 'position' => 2]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        VoteCorrection::create(['candidate_id' => $andi->getKey(), 'field_id' => $presiden->getKey(), 'amount' => 2, 'reason' => 'Koreksi', 'user_id' => null]);

        $this->assertSame(FieldStandings::LEADING, app(FieldStandings::class)->for($presiden)['status']);

        Livewire::actingAs($this->superAdmin())
            ->test(FieldStats::class)
            ->assertSeeHtml('data-count-to="2"')
            ->assertSee('1 bidang belum ada suara');
    }

    public function test_a_tie_for_first_place_is_marked_as_seri(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $bunga = Candidate::create(['name' => 'Bunga', 'position' => 2]);
        $this->castVotes($presiden, $andi, 2);
        $this->castVotes($presiden, $bunga, 2);

        $this->assertSame(FieldStandings::TIED, app(FieldStandings::class)->for($presiden)['status']);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->assertSee('Seri');
    }

    public function test_status_filter_shows_only_fields_with_the_chosen_result(): void
    {
        $tied = Field::create(['name' => 'Presiden', 'position' => 1]);
        $leading = Field::create(['name' => 'Bendahara', 'position' => 2]);
        $empty = Field::create(['name' => 'Sekretaris', 'position' => 3]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $bunga = Candidate::create(['name' => 'Bunga', 'position' => 2]);
        $this->castVotes($tied, $andi, 1);
        $this->castVotes($tied, $bunga, 1);
        $this->castVotes($leading, $andi, 3);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->filterTable('standing', FieldStandings::TIED)
            ->assertCanSeeTableRecords([$tied])
            ->assertCanNotSeeTableRecords([$leading, $empty]);
    }

    public function test_leader_share_is_drawn_as_a_bar(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $bunga = Candidate::create(['name' => 'Bunga', 'position' => 2]);
        $this->castVotes($presiden, $andi, 3);
        $this->castVotes($presiden, $bunga, 1);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->assertSee('Andi')
            ->assertSee('3 suara · 75%')
            ->assertSeeHtml('aria-valuenow="75"');
    }

    public function test_rows_open_the_field_detail_page(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->assertSeeHtml(route('filament.admin.resources.fields.view', ['record' => $presiden]));
    }

    public function test_detail_page_lists_candidates_by_votes_and_marks_who_advances(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $bunga = Candidate::create(['name' => 'Bunga', 'position' => 2]);
        $cahya = Candidate::create(['name' => 'Cahya', 'position' => 3]);
        $this->castVotes($presiden, $andi, 4);
        $this->castVotes($presiden, $bunga, 2);
        $this->castVotes($presiden, $cahya, 1);

        Livewire::actingAs($this->superAdmin())
            ->test(ViewField::class, ['record' => $presiden->getKey()])
            ->assertSeeInOrder(['Andi', 'Bunga', 'Cahya'])
            ->assertSee('Lolos')
            ->assertSee('Ada pemenang');
    }

    public function test_detail_page_explains_when_nobody_has_votes(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(ViewField::class, ['record' => $presiden->getKey()])
            ->assertSee('Belum ada kandidat yang menerima suara di bidang ini.');
    }

    public function test_voter_order_preview_lists_fields_as_the_voter_will_see_them(): void
    {
        Field::create(['name' => 'Bendahara', 'position' => 2]);
        Field::create(['name' => 'Presiden', 'position' => 1]);
        Candidate::create(['name' => 'Andi', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->mountAction('voterOrder')
            ->assertActionMounted('voterOrder');

        $preview = view('filament.fields.voter-order', [
            'fields' => Field::query()->ordered()->get(),
            'candidateCount' => Candidate::count(),
        ])->render();

        $this->assertLessThan(strpos($preview, 'Bendahara'), strpos($preview, 'Presiden'));
        $this->assertStringContainsString('Pilih satu kandidat dari 1 kandidat', $preview);
    }

    public function test_duplicate_copies_the_name_at_the_end_without_votes(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $this->castVotes($presiden, $andi, 2);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->callTableAction('duplicate', $presiden)
            ->assertHasNoTableActionErrors();

        $copy = Field::query()->where('name', 'Presiden (salinan)')->first();

        $this->assertNotNull($copy);
        $this->assertSame(2, $copy->position);
        $this->assertFalse($copy->hasVotes());
    }

    public function test_duplicate_gets_a_numbered_name_when_the_copy_name_is_taken(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        Field::create(['name' => 'Presiden (salinan)', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->callTableAction('duplicate', $presiden);

        $this->assertNotNull(Field::query()->where('name', 'Presiden (salinan 2)')->first());
    }

    public function test_pdf_download_is_logged(): void
    {
        Field::create(['name' => 'Presiden', 'position' => 1]);

        $this->actingAs($this->superAdmin())
            ->get(route('fields.export.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertNotNull(AdminActivity::query()->where('description', 'Mengunduh daftar bidang dalam PDF.')->first());
    }

    public function test_csv_export_includes_the_result_of_each_field(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi', 'position' => 1]);
        $this->castVotes($presiden, $andi, 2);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->callAction('export');

        $this->assertSame(FieldStandings::LEADING, app(FieldStandings::class)->for($presiden)['status']);
    }

    private function castVotes(Field $field, Candidate $candidate, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);

            Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
        }
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
