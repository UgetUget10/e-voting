<?php

namespace App\Filament\Pages;

use App\Services\AdminActivityLog;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AccountSecurity extends Page
{
    protected static ?string $title = 'Keamanan akun';

    protected static ?string $navigationLabel = 'Keamanan akun';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected string $view = 'filament.pages.account-security';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Verifikasi dua langkah lewat email')
                    ->description('Setelah diaktifkan, setiap kali masuk ke panel admin Anda juga diminta kode satu kali yang dikirim ke email.')
                    ->schema(EmailAuthentication::make()->getManagementSchemaComponents()),
            ]);
    }

    /**
     * The admin's own sign-ins that have not expired. The current browser is marked, so it is never listed as
     * something to end.
     *
     * @return Collection<int, array{id: string, device: string, ip: ?string, lastActive: CarbonInterface, current: bool}>
     */
    public function getSessions(): Collection
    {
        $currentId = session()->getId();

        return DB::table('sessions')
            ->where('user_id', auth()->id())
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->getTimestamp())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): array => [
                'id' => $session->id,
                'device' => $this->describeDevice($session->user_agent),
                'ip' => $session->ip_address,
                'lastActive' => Carbon::createFromTimestamp($session->last_activity)->timezone('Asia/Jakarta'),
                'current' => $session->id === $currentId,
            ]);
    }

    public function terminateSession(string $sessionId): void
    {
        if ($sessionId === session()->getId()) {
            return;
        }

        $ended = DB::table('sessions')
            ->where('user_id', auth()->id())
            ->where('id', $sessionId)
            ->delete();

        if ($ended === 0) {
            return;
        }

        app(AdminActivityLog::class)->record('Mengakhiri satu sesi masuk lain.');

        Notification::make()->success()->title('Sesi diakhiri.')->send();
    }

    public function terminateOtherSessions(): void
    {
        $ended = DB::table('sessions')
            ->where('user_id', auth()->id())
            ->where('id', '!=', session()->getId())
            ->delete();

        if ($ended === 0) {
            return;
        }

        app(AdminActivityLog::class)->record('Mengakhiri '.$ended.' sesi masuk lain.');

        Notification::make()->success()->title($ended.' sesi lain diakhiri.')->send();
    }

    /**
     * A short label such as "Chrome di Windows", read from the user agent. It is a guide for spotting an unknown
     * sign-in, not an exact device record.
     */
    private function describeDevice(?string $userAgent): string
    {
        $agent = (string) $userAgent;

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $system = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'perangkat tidak dikenal',
        };

        return $browser.' di '.$system;
    }
}
