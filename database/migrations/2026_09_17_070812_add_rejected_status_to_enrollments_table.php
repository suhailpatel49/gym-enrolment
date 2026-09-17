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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                ->default('approved')
                ->change();
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
            $table->enum('approval_status', ['pending', 'approved'])
                ->default('approved')
                ->change();
        });
    }
};
