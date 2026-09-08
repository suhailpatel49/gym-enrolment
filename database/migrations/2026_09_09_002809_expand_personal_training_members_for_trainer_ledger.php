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
        Schema::table('personal_training_members', function (Blueprint $table) {
            $table->renameColumn('member_name', 'client_name');
            $table->renameColumn('monthly_fee', 'total_client_amount');
        });

        Schema::table('personal_training_members', function (Blueprint $table) {
            $table->string('payment_mode')->default('Not provided')->after('trainer_id');
            $table->decimal('gym_amount', 10, 2)->default(0)->after('total_client_amount');
            $table->decimal('trainer_amount', 10, 2)->default(0)->after('gym_amount');
            $table->text('remark')->nullable()->after('active');
            $table->index(['trainer_id', 'start_date']);
        });

        DB::table('personal_training_members')->update([
            'trainer_amount' => DB::raw('total_client_amount'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_training_members', function (Blueprint $table) {
            $table->dropIndex(['trainer_id', 'start_date']);
            $table->dropColumn(['payment_mode', 'gym_amount', 'trainer_amount', 'remark']);
        });

        Schema::table('personal_training_members', function (Blueprint $table) {
            $table->renameColumn('client_name', 'member_name');
            $table->renameColumn('total_client_amount', 'monthly_fee');
        });
    }
};
