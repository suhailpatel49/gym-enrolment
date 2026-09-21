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
        Schema::table('personal_training_members', function (Blueprint $table): void {
            $table->enum('training_status', ['pending', 'active', 'completed', 'cancelled'])
                ->default('pending')
                ->after('active');
        });

        $today = today()->toDateString();
        DB::update(<<<'SQL'
            UPDATE personal_training_members
            SET training_status = CASE
                WHEN active = 0 THEN 'cancelled'
                WHEN date(start_date) > ? THEN 'pending'
                WHEN date(end_date) < ? THEN 'completed'
                ELSE 'active'
            END
        SQL, [$today, $today]);

        Schema::table('personal_training_members', function (Blueprint $table): void {
            $table->dropIndex(['active', 'end_date']);
            $table->dropColumn('active');
        });

        Schema::table('personal_training_members', function (Blueprint $table): void {
            $table->index(['training_status', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_training_members', function (Blueprint $table): void {
            $table->boolean('active')->default(true)->after('training_status');
        });

        DB::table('personal_training_members')
            ->where('training_status', 'cancelled')
            ->update(['active' => false]);

        Schema::table('personal_training_members', function (Blueprint $table): void {
            $table->dropIndex(['training_status', 'end_date']);
            $table->dropColumn('training_status');
        });

        Schema::table('personal_training_members', function (Blueprint $table): void {
            $table->index(['active', 'end_date']);
        });
    }
};
