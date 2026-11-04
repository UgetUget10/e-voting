<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Field;
use App\Models\User;
use App\Services\CsvCell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvCellTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_that_looks_like_a_formula_is_neutralised(): void
    {
        foreach (['=SUM(A1)', '+1', '-1', '@cmd', "\tx", "\rx"] as $value) {
            $this->assertSame("'".$value, CsvCell::safe($value));
        }
    }

    public function test_ordinary_values_are_left_alone(): void
    {
        $this->assertSame('Andi Pratama', CsvCell::safe('Andi Pratama'));
        $this->assertSame('', CsvCell::safe(''));
        $this->assertSame(12, CsvCell::safe(12));
        $this->assertNull(CsvCell::safe(null));
    }

    public function test_result_export_neutralises_formula_candidate_names(): void
    {
        Field::create(['name' => 'Presiden', 'position' => 1]);
        Candidate::create(['name' => '=HYPERLINK("http://evil.test")']);
        $admin = User::factory()->create(['is_admin' => true]);

        $csv = $this->actingAs($admin)->get(route('results.export'))->streamedContent();

        $this->assertStringContainsString('\'=HYPERLINK', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK', $csv);
    }
}
