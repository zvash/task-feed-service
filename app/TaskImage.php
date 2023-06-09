<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskImage extends Model
{

    protected $fillable = ['task_id', 'url'];

    protected $hidden = ['created_at', 'updated_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @param $value
     * @return string
     */
    public function getUrlAttribute($value)
    {
        if ($value) {
            return rtrim(env('APP_URL'), '/') . '/' . $value;
        }
        return $value;
    }
}
