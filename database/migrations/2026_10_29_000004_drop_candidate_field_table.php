<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Candidates are no longer tied to fields: any candidate can be picked in any field.
     */
    public function up(): void
    {
        Schema::dropIfExists('candidate_field');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The pivot table is not recreated. The relation it held is no longer used.
    }
};
