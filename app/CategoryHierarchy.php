<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CategoryHierarchy extends Model
{
    protected $table = 'category_hierarchies';
    protected $fillable = ['parent_id', 'child_id'];
}
