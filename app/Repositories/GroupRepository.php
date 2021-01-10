<?php

namespace App\Repositories;

use App\Tag;
use App\Task;
use App\Group;
use App\Banner;
use App\Country;
use App\TagTask;
use App\Traits\TaskFilterApplier;
use Illuminate\Database\Eloquent\Builder;
use App\Exceptions\GroupNotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GroupRepository
{
    use TaskFilterApplier;

    /**
     * @var array $currencies
     */
    protected $currencies = [];

    /**
     * @var array $countries
     */
    protected $countries = [];

    /**
     * @var Builder|null $lastQuery
     */
    protected $lastQuery = null;

    /**
     * @var array $filters
     */
    protected $filters = [];

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

    /**
     * @param array $filters
     * @return GroupRepository
     */
    public function setFilters(array $filters)
    {
        $this->filters = $filters;
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
        } else if ($group->type == 'banners') {
            $query = Banner::query();
            $query = $query->where('group_id', $group->id);
            $query = $this->addBannerCountriesWhereClause($query);
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
     * @param Group $group
     * @param string $text
     * @param int $paginate
     * @return LengthAwarePaginator|Builder[]|Collection
     */
    public function searchByText(Group $group, string $text, int $paginate = 0)
    {
        $tagIds = $group->tags->pluck('id')->toArray();
        $query = Task::query();
        $query = $query->where('expires_at', '>', date('Y-m-d'))
            ->where(function ($task) use ($text, $tagIds) {
                $task->whereHas('tags', function ($tags) use ($tagIds) {
                    $tags->whereIn('tag_task.tag_id', $tagIds);
                })->where(function ($query) use ($text) {
                    return $query->where('title', 'like', "%$text%")
                        ->orWhere('store', 'like', "%$text%")
                        ->orWhere('description', 'like', "%$text%")
                        ->orWhere('custom_attributes', 'like', "%$text%")
                        ->orWhereHas('category', function ($category) use ($text) {
                            $category->where('name', 'like', "%$text%");
                        });
                });

            })
            ->with('images');
        $query = $this->withTaskCurrencies($query);
        $query = $this->withTaskCountries($query);
        $query = $this->includePrices($query);

        $this->lastQuery = clone $query;

        $query = $this->applyFilters();

        if ($paginate) {
            return $query->paginate($paginate);
        } else {
            return $query->get();
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

    /**
     * @param Builder $query
     * @return Builder
     */
    private function addBannerCountriesWhereClause(Builder $query): Builder
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

        return $query->whereHas('countries', function ($countries) use ($countryIds) {
            return $countries->whereIn('banner_countries.country_id', $countryIds);
        })->with(['countries' => function ($query) use ($countryIds) {
            return $query->whereIn('banner_countries.country_id', $countryIds);
        }]);
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    private function withTaskCurrencies(Builder $query): Builder
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
    private function withTaskCountries(Builder $query): Builder
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
        }]);;
    }
}