<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CategoryHierarchy extends Model
{
    protected $table = 'category_hierarchies';

    protected $fillable = ['parent_id', 'child_id'];

    /**
     * create category hierarchy
     */
    public static function createHierarchy()
    {
        CategoryHierarchy::where('id', '>', 0)->delete();
        $categories = Category::orderBy('id')->get()->toArray();
        $categoriesParentById = [];
        foreach ($categories as $category) {
            if ($category['id'] != $category['parent_id']) {
                $categoriesParentById[$category['id']] = $category['parent_id'];
            }
        }
        $map = [];
        foreach ($categories as $category) {
            $map[$category['id']] = static::parents($category['id'], $categoriesParentById);
        }
        $toInsert = [];
        foreach ($map as $childId => $parentIds) {
            foreach ($parentIds as $parentId) {
                $toInsert[] = [
                    'parent_id' => $parentId,
                    'child_id' => $childId
                ];
            }
        }
        CategoryHierarchy::insert($toInsert);
    }

    /**
     * @param int $id
     * @param array $categoriesParentById
     * @return array
     */
    private static function parents(int $id, array $categoriesParentById)
    {
        $parents = [];
        if (array_key_exists($id, $categoriesParentById)) {
            $parents[] = $categoriesParentById[$id];
            $parents = array_merge(static::parents($categoriesParentById[$id], $categoriesParentById), $parents);
        }
        return $parents;
    }
}
