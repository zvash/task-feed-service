<?php

namespace App\Repositories;


use App\Tag;
use App\Task;
use App\Country;
use App\Traits\TaskFilterApplier;
use Illuminate\Database\Eloquent\Builder;

class SearchRepository
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
     * @return SearchRepository
     */
    public function setCurrencies(array $currencies)
    {
        $this->currencies = $currencies;
        return $this;
    }

    /**
     * @param array $countries
     * @return SearchRepository
     */
    public function setCountries(array $countries)
    {
        $this->countries = $countries;
        return $this;
    }

    /**
     * @param array $filters
     * @return SearchRepository
     */
    public function setFilters(array $filters)
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @param string $text
     * @param int $paginate
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function searchTasksByText(string $text, int $paginate = 0)
    {
        $tagIds = $this->searchTextInTags($text);
        $query = Task::query();
        $query->where('expires_at', '>', date('Y-m-d'))
            ->where(function ($task) use ($text, $tagIds) {
                $task->where('title', 'like', "%$text%")
                    ->orWhere('store', 'like', "%$text%")
                    ->orWhere('description', 'like', "%$text%")
                    ->orWhere('custom_attributes', 'like', "%$text%")
                    ->orWhereHas('tags', function ($tags) use ($tagIds) {
                        $tags->whereIn('tag_task.tag_id', $tagIds);
                    })
                    ->orWhereHas('category', function ($category) use ($text) {
                        $category->where('name', 'like', "%$text%");
                    });
            })
            ->with('images');

        $query = $this->addTaskCurrenciesWhereClause($query);
        $query = $this->addTaskCountriesWhereClause($query);
        $query = $this->includePrices($query);

        $this->lastQuery = $query;

        $query = $this->applyFilters();

        if ($paginate) {
            return $query->paginate($paginate);
        } else {
            return $query->get();
        }

    }

    /**
     * @param string $text
     * @return mixed
     */
    private function searchTextInTags(string $text)
    {
        $tagIds = Tag::where('name', 'like', "%$text%")
            ->orWhere('display_name', 'like', "%$text%")
            ->pluck('id')
            ->toArray();
        return $tagIds;
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