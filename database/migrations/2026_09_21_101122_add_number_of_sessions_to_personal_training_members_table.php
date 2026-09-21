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
        Schema::table('personal_training_members', function (Blueprint $table): void {
            $table->unsignedInteger('number_of_sessions')->nullable()->after('training_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_training_members', function (Blueprint $table): void {
            $table->dropColumn('number_of_sessions');
        });
    }
};
