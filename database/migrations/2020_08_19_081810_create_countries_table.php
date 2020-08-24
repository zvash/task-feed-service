<?php

use App\Country;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCountriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index()->unique();
            $table->string('alpha3_name')->index()->unique();
            $table->timestamps();
        });

        $countries = config('countries');
        $toInsert = [];
        $toInsert[] = ['name' => 'ALL', 'alpha3_name' => 'ALL'];
        foreach ($countries as $country) {
            $toInsert[] = ['name' => $country['name'], 'alpha3_name' => $country['alpha3']];
        }
        Country::insert($toInsert);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('countries');
    }
}
