<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCountryTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('country_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('task_id');
            $table->string('currency');
            $table->double('original_price');
            $table->double('payable_price');
            $table->boolean('has_shipment')->default(false);
            $table->double('shipment_price')->default(0);
            $table->timestamps();

            $table->unique(['country_id', 'task_id']);

            $table->foreign('country_id')
                ->references('id')
                ->on('countries')
                ->onDelete('cascade');

            $table->foreign('task_id')
                ->references('id')
                ->on('tasks')
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
        Schema::dropIfExists('country_tasks');
    }
}
