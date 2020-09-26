<?php

namespace App\Repositories;

use App\Country;
use App\Task;
use Illuminate\Database\Eloquent\Builder;

class TaskRepository
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
     * @return TaskRepository
     */
    public function setCurrencies(array $currencies)
    {
        $this->currencies = $currencies;
        return $this;
    }

    /**
     * @param array $countries
     * @return TaskRepository
     */
    public function setCountries(array $countries)
    {
        $this->countries = $countries;
        return $this;
    }

    /**
     * @param int $taskId
     * @return Builder|\Illuminate\Database\Eloquent\Model|object
     */
    public function getTask(int $taskId)
    {
        $query = Task::query();
        $query->where('id', $taskId)
            ->with('images')
            ->where('expires_at', '>', date('Y-m-d'));

        $query = $this->addTaskCurrenciesWhereClause($query);
        $query = $this->addTaskCountriesWhereClause($query);
        $query = $this->includePrices($query);
        return $query->first();
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