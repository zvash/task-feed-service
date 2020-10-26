<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BannerCountry extends Model
{
    protected $table = 'banner_countries';

    protected $appends = ['country_name'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'banner_id',
        'country_id',
        'currency',
    ];

    /**
     * @return mixed|null
     */
    public function getCountryNameAttribute()
    {
        if (!$this->country_id) {
            return null;
        }
        $country = Country::find($this->country_id);
        return $country->name;
    }
}
