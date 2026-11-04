<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ballots', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false)->index()->after('receipt_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ballots', function (Blueprint $table) {
            $table->dropIndex(['needs_review']);
            $table->dropColumn('needs_review');
        });
    }
};
