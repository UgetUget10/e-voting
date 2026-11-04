<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultDocumentExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_downloads_results_as_pdf_and_excel(): void
    {
        Candidate::create(['name' => 'Andi']);
        $admin = User::factory()->create(['is_admin' => true]);

        $pdf = $this->actingAs($admin)->get(route('results.export.pdf'));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->actingAs($admin)->get(route('results.export.xlsx'));
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());
    }

    public function test_voters_cannot_download_result_documents(): void
    {
        $voter = User::factory()->create(['is_admin' => false]);

        $this->actingAs($voter)->get(route('results.export.pdf'))->assertForbidden();
        $this->actingAs($voter)->get(route('results.export.xlsx'))->assertForbidden();
    }
}
