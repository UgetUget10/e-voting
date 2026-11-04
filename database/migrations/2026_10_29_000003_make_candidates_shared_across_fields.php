<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Candidates become one shared list that can appear in many fields.
     */
    public function up(): void
    {
        Schema::create('candidate_field', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['candidate_id', 'field_id']);
        });

        DB::table('candidates')->orderBy('id')->each(function ($candidate) {
            DB::table('candidate_field')->insert([
                'candidate_id' => $candidate->id,
                'field_id' => $candidate->field_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('field_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->foreignId('field_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('candidate_field')->orderBy('id')->get()
            ->groupBy('candidate_id')
            ->each(function ($links, $candidateId) {
                DB::table('candidates')->where('id', $candidateId)->update(['field_id' => $links->first()->field_id]);
            });

        Schema::dropIfExists('candidate_field');
    }
};
