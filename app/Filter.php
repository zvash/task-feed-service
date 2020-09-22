<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string selection_type
 * @property int filterable_id
 * @property Filterable filterable
 * @property string name
 */
class Filter extends Model
{
    protected $fillable = [
        'filterable_id',
        'name',
        'selection_type',
        'acceptable_values',
        'is_active',
        'order',
    ];
    protected $hidden = [
        'created_at',
        'updated_at',
        'acceptable_values',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function filterable()
    {
        return $this->belongsTo(Filterable::class);
    }
}
