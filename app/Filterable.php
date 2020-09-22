<?php

namespace App;

use Illuminate\Support\Str;
use App\Exceptions\ServiceException;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string relation_to_tasks
 * @property string column
 * @property string grouping_column
 * @property string relation_name
 */
class Filterable extends Model
{
    protected $fillable = ['table', 'column', 'column_type', 'relation_to_tasks', 'grouping_column', 'relation_name'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function filters()
    {
        return $this->hasMany(Filter::class);
    }

    /**
     * @return string
     * @throws ServiceException
     */
    public function getModelClass()
    {
        $className = 'App\\' . Str::studly(Str::singular($this->getAttribute('table')));
        if (class_exists($className)) {
            return $className;
        }
        throw new ServiceException('Model (' . $className . ') was not found', [
            'message' => 'Model (' . $className . ') was not found'
        ]);
    }
}
