<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $pendingCount = DB::table('enrollments')
            ->where('approval_status', 'pending')
            ->count();

        if ($pendingCount > 0) {
            $label = $pendingCount === 1 ? 'pending enrollment' : 'pending enrollments';

            throw new RuntimeException(
                "Cannot enable direct enrollment decisions: found {$pendingCount} {$label}. "
                .'Pause intake, resolve every pending enrollment under the legacy approval workflow, '
                .'verify the pending count is zero, then rerun php artisan migrate.',
            );
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                ->default('approved')
                ->change();
            $table->string('decision_token_hash', 64)->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('enrollments')
            ->where('approval_status', 'rejected')
            ->update(['approval_status' => 'pending']);

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropUnique(['decision_token_hash']);
            $table->dropColumn('decision_token_hash');
            $table->enum('approval_status', ['pending', 'approved'])
                ->default('approved')
                ->change();
        });
    }
};
