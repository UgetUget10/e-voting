<?php

namespace App\Filament\Voter\Pages;

use App\Models\Election;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckReceipt extends Page
{
    /**
     * Session key for the token that links from this page must carry to open the ballot or the results.
     */
    public const GATE_SESSION_KEY = 'voter.status-gate';

    public const GATE_TOKEN_QUERY = 'akses';

    protected static ?string $slug = 'status-memilih';

    protected static ?string $title = 'Status Memilih';

    protected static ?string $navigationLabel = 'Status Memilih';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    /**
     * Kept out of the navigation. Voters who have voted are sent here from the ballot and the results.
     */
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.voter.pages.check-receipt';

    /**
     * Admins have no ballot, so their landing page is the results.
     */
    public function mount(): void
    {
        if (auth()->user()?->is_admin) {
            $this->redirect(Results::getUrl(panel: 'pemilih'), navigate: true);
        }
    }

    /**
     * The link to the ballot or the results from this page, carrying the token that opens them.
     */
    public static function gatedUrl(string $url): string
    {
        return $url.'?'.http_build_query([self::GATE_TOKEN_QUERY => self::gateToken()]);
    }

    /**
     * Whether the request came through this page: it carries the token issued in the current session.
     */
    public static function hasGateAccess(Request $request): bool
    {
        $gate = session(self::GATE_SESSION_KEY);

        if (! is_array($gate) || now()->timestamp >= ($gate['expires_at'] ?? 0)) {
            return false;
        }

        return hash_equals((string) ($gate['token'] ?? ''), (string) $request->query(self::GATE_TOKEN_QUERY));
    }

    /**
     * Reuses the session's token and keeps it alive while the voter is active.
     */
    private static function gateToken(): string
    {
        $gate = session(self::GATE_SESSION_KEY);

        if (! is_array($gate) || ! isset($gate['token'])) {
            $gate = ['token' => Str::random(40)];
        }

        $gate['expires_at'] = now()->addHour()->timestamp;

        session()->put(self::GATE_SESSION_KEY, $gate);

        return $gate['token'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'hasVoted' => Vote::hasVoted(),
            'voteUrl' => self::gatedUrl(Vote::getUrl(panel: 'pemilih')),
            'resultsUrl' => self::gatedUrl(Results::getUrl(panel: 'pemilih')),
            'election' => Election::current(),
            'votingStatus' => Election::current()?->votingStatus() ?? 'none',
            'closesAtMs' => Election::current()?->closes_at?->getTimestampMs(),
        ];
    }
}
