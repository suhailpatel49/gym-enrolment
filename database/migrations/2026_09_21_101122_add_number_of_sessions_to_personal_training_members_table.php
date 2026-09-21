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
            $table->unsignedInteger('number_of_sessions')->nullable()->after('training_status');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER personal_training_members_sessions_{$operation}
                BEFORE {$operation} ON personal_training_members
                FOR EACH ROW
                WHEN NEW.number_of_sessions IS NOT NULL
                    AND (typeof(NEW.number_of_sessions) != 'integer' OR NEW.number_of_sessions < 1)
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid personal training number of sessions');
                END
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS personal_training_members_sessions_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS personal_training_members_sessions_update');
        }

        Schema::table('personal_training_members', function (Blueprint $table): void {
            $table->dropColumn('number_of_sessions');
        });
    }
};
