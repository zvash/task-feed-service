<?php

namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'parent_id', 'image', 'svg', 'is_main'];

    protected $hidden = ['created_at', 'updated_at'];

    /**
     * @param string $name
     * @param int $parentId
     * @param string|null $image
     * @param string|null $svg
     * @param bool $isMain
     * @return bool
     */
    public static function makeCategory(string $name, int $parentId = 1, string $image = null, string $svg = null, bool $isMain = false)
    {
        try {
            DB::beginTransaction();
            $category = Category::create(['name' => $name, 'parent_id' => $parentId, 'image' => $image, 'svg' => $svg, 'is_main' => $isMain]);
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subCategories()
    {
        return $this->hasMany(Category::class, 'parent_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id', 'id');
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

    /**
     * @param $value
     * @return string
     */
    public function getImageAttribute($value)
    {
        if ($value) {
            return rtrim(env('APP_URL'), '/') . '/' . $value;
        }
        return $value;
    }

    /**
     * @param $value
     * @return string
     */
    public function getSvgAttribute($value)
    {
        if ($value) {
            return rtrim(env('APP_URL'), '/') . '/' . $value;
        }
        return $value;
    }
}
