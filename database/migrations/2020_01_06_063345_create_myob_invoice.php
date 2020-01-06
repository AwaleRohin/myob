<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMyobInvoice extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('myob_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->string('customer_uid');
            $table->string('account_uid')->nullable();
            $table->string('item_uid')->nullable();
            $table->string('myob_invoice_id')->nullable();
            $table->string('invoice_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('myob_invoices');
    }
}
