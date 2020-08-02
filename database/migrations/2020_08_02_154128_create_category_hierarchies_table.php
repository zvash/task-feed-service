<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryHierarchiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('category_hierarchies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->index();
            $table->unsignedBigInteger('child_id')->index();

            $table->unique(['parent_id', 'child_id']);

            $table->foreign('parent_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');

            $table->foreign('child_id')
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
        Schema::dropIfExists('category_hierarchies');
    }
}
