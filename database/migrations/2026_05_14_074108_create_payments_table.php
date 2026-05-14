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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_payment_identifier')->nullable();
            $table->string('transaction_ref')->unique();
            $table->timestamp('transaction_date')->nullable();
            $table->decimal('amount', 10, 2);
            $table->unsignedBigInteger('loan_id')->nullable(); // If wallet deposit, this field isn't needed
            $table->string('type'); // loan_repayment, wallet_deposit
            $table->json('meta')->nullable(); // Optional field to store raw gateway response
            $table->string('status')->default('PENDING');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
