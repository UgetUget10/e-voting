<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use App\Models\User;

/**
 * The one place that decides what an activity entry is: its category, whether it is sensitive, and how its
 * subject and changed columns are named. The result is stored when the entry is written, so filters and exports
 * do not depend on parsing the description later.
 */
class AdminActivityClassifier
{
    public const TAMBAH = 'tambah';

    public const UBAH = 'ubah';

    public const HAPUS = 'hapus';

    public const EKSPOR = 'ekspor';

    public const KEAMANAN = 'keamanan';

    public const SISTEM = 'sistem';

    /**
     * Checked before the verbs, so an export that names identitas still counts as an export.
     *
     * @var list<string>
     */
    private const SECURITY_PREFIXES = [
        'Membuka identitas',
        'Mengunci',
        'Membuka kunci',
        'Mengganti email',
        'Mengatur ulang password',
        'Menambahkan peran',
        'Mencabut peran',
    ];

    /**
     * The first word of the description decides the category when no security prefix matches.
     *
     * @var array<string, string>
     */
    private const VERBS = [
        'Menambahkan' => self::TAMBAH,
        'Membuat' => self::TAMBAH,
        'Mengimpor' => self::TAMBAH,
        'Mengunggah' => self::TAMBAH,
        'Menduplikat' => self::TAMBAH,
        'Menghapus' => self::HAPUS,
        'Mereset' => self::HAPUS,
        'Mengekspor' => self::EKSPOR,
        'Mengunduh' => self::EKSPOR,
        'Mencetak' => self::EKSPOR,
        'Mengirim' => self::SISTEM,
        'Memverifikasi' => self::SISTEM,
        'Mengubah' => self::UBAH,
        'Mengganti' => self::UBAH,
        'Mengoreksi' => self::UBAH,
        'Mengacak' => self::UBAH,
        'Mengatur' => self::UBAH,
        'Membatalkan' => self::UBAH,
        'Memperpanjang' => self::UBAH,
        'Mengaktifkan' => self::UBAH,
        'Mengarsipkan' => self::UBAH,
        'Menutup' => self::UBAH,
        'Membuka' => self::UBAH,
    ];

    /**
     * Actions that change who can do what, or touch data that identifies a voter, or change the election's state
     * in a way that cannot be undone. These are flagged so they can be found and reviewed quickly.
     *
     * @var list<string>
     */
    private const SENSITIVE_PHRASES = [
        'identitas',
        'kunci',
        'password',
        'peran',
        'email',
        'Mereset',
        'Menghapus',
        'Mengoreksi total suara',
        'Membatalkan koreksi',
        'voting "',
        'jadwal',
    ];

    /**
     * @return array<string, string>
     */
    public static function categoryLabels(): array
    {
        return [
            self::TAMBAH => 'Tambah',
            self::UBAH => 'Ubah',
            self::HAPUS => 'Hapus',
            self::EKSPOR => 'Ekspor',
            self::KEAMANAN => 'Keamanan',
            self::SISTEM => 'Sistem',
        ];
    }

    public static function categoryLabel(string $category): string
    {
        return self::categoryLabels()[$category] ?? 'Sistem';
    }

    public static function categoryColor(string $category): string
    {
        return match ($category) {
            self::TAMBAH => 'success',
            self::UBAH => 'info',
            self::HAPUS => 'danger',
            self::EKSPOR => 'primary',
            self::KEAMANAN => 'warning',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function subjectLabels(): array
    {
        return [
            Candidate::class => 'Kandidat',
            Field::class => 'Bidang',
            Ballot::class => 'Surat suara',
            Election::class => 'Pemilihan',
            User::class => 'Pengguna',
        ];
    }

    public static function subjectLabel(?string $subjectType): ?string
    {
        return $subjectType ? (self::subjectLabels()[$subjectType] ?? class_basename($subjectType)) : null;
    }

    /**
     * The category stored with an entry, from its description.
     */
    public function category(string $description): string
    {
        foreach (self::SECURITY_PREFIXES as $prefix) {
            if (str_starts_with($description, $prefix)) {
                return self::KEAMANAN;
            }
        }

        foreach (self::VERBS as $verb => $category) {
            if (str_starts_with($description, $verb)) {
                return $category;
            }
        }

        return self::SISTEM;
    }

    public function isSensitive(string $description): bool
    {
        if ($this->category($description) === self::KEAMANAN) {
            return true;
        }

        foreach (self::SENSITIVE_PHRASES as $phrase) {
            if (mb_stripos($description, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The name shown for a column that was changed, such as "nip" as "NIP".
     */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => 'Nama',
            'nip' => 'NIP',
            'golongan' => 'Golongan',
            'photo_path' => 'Foto',
            'position' => 'Urutan',
            'total' => 'Total suara',
            'show_results_early' => 'Tampilkan hasil lebih awal',
            'email' => 'Email',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }

    /**
     * How one old or new value reads in the change table. A photo is shown as present or absent, never by its path.
     */
    public static function formatChangeValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($field === 'photo_path') {
            return 'Ada foto';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        return (string) $value;
    }

    /**
     * Two letters for the avatar, from the first letter of the first two words of the name.
     */
    public static function initials(?string $name): string
    {
        return mb_strtoupper(collect(preg_split('/\s+/u', trim((string) $name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_substr($word, 0, 1))
            ->implode(''));
    }
}
