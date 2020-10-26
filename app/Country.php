<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['name', 'alpha3_name'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'country_tasks')->withPivot([
            'currency',
            'original_price',
            'payable_price',
            'has_shipment',
            'shipment_price'
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function banners()
    {
        return $this->belongsToMany(Banner::class, 'banner_countries')->withPivot([
            'currency'
        ]);
    }
}
