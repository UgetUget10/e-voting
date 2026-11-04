<?php

namespace Tests\Feature;

use App\Filament\Resources\Fields\Pages\ListFields;
use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Field;
use App\Models\Role;
use App\Models\User;
use App\Models\Vote;
use App\Services\FieldCsvImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class FieldCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_fields_in_file_order_after_existing_fields(): void
    {
        Field::create(['name' => 'Lama', 'position' => 5]);

        $result = $this->importCsv("bidang\nPresiden\nBendahara\n");

        $this->assertSame(['created' => 2, 'skipped' => 0, 'invalid' => 0, 'truncated' => false], $result);
        $this->assertSame(['Lama', 'Presiden', 'Bendahara'], Field::ordered()->pluck('name')->all());
        $this->assertSame([6, 7], Field::whereIn('name', ['Presiden', 'Bendahara'])->orderBy('position')->pluck('position')->all());
    }

    public function test_accepts_the_nama_column_and_semicolon_delimiter(): void
    {
        $result = $this->importCsv("\xEF\xBB\xBFNama\nPresiden\n");

        $this->assertSame(1, $result['created']);
    }

    public function test_skips_names_that_exist_in_any_letter_case_or_repeat_in_the_file(): void
    {
        Field::create(['name' => 'Presiden']);

        $result = $this->importCsv("bidang\nPRESIDEN\nBendahara\nbendahara\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['skipped']);
    }

    public function test_counts_blank_names_as_invalid(): void
    {
        $result = $this->importCsv("bidang\n\nPresiden\n,\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['invalid']);
    }

    public function test_rejects_a_file_without_a_name_column(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importCsv("judul\nPresiden\n");
    }

    public function test_stops_after_the_row_limit_and_reports_it(): void
    {
        $rows = implode("\n", array_map(fn (int $i): string => 'Bidang '.$i, range(1, FieldCsvImport::MAX_ROWS + 3)));

        $result = $this->importCsv("bidang\n".$rows."\n");

        $this->assertSame(FieldCsvImport::MAX_ROWS, $result['created']);
        $this->assertTrue($result['truncated']);
    }

    public function test_import_action_creates_fields_and_removes_the_uploaded_file(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('bidang.csv', "bidang\nPresiden\n");

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->callAction('import', data: ['file' => $file])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('fields', ['name' => 'Presiden']);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    public function test_export_lists_each_field_with_its_current_leader(): void
    {
        $field = Field::create(['name' => 'Presiden', 'position' => 1]);
        $andi = Candidate::create(['name' => 'Andi']);
        Field::create(['name' => 'Bendahara', 'position' => 2]);
        $ballot = Ballot::create(['user_id' => User::factory()->create(['is_admin' => false])->id]);
        Vote::create(['ballot_id' => $ballot->id, 'field_id' => $field->id, 'candidate_id' => $andi->id]);

        Livewire::actingAs($this->superAdmin())
            ->test(ListFields::class)
            ->callAction('export')
            ->assertFileDownloaded('daftar-bidang.csv');
    }

    private function importCsv(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bidang');
        file_put_contents($path, $content);

        try {
            return app(FieldCsvImport::class)->import($path);
        } finally {
            unlink($path);
        }
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }
}
