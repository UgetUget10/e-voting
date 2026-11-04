<?php

use App\Services\AdminActivityClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores each entry's category and sensitivity, and fills them in for entries written before this change.
     */
    public function up(): void
    {
        Schema::table('admin_activities', function (Blueprint $table) {
            $table->string('category', 20)->default(AdminActivityClassifier::SISTEM)->after('description')->index();
            $table->boolean('sensitive')->default(false)->after('category')->index();
        });

        $classifier = app(AdminActivityClassifier::class);

        DB::table('admin_activities')
            ->select(['id', 'description'])
            ->orderBy('id')
            ->chunk(500, function ($activities) use ($classifier): void {
                foreach ($activities as $activity) {
                    DB::table('admin_activities')->where('id', $activity->id)->update([
                        'category' => $classifier->category($activity->description),
                        'sensitive' => $classifier->isSensitive($activity->description),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_activities', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['sensitive']);
            $table->dropColumn(['category', 'sensitive']);
        });
    }
};
