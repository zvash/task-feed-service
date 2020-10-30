<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Banner extends Model
{

    protected $fillable = [
        'group_id',
        'name',
        'image'
    ];

    public static function makeBanner(int $groupId, string $name, string $image)
    {
        try {
            DB::beginTransaction();
            $banner = Banner::create([
                'group_id' => $groupId,
                'name' => $name,
                'image' => $image
            ]);
            DB::commit();
            return $banner;
        } catch (\Exception $exception) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'banner_tags');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function countries()
    {
        return $this->belongsToMany(Country::class, 'banner_countries')->withPivot([
            'currency'
        ]);
    }

    /**
     * @param $value
     * @return string
     */
    public function getImageAttribute($value)
    {
        if ($value) {
            return rtrim(env('APP_URL'), '/') . '/' . $value;
        }
        return $value;
    }
}
