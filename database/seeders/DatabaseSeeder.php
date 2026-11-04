<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Field;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the four Waka fields of the madrasah and the shared candidate list.
     * Every candidate can be picked in any field.
     */
    public function run(): void
    {
        Election::firstOrCreate(
            ['name' => 'Pemilihan Wakil Kepala Madrasah 2026'],
            ['opens_at' => now(), 'closes_at' => now()->addDays(7)],
        );

        collect([
            'Waka Bidang Akademik',
            'Waka Bidang Kesiswaan',
            'Waka Bidang Humas',
            'Waka Bidang Sarpras',
        ])->each(fn (string $name) => Field::firstOrCreate(['name' => $name]));

        collect(['Jokowi', 'Gibran', 'Prabowo'])
            ->each(fn (string $name) => Candidate::firstOrCreate(['name' => $name]));
    }
}
