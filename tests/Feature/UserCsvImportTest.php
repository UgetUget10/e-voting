<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\User;
use App\Services\UserCsvImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class UserCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_voters_with_the_password_from_the_file(): void
    {
        $result = $this->importCsv("nama,email,password\nAndi Pratama,andi@example.com,rahasia-123\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['generated']);

        $voter = User::where('email', 'andi@example.com')->firstOrFail();
        $this->assertSame('Andi Pratama', $voter->name);
        $this->assertTrue(Hash::check('rahasia-123', $voter->password));
        $this->assertFalse($voter->is_admin);
    }

    public function test_generates_a_password_for_rows_without_one_and_reports_it(): void
    {
        $result = $this->importCsv("nama,email\nBunga,bunga@example.com\n");

        $this->assertCount(1, $result['generated']);
        $generated = $result['generated'][0];
        $this->assertSame('bunga@example.com', $generated['email']);
        $this->assertSame(10, strlen($generated['password']));
        $this->assertTrue(Hash::check($generated['password'], User::where('email', 'bunga@example.com')->value('password')));
    }

    public function test_skips_emails_that_exist_in_any_letter_case_or_repeat_in_the_file(): void
    {
        User::factory()->create(['email' => 'andi@example.com']);

        $result = $this->importCsv("nama,email\nAndi,ANDI@example.com\nBunga,bunga@example.com\nBunga Dua,bunga@example.com\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['skipped']);
    }

    public function test_counts_bad_emails_short_passwords_and_blank_names_as_invalid(): void
    {
        $result = $this->importCsv("nama,email,password\n,kosong@example.com,\nDewi,bukan-email,\nEka,eka@example.com,pendek\nFani,fani@example.com,cukup-panjang\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame(3, $result['invalid']);
    }

    public function test_rejects_a_file_without_an_email_column(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importCsv("nama,surel\nAndi,andi@example.com\n");
    }

    public function test_stops_after_the_row_limit_and_reports_it(): void
    {
        $rows = implode("\n", array_map(fn (int $i): string => "Pemilih {$i},pemilih{$i}@example.com", range(1, UserCsvImport::MAX_ROWS + 2)));

        $result = $this->importCsv("nama,email\n".$rows."\n");

        $this->assertSame(UserCsvImport::MAX_ROWS, $result['created']);
        $this->assertTrue($result['truncated']);
    }

    public function test_import_action_downloads_generated_passwords_once(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('pemilih.csv', "nama,email\nBunga,bunga@example.com\n");

        Livewire::actingAs($this->superAdmin())
            ->test(ListUsers::class)
            ->callAction('import', data: ['file' => $file])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('password-pemilih-baru.csv');

        $this->assertDatabaseHas('users', ['email' => 'bunga@example.com']);
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    public function test_existing_voters_are_updated_only_when_asked_and_keep_their_password_when_none_is_given(): void
    {
        $voter = User::factory()->create(['name' => 'Nama Lama', 'email' => 'andi@example.com']);
        $oldHash = $voter->password;
        $path = $this->csvFile("nama,email,password\nAndi Baru,ANDI@example.com,\n");

        try {
            $result = app(UserCsvImport::class)->import($path, updateExisting: true);
        } finally {
            unlink($path);
        }

        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0], array_intersect_key($result, array_flip(['created', 'updated', 'skipped'])));
        $voter->refresh();
        $this->assertSame('Andi Baru', $voter->name);
        $this->assertSame($oldHash, $voter->password);
    }

    public function test_updating_an_existing_voter_can_also_change_the_password(): void
    {
        $voter = User::factory()->create(['email' => 'andi@example.com']);
        $path = $this->csvFile("nama,email,password\nAndi,andi@example.com,password-baru-1\n");

        try {
            app(UserCsvImport::class)->import($path, updateExisting: true);
        } finally {
            unlink($path);
        }

        $this->assertTrue(Hash::check('password-baru-1', $voter->fresh()->password));
    }

    public function test_existing_accounts_are_skipped_without_the_update_option(): void
    {
        $voter = User::factory()->create(['name' => 'Nama Lama', 'email' => 'andi@example.com']);
        $path = $this->csvFile("nama,email\nAndi Baru,andi@example.com\n");

        try {
            $result = app(UserCsvImport::class)->import($path);
        } finally {
            unlink($path);
        }

        $this->assertSame(1, $result['skipped']);
        $this->assertSame('Nama Lama', $voter->fresh()->name);
    }

    public function test_admin_accounts_are_never_changed_by_an_import(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Asli', 'email' => 'admin@example.com', 'is_admin' => true]);
        $oldHash = $admin->password;
        $path = $this->csvFile("nama,email,password\nSiapa Saja,admin@example.com,password-baru-1\n");

        try {
            $result = app(UserCsvImport::class)->import($path, updateExisting: true);
        } finally {
            unlink($path);
        }

        $this->assertSame(1, $result['skipped']);
        $this->assertSame('Admin Asli', $admin->fresh()->name);
        $this->assertSame($oldHash, $admin->fresh()->password);
    }

    private function csvFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pemilih');
        file_put_contents($path, $content);

        return $path;
    }

    public function test_new_voters_get_a_username_built_from_their_email(): void
    {
        $this->importCsv("nama,email,password\nAhmad Fauzi,ahmad.fauzi@sekolah.test,rahasia-123\nSiti,Siti+kelas7@sekolah.test,rahasia-123\n");

        $this->assertSame('ahmad.fauzi', User::where('email', 'ahmad.fauzi@sekolah.test')->value('username'));
        $this->assertSame('siti.kelas7', User::where('email', 'siti+kelas7@sekolah.test')->value('username'));
    }

    public function test_a_derived_username_gets_a_number_when_it_is_already_taken(): void
    {
        User::factory()->create(['username' => 'ahmad']);

        $this->importCsv("nama,email,password\nAhmad,ahmad@sekolah.test,rahasia-123\n");

        $this->assertSame('ahmad2', User::where('email', 'ahmad@sekolah.test')->value('username'));
    }

    public function test_a_username_from_the_file_is_used_in_lowercase(): void
    {
        $result = $this->importCsv("nama,username,email,password\nSiti,Siti.Kelas7,siti@sekolah.test,rahasia-123\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame('siti.kelas7', User::where('email', 'siti@sekolah.test')->value('username'));
    }

    public function test_a_username_already_used_by_someone_else_makes_the_row_invalid(): void
    {
        User::factory()->create(['username' => 'siswa01']);

        $result = $this->importCsv("nama,username,email,password\nBudi,SISWA01,budi@sekolah.test,rahasia-123\n");

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['invalid']);
        $this->assertFalse(User::where('email', 'budi@sekolah.test')->exists());
    }

    public function test_a_username_outside_the_allowed_pattern_makes_the_row_invalid(): void
    {
        $result = $this->importCsv("nama,username,email,password\nBudi,budi santoso!,budi@sekolah.test,rahasia-123\nCici,ab,cici@sekolah.test,rahasia-123\n");

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['invalid']);
    }

    public function test_generated_passwords_come_with_the_username(): void
    {
        $result = $this->importCsv("nama,email\nBunga,bunga@example.com\n");

        $this->assertSame('bunga', $result['generated'][0]['username']);
    }

    public function test_existing_voters_keep_their_username_when_updated_from_a_file(): void
    {
        User::factory()->create(['name' => 'Ani', 'email' => 'ani@sekolah.test', 'username' => 'ani']);

        $this->importCsv("nama,username,email,password\nAni Baru,ani.baru,ani@sekolah.test,rahasia-123\n", updateExisting: true);

        $voter = User::where('email', 'ani@sekolah.test')->firstOrFail();
        $this->assertSame('Ani Baru', $voter->name);
        $this->assertSame('ani', $voter->username);
    }

    private function importCsv(string $content, bool $updateExisting = false): array
    {
        $path = tempnam(sys_get_temp_dir(), 'pemilih');
        file_put_contents($path, $content);

        try {
            return app(UserCsvImport::class)->import($path, $updateExisting);
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
