<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropColumn('vision');
            $table->string('nip', 30)->nullable()->unique()->after('name');
            $table->string('golongan', 10)->nullable()->after('nip');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropUnique(['nip']);
            $table->dropColumn(['nip', 'golongan']);
            $table->text('vision')->nullable()->after('photo_path');
        });
    }
};
