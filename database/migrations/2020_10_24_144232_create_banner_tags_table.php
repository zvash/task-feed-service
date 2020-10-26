<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBannerTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banner_tags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('banner_id')->index();
            $table->unsignedBigInteger('tag_id')->index();
            $table->timestamps();

            $table->unique(['banner_id', 'tag_id']);

            $table->foreign('banner_id')
                ->references('id')
                ->on('banners')
                ->onDelete('cascade');

            $table->foreign('tag_id')
                ->references('id')
                ->on('tags')
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
        Schema::dropIfExists('banner_tags');
    }
}
