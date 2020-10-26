<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BannerTag extends Model
{
    protected $table = 'banner_tags';

    protected $fillable = ['banner_id', 'tag_id'];
}
