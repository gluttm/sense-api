<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('amount');
            $table->enum('method', ['cash', 'cheque', 'bank_transfer', 'mpesa']);

            $table->string('transaction_id')->nullable();
            $table->string('loan_transaction_id')->nullable();
            $table->date('payment_date');
            $table->string('receipt_number')->nullable();
            $table->string('attachment')->nullable();
            $table->string('notes')->nullable();

            //bank fields
            $table->string('cheque_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->date('cheque_date')->nullable();

            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
