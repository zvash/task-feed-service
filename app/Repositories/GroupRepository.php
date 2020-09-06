<?php

namespace App\Repositories;

use App\Task;
use App\Group;
use App\Country;
use App\TagTask;
use Illuminate\Database\Eloquent\Builder;
use App\Exceptions\GroupNotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GroupRepository
{

    /**
     * @var array $currencies
     */
    protected $currencies = [];

    /**
     * @var array $countries
     */
    protected $countries = [];

    /**
     * @param array $currencies
     * @return GroupRepository
     */
    public function setCurrencies(array $currencies)
    {
        $this->currencies = $currencies;
        return $this;
    }

    /**
     * @param array $countries
     * @return GroupRepository
     */
    public function setCountries(array $countries)
    {
        $this->countries = $countries;
        return $this;
    }

    public function reorder(array $orders)
    {
        $groupIds = array_keys($orders);
    }

    /**
     * @param int $groupId
     * @param int $limit
     * @param int $page
     * @return array
     * @throws GroupNotFoundException
     */
    public function getItems(int $groupId, int $limit = 0, int $page = 1)
    {
        $group = Group::find($groupId);
        if (!$group) {
            throw new GroupNotFoundException('Group was not found.', [
                'message' => 'Group was not found.',
                'id' => $groupId
            ]);
        }
        $tagIds = $group->tags->pluck('id')->toArray();

        if ($group->type == 'tasks') {
            $taskIds = TagTask::whereIn('tag_id', $tagIds)
                ->pluck('task_id')
                ->unique()
                ->toArray();

            $query = Task::query();
            $query->whereIn('id', $taskIds)
                ->with('images')
                ->where('expires_at', '>', date('Y-m-d'));

            $query = $this->addTaskCurrenciesWhereClause($query);
            $query = $this->addTaskCountriesWhereClause($query);
            $query = $this->includePrices($query);

            if ($limit) {
                $query->skip($limit * ($page - 1))
                    ->limit($limit);
            }
            return $query->get()
                ->toArray();

        }
        return [];
    }

    /**
     * @param Group $group
     * @param int $paginate
     * @return LengthAwarePaginator|Builder[]|Collection
     */
    public function getGroupItems(Group $group, int $paginate = 0)
    {
        $tagIds = $group->tags->pluck('id')->toArray();
        if ($group->type == 'tasks') {
            $taskIds = TagTask::whereIn('tag_id', $tagIds)
                ->pluck('task_id')
                ->unique()
                ->toArray();

            $query = Task::query();
            $query->whereIn('id', $taskIds)
                ->with('images')
                ->where('expires_at', '>', date('Y-m-d'));

            $query = $this->addTaskCurrenciesWhereClause($query);
            $query = $this->addTaskCountriesWhereClause($query);
            $query = $this->includePrices($query);

            if ($paginate) {
                return $query->paginate($paginate);
            } else {
                return $query->get();
            }
        }
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    private function addTaskCurrenciesWhereClause(Builder $query): Builder
    {
        if (!$this->currencies) {
            return $query;
        }
        $userCurrencies = $this->currencies;
        return $query->whereHas('prices', function ($prices) use ($userCurrencies) {
            return $prices->whereIn('country_tasks.currency', $userCurrencies);
        })->with(['prices' => function ($query) use ($userCurrencies) {
            return $query->whereIn('country_tasks.currency', $userCurrencies);
        }]);
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    private function addTaskCountriesWhereClause(Builder $query): Builder
    {
        if (!$this->countries) {
            return $query;
        }
        $userCountries = $this->countries;

        $countryIds = Country::whereIn('name', $userCountries)
            ->orWhereIn('alpha3_name', $userCountries)
            ->orWhere('name', 'ALL')
            ->pluck('id')
            ->toArray();

        return $query->whereHas('prices', function ($prices) use ($countryIds) {
            return $prices->whereIn('country_tasks.country_id', $countryIds);
        })->with(['prices' => function ($query) use ($countryIds) {
            return $query->whereIn('country_tasks.country_id', $countryIds);
        }]);
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    private function includePrices(Builder $query): Builder
    {
        if (!$this->countries && !$this->currencies) {
            return $query->with('prices');
        }
        return $query;
    }
}