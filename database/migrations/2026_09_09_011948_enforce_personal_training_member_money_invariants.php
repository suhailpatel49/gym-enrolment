<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER personal_training_members_money_{$operation}
                BEFORE {$operation} ON personal_training_members
                FOR EACH ROW
                WHEN typeof(NEW.total_client_amount) NOT IN ('integer', 'real')
                    OR typeof(NEW.gym_amount) NOT IN ('integer', 'real')
                    OR typeof(NEW.trainer_amount) NOT IN ('integer', 'real')
                    OR NEW.total_client_amount < 0
                    OR NEW.total_client_amount > 99999999.99
                    OR NEW.total_client_amount != ROUND(NEW.total_client_amount, 2)
                    OR NEW.gym_amount < 0
                    OR NEW.gym_amount > 99999999.99
                    OR NEW.gym_amount != ROUND(NEW.gym_amount, 2)
                    OR NEW.trainer_amount < 0
                    OR NEW.trainer_amount > 99999999.99
                    OR NEW.trainer_amount != ROUND(NEW.trainer_amount, 2)
                    OR CAST(ROUND(NEW.gym_amount * 100) AS INTEGER) > CAST(ROUND(NEW.total_client_amount * 100) AS INTEGER)
                    OR CAST(ROUND(NEW.trainer_amount * 100) AS INTEGER) != CAST(ROUND(NEW.total_client_amount * 100) AS INTEGER) - CAST(ROUND(NEW.gym_amount * 100) AS INTEGER)
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid personal training money split');
                END
            SQL);
        }

        DB::statement('UPDATE personal_training_members SET total_client_amount = total_client_amount');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS personal_training_members_money_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS personal_training_members_money_update');
    }
};
