<?php

namespace Tests\Feature;

use App\Filament\Resources\Fields\FieldResource;
use App\Filament\Resources\Fields\Pages\CreateField;
use App\Filament\Resources\Fields\Pages\EditField;
use App\Filament\Resources\Fields\Pages\ListFields;
use App\Filament\Widgets\FieldStats;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use App\Services\ResultService;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FieldResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_count_fields_and_fields_without_votes(): void
    {
        $filled = Field::create(['name' => 'Presiden', 'position' => 1]);
        Field::create(['name' => 'Bendahara', 'position' => 2]);
        $this->castVote($filled);

        Livewire::actingAs($this->superAdmin())
            ->test(FieldStats::class)
            ->assertSee('Jumlah bidang')
            ->assertSee('Total suara')
            ->assertSee('Sudah terisi')
            ->assertSee('1 bidang belum ada suara');
    }

    public function test_table_shows_how_many_picks_each_field_received(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $bendahara = Field::create(['name' => 'Bendahara', 'position' => 2]);
        $this->castVote($presiden);
        $this->castVote($presiden);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->assertCanSeeTableRecords([$presiden, $bendahara])
            ->assertSeeInOrder(['Presiden', 'Bendahara'])
            ->assertSee('Total suara');
    }

    public function test_fields_are_listed_in_their_voting_order(): void
    {
        $second = Field::create(['name' => 'Kedua', 'position' => 2]);
        $first = Field::create(['name' => 'Pertama', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->assertCanSeeTableRecords([$first, $second], inOrder: true);
    }

    public function test_dragging_rows_changes_the_voting_order_and_results_order(): void
    {
        $presiden = Field::create(['name' => 'Presiden', 'position' => 1]);
        $bendahara = Field::create(['name' => 'Bendahara', 'position' => 2]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->call('reorderTable', [$bendahara->getKey(), $presiden->getKey()]);

        $this->assertSame(
            ['Bendahara', 'Presiden'],
            Field::query()->ordered()->pluck('name')->all(),
        );
        $this->assertSame(
            ['Bendahara', 'Presiden'],
            app(ResultService::class)->results()->pluck('field.name')->all(),
        );
    }

    public function test_new_field_is_added_to_the_end_of_the_voting_order(): void
    {
        Field::create(['name' => 'Presiden', 'position' => 5]);

        Livewire::actingAs($this->superAdmin())
            ->test(CreateField::class)
            ->fillForm(['name' => 'Bendahara'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(6, Field::where('name', 'Bendahara')->value('position'));
    }

    public function test_filter_separates_fields_with_and_without_votes(): void
    {
        $filled = Field::create(['name' => 'Presiden', 'position' => 1]);
        $empty = Field::create(['name' => 'Bendahara', 'position' => 2]);
        $this->castVote($filled);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->filterTable('has_votes', true)
            ->assertCanSeeTableRecords([$filled])
            ->assertCanNotSeeTableRecords([$empty]);
    }

    public function test_field_with_votes_cannot_be_deleted_from_the_edit_page(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $this->castVote($field);

        Livewire::actingAs($this->superAdmin())
            ->test(EditField::class, ['record' => $field->getKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_bulk_delete_refuses_fields_that_already_have_votes(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $this->castVote($field);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->callTableBulkAction(DeleteBulkAction::class, [$field]);

        $this->assertDatabaseHas('fields', ['id' => $field->id]);
        $this->assertDatabaseCount('votes', 1);
    }

    public function test_empty_field_can_be_deleted(): void
    {
        $field = Field::create(['name' => 'Salah input', 'position' => 1]);

        Livewire::actingAs($this->superAdmin())
            ->test(EditField::class, ['record' => $field->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($field);
    }

    public function test_table_names_the_current_leader_of_each_field(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        Candidate::create(['name' => 'Andi']);
        $this->castVote($field);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->assertSee('Pemimpin sementara')
            ->assertSee('Andi')
            ->assertSee('1 suara');
    }

    public function test_field_edit_page_opens_read_only_while_the_election_is_locked(): void
    {
        Election::create(['name' => 'Pemilu', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'locked_at' => now()]);
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);

        $this->actingAs($this->superAdmin())
            ->get(FieldResource::getUrl('edit', ['record' => $field]))
            ->assertOk()
            ->assertSee('Data pemilihan sedang dikunci');

        Livewire::actingAs($this->superAdmin())
            ->test(EditField::class, ['record' => $field->getKey()])
            ->assertFormFieldIsDisabled('name')
            ->set('data.name', 'Diganti')
            ->call('save');

        $this->assertSame('Presiden', $field->fresh()->name);
    }

    private function castVote(Field $field): void
    {
        $ballot = Ballot::create(['user_id' => User::factory()->create()->id]);
        $candidate = Candidate::query()->first() ?? Candidate::create(['name' => 'Jokowi']);

        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $candidate->id]);
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
