<?php

namespace App\Repositories;

use App\Banner;
use App\Task;
use App\Group;
use App\Country;
use App\TagTask;
use Illuminate\Database\Eloquent\Builder;
use App\Exceptions\GroupNotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BannerRepository
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
     * @return BannerRepository
     */
    public function setCurrencies(array $currencies)
    {
        $this->currencies = $currencies;
        return $this;
    }

    /**
     * @param array $countries
     * @return BannerRepository
     */
    public function setCountries(array $countries)
    {
        $this->countries = $countries;
        return $this;
    }

    /**
     * @param Banner $banner
     * @param int $paginate
     * @return LengthAwarePaginator|Builder[]|Collection
     */
    public function getTasks(Banner $banner, int $paginate = 0)
    {
        $tagIds = $banner->tags->pluck('id')->toArray();
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