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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_code')->unique();
            $table->string('email');
            $table->string('full_name');
            $table->text('address')->nullable();
            $table->string('mobile_number');
            $table->string('emergency_contact')->nullable();
            $table->date('date_of_birth');
            $table->unsignedTinyInteger('package_months');
            $table->boolean('freezing_enabled')->default(false);
            $table->unsignedSmallInteger('freezing_days')->nullable();
            $table->string('payment_mode');
            $table->decimal('amount_paid', 10, 2);
            $table->date('membership_start_date');
            $table->date('membership_end_date');
            $table->boolean('has_balance')->default(false);
            $table->decimal('remaining_balance', 10, 2)->nullable();
            $table->date('balance_due_date')->nullable();
            $table->boolean('terms_accepted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
