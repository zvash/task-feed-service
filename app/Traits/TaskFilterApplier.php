<?php

namespace App\Traits;

use App\Country;
use App\Exceptions\ServiceException;
use App\Task;
use App\Filter;
use App\Filterable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

trait TaskFilterApplier
{
    /**
     * @return array
     */
    public function filterOptions()
    {
        $query = $this->makeQuery($this->lastQuery);
        $filters = Filter::where('is_active', true)->get();
        $options = [];
        foreach ($filters as $filter) {
            $this->appendOptionIfNotNull($options, $this->makeSelectableFilterOption($query, $filter));
            $this->appendOptionIfNotNull($options, $this->makeRangeFilterOption($query, $filter));
        }
        return $options;
    }

    /**
     * @return Builder
     */
    private function applyFilters(): Builder
    {
        if ($this->filters) {
            $filterNames = array_keys($this->filters);
            $query = clone $this->lastQuery;
            foreach ($filterNames as $filterName) {
                $filter = $this->getFilter($filterName);
                if ($filter) {
                    $query = $this->applyFilterToQuery($query, $filter, $this->filters[$filterName]);
                }
            }
            return $query;
        }
        return $this->lastQuery;
    }

    /**
     * @param Builder $query
     * @param Filter $filter
     * @param array $filterValues
     * @return Builder
     */
    private function applyFilterToQuery(Builder $query, Filter $filter, array $filterValues)
    {
        $filterable = $filter->filterable;
        if ($filterable->relation_to_tasks == 'self') {

            if ($filter->selection_type == 'range') {
                if (array_key_exists('min', $filterValues)) {
                    $query = $query->where($filterable->column, '>=', $filterValues['min']);
                }
                if (array_key_exists('max', $filterValues)) {
                    $query = $query->where($filterable->column, '<=', $filterValues['min']);
                }
                return $query;
            } else {
                $filterValues = Task::whereIn('id', $filterValues)->pluck($filterable->column)->toArray();
                return $query->whereIn($filterable->column, $filterValues);
            }

        } else if ($filterable->relation_to_tasks == 'belongs_to') {

            if ($filter->selection_type == 'range') {
                if ($filterable->grouping_column) {
                    $query = $query->whereHas($filterable->relation_name, function ($relation) use ($filterable, $filterValues) {
                        $relationColumn = $filterable->getAttribute('table') . '.' . $filterable->column;
                        $groupingColumn = $filterable->getAttribute('table') . '.' . $filterable->grouping_column;
                        foreach ($filterValues as $key => $values) {
                            $relation = $relation->where($groupingColumn, $key);
                            if (array_key_exists('min', $values)) {
                                $relation = $relation->where($relationColumn, '>=', $values['min']);
                            }
                            if (array_key_exists('max', $values)) {
                                $relation = $relation->where($relationColumn, '<=', $values['max']);
                            }
                        }
                        return $relation;
                    });
                    return $query;
                } else {
                    $query = $query->whereHas($filterable->relation_name, function ($relation) use ($filterable, $filterValues) {
                        $relationColumn = $filterable->getAttribute('table') . '.' . $filterable->column;
                        foreach ($filterValues as $values) {
                            if (array_key_exists('min', $values)) {
                                $relation = $relation->where($relationColumn, '>=', $values['min']);
                            }
                            if (array_key_exists('max', $values)) {
                                $relation = $relation->where($relationColumn, '<=', $values['max']);
                            }
                        }
                        return $relation;
                    });
                    return $query;
                }
            } else {
                $query = $query->whereHas($filterable->relation_name, function ($relation) use ($filterable, $filterValues) {
                    $relationColumn = $filterable->getAttribute('table') . '.' . $filterable->column;
                    $relation = $relation->whereIn($relationColumn, $filterValues);
                    return $relation;
                });
                return $query;
            }
        } else if ($filterable->relation_to_tasks == 'has_many') {
            try {
                $relationColumn = Str::singular($filterable->getAttribute('table')) . '_id';
                return $query->whereIn($relationColumn, $filterValues);
            } catch (ServiceException $e) {
            }
        }
    }

