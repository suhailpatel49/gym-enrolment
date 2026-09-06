<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_training_members', function (Blueprint $table): void {
            $table->id();
            $table->string('member_name');
            $table->string('phone')->nullable();
            $table->foreignId('trainer_id')->index()->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('monthly_fee', 10, 2);
            $table->boolean('member_payment_paid')->default(false);
            $table->boolean('trainer_payment_paid')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['active', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_training_members');
    }
};
