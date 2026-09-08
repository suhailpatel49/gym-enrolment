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
        if (DB::table('personal_training_members')->whereRaw($this->invalidMoneyPredicate())->exists()) {
            throw new RuntimeException(
                'Existing personal training records contain invalid money splits. Repair them before retrying this migration.'
            );
        }

        $invalidMoneyPredicate = $this->invalidMoneyPredicate('NEW.');

        foreach (['insert', 'update'] as $operation) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER personal_training_members_money_{$operation}
                BEFORE {$operation} ON personal_training_members
                FOR EACH ROW
                WHEN {$invalidMoneyPredicate}
                BEGIN
                    SELECT RAISE(ABORT, 'Invalid personal training money split');
                END
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS personal_training_members_money_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS personal_training_members_money_update');
    }

    private function invalidMoneyPredicate(string $prefix = ''): string
    {
        return <<<SQL
            typeof({$prefix}total_client_amount) NOT IN ('integer', 'real')
                OR typeof({$prefix}gym_amount) NOT IN ('integer', 'real')
                OR typeof({$prefix}trainer_amount) NOT IN ('integer', 'real')
                OR {$prefix}total_client_amount < 0
                OR {$prefix}total_client_amount > 99999999.99
                OR {$prefix}total_client_amount != ROUND({$prefix}total_client_amount, 2)
                OR {$prefix}gym_amount < 0
                OR {$prefix}gym_amount > 99999999.99
                OR {$prefix}gym_amount != ROUND({$prefix}gym_amount, 2)
                OR {$prefix}trainer_amount < 0
                OR {$prefix}trainer_amount > 99999999.99
                OR {$prefix}trainer_amount != ROUND({$prefix}trainer_amount, 2)
                OR CAST(ROUND({$prefix}gym_amount * 100) AS INTEGER) > CAST(ROUND({$prefix}total_client_amount * 100) AS INTEGER)
                OR CAST(ROUND({$prefix}trainer_amount * 100) AS INTEGER) != CAST(ROUND({$prefix}total_client_amount * 100) AS INTEGER) - CAST(ROUND({$prefix}gym_amount * 100) AS INTEGER)
        SQL;
    }
};
