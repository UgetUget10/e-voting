<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ballots', function (Blueprint $table) {
            $table->string('receipt_code', 12)->nullable()->unique()->after('user_id');
        });

        // Existing ballots get a receipt code too, so the admin list can show one for every ballot.
        DB::table('ballots')->orderBy('id')->pluck('id')->each(function (int $id): void {
            DB::table('ballots')->where('id', $id)->update(['receipt_code' => Str::upper(Str::random(10))]);
        });
    }

    public function down(): void
    {
        Schema::table('ballots', function (Blueprint $table) {
            $table->dropUnique(['receipt_code']);
            $table->dropColumn('receipt_code');
        });
    }
};
