<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed country_id
 */
class CountryTask extends Model
{
    protected $table = 'country_tasks';

    protected $appends = ['country_name', 'discount'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $fillable = [
        'country_id',
        'task_id',
        'currency',
        'original_price',
        'payable_price',
        'has_shipment',
        'shipment_price'
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

    /**
     * @return int
     */
    public function getDiscountAttribute()
    {
        if ($this->original_price > 0) {
            return 100 - intval(round($this->payable_price / $this->original_price)) * 100;
        }
        return 0;
    }
}
