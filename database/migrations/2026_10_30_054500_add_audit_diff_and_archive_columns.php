<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_activities', function (Blueprint $table) {
            $table->json('changes')->nullable()->after('description');
        });

        Schema::table('elections', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('locked_at');
            $table->json('results_snapshot')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('admin_activities', function (Blueprint $table) {
            $table->dropColumn('changes');
        });

        Schema::table('elections', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'results_snapshot']);
        });
    }
};
