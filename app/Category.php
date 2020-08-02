<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'image'];

    /**
     * @param string $name
     * @param int $parentId
     * @param string|null $image
     * @return bool
     */
    public static function makeCategory(string $name, int $parentId = 1, string $image = null)
    {
        try {
            DB::beginTransaction();
            $category = Category::create(['name' => $name, 'image' => $image]);
            $ascendantIds = CategoryHierarchy::where('child_id', $parentId)->pluck('parent_id')->toArray();
            $ascendantIds[] = $parentId;
            $rows = [];
            foreach ($ascendantIds as $parentId) {
                $rows[] = ['parent_id' => $parentId, 'child_id' => $category->id];
            }
            CategoryHierarchy::insert($rows);
            DB::commit();
            return $category;
        } catch (\Exception $exception) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * @return mixed
     */
    public function getPathToRoot()
    {
        $ids = CategoryHierarchy::where('child_id', $this->id)->pluck('parent_id')->toArray();
        $ids[] = $this->id;
        $categories = Category::whereIn('id', $ids)->orderBy('id')->toArray();
        return $categories;
    }

    /**
     * @return array
     */
    public function getDescendantsIds()
    {
        $ids = [$this->id];
        $ids = array_merge($ids, CategoryHierarchy::where('parent_id', $this->id)->pluck('child_id')->toArray());
        return $ids;
    }

    /**
     * @return mixed
     */
    public function getDescendants()
    {
        $ids = $this->getDescendantsIds();
        $categories = Category::whereIn('id', $ids)->orderBy('id')->get()->toArray();
        return $categories;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function children()
    {
        return $this->belongsToMany(Category::class, 'category_hierarchies', 'parent_id', 'child_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function parents()
    {
        return $this->belongsToMany(Category::class, 'category_hierarchies', 'child_id', 'parent_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
