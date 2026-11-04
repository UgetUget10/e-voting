<?php

namespace Tests\Feature;

use App\Filament\Pages\InstitutionSettings;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_admin_can_save_institution_settings_and_the_change_is_logged(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(InstitutionSettings::class)
            ->fillForm([
                Setting::INSTITUTION_NAME => 'MTsN 1 Kota Malang',
                Setting::SIGNER_NAME => 'Dr. Siti Rahayu, M.Pd.',
                Setting::SIGNER_TITLE => 'Kepala Madrasah',
                Setting::VOTER_ANNOUNCEMENT => 'Voting ditutup pukul 14.00 WIB.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('MTsN 1 Kota Malang', Setting::value(Setting::INSTITUTION_NAME));
        $this->assertSame('Dr. Siti Rahayu, M.Pd.', Setting::value(Setting::SIGNER_NAME));
        $this->assertSame('Kepala Madrasah', Setting::value(Setting::SIGNER_TITLE));
        $this->assertSame('Voting ditutup pukul 14.00 WIB.', Setting::value(Setting::VOTER_ANNOUNCEMENT));
        $this->assertDatabaseHas('admin_activities', ['description' => 'Memperbarui pengaturan instansi.']);
    }

    public function test_saving_again_updates_the_existing_value_instead_of_adding_a_row(): void
    {
        Setting::store([Setting::SIGNER_NAME => 'Nama Lama']);
        Setting::store([Setting::SIGNER_NAME => 'Nama Baru']);

        $this->assertSame('Nama Baru', Setting::value(Setting::SIGNER_NAME));
        $this->assertSame(1, Setting::query()->where('key', Setting::SIGNER_NAME)->count());
    }

    public function test_berita_acara_prints_the_signer_and_institution_from_settings(): void
    {
        Setting::store([
            Setting::INSTITUTION_NAME => 'MTsN 1 Kota Malang',
            Setting::SIGNER_NAME => 'Dr. Siti Rahayu, M.Pd.',
            Setting::SIGNER_TITLE => 'Kepala Madrasah',
        ]);

        $this->actingAs($this->admin())
            ->get('/hasil/berita-acara')
            ->assertOk()
            ->assertSee('MTsN 1 Kota Malang')
            ->assertSee('Dr. Siti Rahayu, M.Pd.')
            ->assertSee('Kepala Madrasah')
            ->assertDontSee('Ketua panitia');
    }

    public function test_berita_acara_keeps_a_blank_signature_line_when_no_signer_is_set(): void
    {
        $this->actingAs($this->admin())
            ->get('/hasil/berita-acara')
            ->assertOk()
            ->assertSee('Ketua panitia')
            ->assertSee('......................................');
    }

    public function test_voter_login_shows_the_announcement_and_institution(): void
    {
        Setting::store([
            Setting::INSTITUTION_NAME => 'MTsN 1 Kota Malang',
            Setting::VOTER_ANNOUNCEMENT => 'Voting ditutup pukul 14.00 WIB.',
        ]);

        $this->get('/pemilih/login')
            ->assertOk()
            ->assertSee('MTsN 1 Kota Malang')
            ->assertSee('Voting ditutup pukul 14.00 WIB.');
    }

    public function test_voter_login_hides_the_announcement_when_it_is_empty(): void
    {
        Setting::store([Setting::VOTER_ANNOUNCEMENT => '']);

        $this->get('/pemilih/login')
            ->assertOk()
            ->assertDontSee('voter-login-announcement', escape: false);
    }
}
