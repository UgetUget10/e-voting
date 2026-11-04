<?php

namespace Tests\Feature;

use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Models\Candidate;
use App\Models\Role;
use App\Models\User;
use App\Services\CandidateCsvImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class CandidateCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_names_in_order_after_existing_candidates(): void
    {
        Candidate::create(['name' => 'Lama', 'position' => 5]);

        $result = $this->importCsv("nama\nAndi\nBunga\n");

        $this->assertSame(['created' => 2, 'skipped' => 0, 'invalid' => 0, 'truncated' => false], $result);
        $this->assertSame([6, 7], Candidate::whereIn('name', ['Andi', 'Bunga'])->orderBy('position')->pluck('position')->all());
    }

    public function test_imports_nip_and_golongan_when_the_file_has_those_columns(): void
    {
        $result = $this->importCsv("nama,nip,golongan\nAndi,198501012010011001,iii/a\nBunga,,\n");

        $this->assertSame(2, $result['created']);
        $this->assertSame('198501012010011001', Candidate::where('name', 'Andi')->value('nip'));
        $this->assertSame('III/a', Candidate::where('name', 'Andi')->value('golongan'));
        $this->assertNull(Candidate::where('name', 'Bunga')->value('nip'));
        $this->assertNull(Candidate::where('name', 'Bunga')->value('golongan'));
    }

    public function test_accepts_semicolon_delimiter_and_a_byte_order_mark(): void
    {
        $result = $this->importCsv("\xEF\xBB\xBFNama;NIP\nAndi;198 501 012\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame('198501012', Candidate::where('name', 'Andi')->value('nip'));
    }

    public function test_rows_with_a_nip_already_used_or_a_golongan_off_the_list_are_invalid(): void
    {
        Candidate::create(['name' => 'Lama', 'nip' => '123456']);

        $result = $this->importCsv("nama,nip,golongan\nBaru,123456,\nAndi,,V/z\nBunga,abc,\nCitra,,IV/e\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame(3, $result['invalid']);
        $this->assertSame('IV/e', Candidate::where('name', 'Citra')->value('golongan'));
    }

    public function test_skips_names_that_exist_in_any_letter_case_or_repeat_in_the_file(): void
    {
        Candidate::create(['name' => 'Andi']);

        $result = $this->importCsv("nama\nANDI\nBunga\nbunga\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(2, Candidate::count());
    }

    public function test_counts_blank_and_over_long_values_as_invalid(): void
    {
        $result = $this->importCsv("nama\n,Tanpa nama\n".str_repeat('a', 256)."\nBunga\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['invalid']);
    }

    public function test_rejects_a_file_without_a_name_column(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importCsv("judul,nip\nAndi,1\n");
    }

    public function test_stops_after_the_row_limit_and_reports_it(): void
    {
        $rows = implode("\n", array_map(fn (int $i): string => 'Kandidat '.$i, range(1, CandidateCsvImport::MAX_ROWS + 5)));

        $result = $this->importCsv("nama\n".$rows."\n");

        $this->assertSame(CandidateCsvImport::MAX_ROWS, $result['created']);
        $this->assertTrue($result['truncated']);
    }

    public function test_import_action_creates_candidates_and_removes_the_uploaded_file(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('kandidat.csv', "nama,nip\nAndi,198501012010011001\n");

        Livewire::actingAs($this->superAdmin())
            ->test(ListCandidates::class)
            ->callAction('import', data: ['file' => $file])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('candidates', ['name' => 'Andi']);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    private function importCsv(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'kandidat');
        file_put_contents($path, $content);

        try {
            return app(CandidateCsvImport::class)->import($path);
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
