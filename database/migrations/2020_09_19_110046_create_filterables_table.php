<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilterablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('filterables', function (Blueprint $table) {
            $table->id();
            $table->string('table')->index();
            $table->string('column')->index();
            $table->string('column_type');
            $table->string('relation_to_tasks');
            $table->string('grouping_column')->nullable()->default(null);
            $table->string('relation_name')->nullable()->default(null);
            $table->timestamps();
        });

        \App\Filterable::create([
            'table' => 'categories',
            'column' => 'name',
            'column_type' => 'string',
            'relation_to_tasks' => 'has_many',
            'grouping_column' => null,
        ]);
        \App\Filterable::create([
            'table' => 'tasks',
            'column' => 'store',
            'column_type' => 'string',
            'relation_to_tasks' => 'self',
            'grouping_column' => null,
        ]);
        \App\Filterable::create([
            'table' => 'tasks',
            'column' => 'coin_reward',
            'column_type' => 'integer',
            'relation_to_tasks' => 'self',
            'grouping_column' => null,
        ]);
        \App\Filterable::create([
            'table' => 'country_tasks',
            'column' => 'payable_price',
            'column_type' => 'double',
            'relation_to_tasks' => 'belongs_to',
            'grouping_column' => 'currency',
            'relation_name' => 'prices',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('filterables');
    }
}
