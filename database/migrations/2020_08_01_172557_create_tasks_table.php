<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->index();
            $table->string('title')->index();
            $table->string('currency')->index();
            $table->double('original_price');
            $table->double('payable_price');
            $table->boolean('has_shipment');
            $table->double('shipment_price');
            $table->string('destination_url');
            $table->string('coupon_code')->nullable();
            $table->timestamp('expires_at')->index()->nullable();
            $table->text('description')->nullable();
            $table->integer('coin_reward');
            $table->json('custom_attributes')->nullable();
            $table->string('token')->unique();
            $table->timestamps();
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tasks');
    }
}
