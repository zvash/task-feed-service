<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskImage extends Model
{

    protected $fillable = ['task_id', 'url'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
