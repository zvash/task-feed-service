<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GroupTag extends Model
{
    protected $table = 'group_tags';

    protected $fillable = ['group_id', 'tag_id'];
}
