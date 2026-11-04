<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The day of each entry in Jakarta time, so the log can group by day in the local calendar.
     */
    public function up(): void
    {
        Schema::table('admin_activities', function (Blueprint $table) {
            $table->date('day')->nullable()->after('created_at')->index();
        });

        DB::table('admin_activities')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunk(500, function ($activities): void {
                foreach ($activities as $activity) {
                    DB::table('admin_activities')->where('id', $activity->id)->update([
                        'day' => Carbon::parse($activity->created_at, 'UTC')->timezone('Asia/Jakarta')->toDateString(),
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
            $table->dropIndex(['day']);
            $table->dropColumn('day');
        });
    }
};
