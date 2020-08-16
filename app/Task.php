<?php

namespace App;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{

    protected $fillable = [
        'title',
        'category_id',
        'offer_id',
        'currency',
        'original_price',
        'payable_price',
        'has_shipment',
        'shipment_price',
        'destination_url',
        'coupon_code',
        'expires_at',
        'description',
        'coin_reward',
        'custom_attributes',
        'image',
        'token',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    /**
     * @param int $length
     * @return string
     */
    public static function generateToken($length = 5)
    {
        $token = Str::random($length);
        if (Task::where('token', $token)->first()) {
            return static::generateToken($length);
        }
        return $token;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany(TaskImage::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'tag_task');
    }

    /**
     * @param string $value
     * @return bool|string
     */
    public function getDestinationUrlAttribute(string $value)
    {
        return base64_decode($value);
    }
}