    /**
     * @param string $filterName
     * @return Filter|null
     */
    private function getFilter(string $filterName)
    {
        $filter = Filter::where('name', $filterName)->first();
        return $filter;
    }

    /**
     * @param Builder $query
     * @param Filter $filter
     * @return array|null
     */
    private function makeSelectableFilterOption(Builder $query, Filter $filter)
    {
        if ($filter->selection_type == 'range') {
            return null;
        }
        $filterable = $filter->filterable;
        $query = $this->addWhereClauseByRelation($query, $filterable);
        $columnName = $filterable->column;
        $query = $query->select(['id', $columnName]);
        $result = $query->get()->toArray();
        $data = [];
        foreach ($result as $item) {
            $data[] = [
                'key' => "filters[{$filter->name}][]",
                'value' => $item['id'],
                'display_value' => $item[$columnName],
            ];
        }
        $option = [];
        if ($data) {
            $option = [
                'filter_name' => $filter->name,
                'selection_type' => $filter->selection_type,
                'data' => $data
            ];
        }

        return $option;
    }

    /**
     * @param Builder $query
     * @param Filter $filter
     * @return array|null
     */
    private function makeRangeFilterOption(Builder $query, Filter $filter)
    {
        if ($filter->selection_type != 'range') {
            return null;
        }
        $filterable = $filter->filterable;
        $query = $this->addWhereClauseByRelation($query, $filterable);
        $columnName = $filterable->column;
        $minColumnName = 'min_' . $columnName;
        $maxColumnName = 'max_' . $columnName;
        $rawSelect = "min(`$columnName`) as $minColumnName, max(`$columnName`) as $maxColumnName";
        if ($filterable->grouping_column) {
            $rawSelect = $filterable->grouping_column . ', ' . $rawSelect;
        }
        $query = $query->selectRaw($rawSelect);
        $hasVariations = false;
        if ($filterable->grouping_column) {
            $query = $query->groupBy($filterable->grouping_column);
            $hasVariations = true;
        }
        $result = $query->get()->toArray();
        $option = [];
        if ($hasVariations) {
            foreach ($result as $row) {
                if ($row[$minColumnName] === null || $row[$maxColumnName] === null) {
                    continue;
                }
                $minValue = $this->roundValue($row[$minColumnName], 'down');
                $maxValue = $this->roundValue($row[$maxColumnName], 'up');
                $option[] = [
                    'filter_name' => $filter->name . " ({$row[$filterable->grouping_column]})",
                    'selection_type' => $filter->selection_type,
                    'data' => [
                        [
                            'key' => "filters[{$filter->name}][{$row[$filterable->grouping_column]}][min]",
                            'value' => $minValue,
                            'display_value' => $minValue,
                        ],
                        [
                            'key' => "filters[{$filter->name}][{$row[$filterable->grouping_column]}][max]",
                            'value' => $maxValue,
                            'display_value' => $maxValue,
                        ],
                    ]
                ];
            }
        } else {
            foreach ($result as $row) {
                if ($row[$minColumnName] === null || $row[$maxColumnName] === null) {
                    continue;
                }
                $minValue = $this->roundValue($row[$minColumnName], 'down');
                $maxValue = $this->roundValue($row[$maxColumnName], 'up');
                $option = [
                    'filter_name' => $filter->name,
                    'selection_type' => $filter->selection_type,
                    'data' => [
                        [
                            'key' => "filters[{$filter->name}][min]",
                            'value' => '',
                            'display_value' => $minValue,
                        ],
                        [
                            'key' => "filters[{$filter->name}][max]",
                            'value' => '',
                            'display_value' => $maxValue,
                        ],
                    ]
                ];
            }
        }

        return $option;
    }

    /**
     * @param array $options
     * @param array|null $option
     */
    private function appendOptionIfNotNull(array &$options, ?array $option)
    {
        if ($option) {
            if (is_array($option) && array_keys($option) === range(0, count($option) - 1)) {
                foreach ($option as $item) {
                    $options[] = $item;
                }
            } else {
                $options[] = $option;
            }
        }
    }

