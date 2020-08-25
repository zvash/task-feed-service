<?php

namespace App\Repositories;


use App\Task;
use App\Country;
use App\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryRepository
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
     * @return CategoryRepository
     */
    public function setCurrencies(array $currencies)
    {
        $this->currencies = $currencies;
        return $this;
    }

    /**
     * @param array $countries
     * @return CategoryRepository
     */
    public function setCountries(array $countries)
    {
        $this->countries = $countries;
        return $this;
    }

    /**
     * @return Builder[]|Collection
     */
    public function getAllMainCategories()
    {
        $query = Category::query();
        $query = $query->where('is_main', true);
        $query = $this->hasCurrencies($query);
        $query = $this->hasCountries($query);
        return $query->get();
    }

    /**
     * @param Category $category
     * @param int $paginate
     * @return LengthAwarePaginator|Builder[]|Collection
     */
    public function getTasks(Category $category, int $paginate = 0)
    {
        $descendantIds = $category->getDescendantsIds();
        $query = Task::query();
        $query = $query->whereIn('category_id', $descendantIds)
            ->with('images')
            ->where('expires_at', '>', date('Y-m-d'));
        $query = $this->withTaskCurrencies($query);
        $query = $this->withTaskCountries($query);
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
    private function hasCurrencies(Builder $query): Builder
    {
        if (!$this->currencies) {
            return $query;
        }
        $userCurrencies = $this->currencies;
        return $query->whereHas('tasks', function ($query) use ($userCurrencies) {
            return $query->where('expires_at', '>', date('Y-m-d'))
                ->whereHas('prices', function ($prices) use ($userCurrencies) {
                    return $prices->whereIn('country_tasks.currency', $userCurrencies);
                });
        });
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    private function hasCountries(Builder $query): Builder
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

        return $query->whereHas('tasks', function ($query) use ($countryIds) {
            return $query->where('expires_at', '>', date('Y-m-d'))
                ->whereHas('prices', function ($prices) use ($countryIds) {
                    return $prices->whereIn('country_tasks.country_id', $countryIds);
                });
        });
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