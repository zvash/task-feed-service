<?php

namespace App\Repositories;


use App\Tag;
use App\Task;
use App\Country;
use App\Category;
use App\Traits\TaskFilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryRepository
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
     * @param array $filters
     * @return CategoryRepository
     */
    public function setFilters(array $filters)
    {
        $this->filters = $filters;
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
     * @param int $parentId
     * @return Builder[]|Collection
     */
    public function getSubCategories(int $parentId)
    {
        $query = Category::query();
        $query = $query->where('parent_id', $parentId);
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

        $this->lastQuery = $query;

        $query = $this->applyFilters();

        if ($paginate) {
            return $query->paginate($paginate);
        } else {
            return $query->get();
        }
    }

    /**
     * @param Category $category
     * @param string $text
     * @param int $paginate
     * @return LengthAwarePaginator|Builder[]|Collection
     */
    public function searchByText(Category $category, string $text, int $paginate = 0)
    {
        $descendantIds = $category->getDescendantsIds();
        $tagIds = $this->searchTextInTags($text);
        $query = Task::query();
        $query = $query->whereIn('category_id', $descendantIds)
            ->with('images')
            ->where('expires_at', '>', date('Y-m-d'))
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

        return $query->where(function ($query) use ($countryIds) {
            return $query->whereHas('tasks', function ($query) use ($countryIds) {
                return $query->where('expires_at', '>', date('Y-m-d'))
                    ->whereHas('prices', function ($prices) use ($countryIds) {
                        return $prices->whereIn('country_tasks.country_id', $countryIds);
                    });
            })->orWhereHas('children', function ($query) use ($countryIds) {
                return $query->whereHas('tasks', function ($query) use ($countryIds) {
                    return $query->where('expires_at', '>', date('Y-m-d'))
                        ->whereHas('prices', function ($prices) use ($countryIds) {
                            return $prices->whereIn('country_tasks.country_id', $countryIds);
                        });
                });
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
}