    /**
     * @param Builder $query
     * @param Filterable $filterable
     * @return Builder
     */
    private function addWhereClauseByRelation(Builder $query, Filterable $filterable)
    {
        if ($filterable->relation_to_tasks == 'self') {
            return $query;
        }
        if ($filterable->relation_to_tasks == 'belongs_to') {
            $taskIds = $query->pluck('id')->toArray();
            try {
                $modelClass = $filterable->getModelClass();
                $relationQuery = $modelClass::query();
                $fields = (new $modelClass())->getFillable();
                if (in_array('country_id', $fields) && $this->countries) {
                    $userCountries = $this->countries;
                    $countryIds = Country::whereIn('name', $userCountries)
                        ->orWhereIn('alpha3_name', $userCountries)
                        ->orWhere('name', 'ALL')
                        ->pluck('id')
                        ->toArray();
                    $relationQuery = $relationQuery->whereIn('country_id', $countryIds);
                }
                if ($taskIds) {
                    return $relationQuery->whereIn('task_id', $taskIds);
                } else {
                    return $relationQuery->where('task_id', -1);
                }
            } catch (ServiceException $exception) {

            }
        }
        if ($filterable->relation_to_tasks == 'has_many') {
            $relationColumn = Str::singular($filterable->getAttribute('table')) . '_id';
            $ids = $query->pluck($relationColumn)->toArray();
            try {
                $modelClass = $filterable->getModelClass();
                $relationQuery = $modelClass::query();
                $fields = (new $modelClass())->getFillable();
                if (in_array('country_id', $fields) && $this->countries) {
                    $userCountries = $this->countries;
                    $countryIds = Country::whereIn('name', $userCountries)
                        ->orWhereIn('alpha3_name', $userCountries)
                        ->orWhere('name', 'ALL')
                        ->pluck('id')
                        ->toArray();
                    $relationQuery = $relationQuery->whereIn('country_id', $countryIds);
                }
                if ($ids) {
//                    if (method_exists(new $modelClass(), 'getDescendantsIds')) {
//                        $allIds = [];
//                        foreach ($ids as $id) {
//                            $model = $modelClass::find($id);
//                            $allIds = array_merge($allIds, $model->getDescendantsIds());
//                        }
//                        $ids = $allIds;
//                    }
                    return $relationQuery->whereIn('id', $ids);
                }
                return $relationQuery->where('id', -1);
            } catch (ServiceException $exception) {

            }
        }
        return $query;
    }

    /**
     * @param Builder|null $query
     * @return Builder
     */
    private function makeQuery(?Builder $query): Builder
    {
        if (!$query) {
            $query = Task::query();
            return $query->where('expires_at', '>', date('Y-m-d'));
        }
        $modelClass = $query->getModel();
        if (get_class($modelClass) == Task::class) {
            return $query;
        }
        $task = new Task();
        $taskFields = $task->getFillable();
        $classNameParts = explode('\\', $modelClass);
        $className = end($classNameParts);
        $foreignKey = Str::snake($className) . '_id';
        if (in_array($foreignKey, $taskFields)) {
            $relationIds = $query->pluck('id')->toArray();
            $query = Task::query();
            $query = $query
                ->where('expires_at', '>', date('Y-m-d'))
                ->whereIn($foreignKey, $relationIds);
            return $query;
        }
        $fields = (new $modelClass())->getFillable();
        if (in_array('task_id', $fields)) {
            $taskIds = $query->pluck('task_id')->toArray();
            $query = Task::query();
            $query = $query
                ->where('expires_at', '>', date('Y-m-d'))
                ->whereIn('id', $taskIds);
            return $query;
        }
        return $query;
    }

    /**
     * @param float $value
     * @param string $mode
     * @return int
     */
    private function roundValue(float $value, string $mode)
    {
        $value = ceil($value);
        if ($mode == 'up') {
            return intval(ceil($value / 10) * 10);
        }
        if ($mode == 'down') {
            return intval(floor($value / 10) * 10);
        }
        return intval($value);
    }
}