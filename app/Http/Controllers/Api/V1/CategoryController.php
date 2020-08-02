<?php

namespace App\Http\Controllers\Api\V1;

use App\Category;
use App\Task;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'unique:categories'],
            'image' => 'mimes:jpeg,jpg,png',
            'parent_id' => 'integer|min:1|exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' => $validator->errors(), 'status' => false], 422);
        }
        $name = $request->get('name');
        $path = null;
        if ($request->hasFile('image')) {
            $publicImagesPath = rtrim(env('PUBLIC_IMAGES_PATH', 'public/images'), '/') . '/';
            $file = $request->file('image');
            $path = preg_replace(
                '#public/#',
                'storage/',
                Storage::putFile($publicImagesPath, $file)
            );
        }
        $parentId = $request->exists('parent_id') ? $request->get('parent_id') : 1;
        $category = Category::makeCategory($name, $parentId, $path);
        return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => $category], 200);
    }

    /**
     * @param Request $request
     * @param int $categoryId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function tasks(Request $request, int $categoryId)
    {
        $category = Category::find($categoryId);
        $tasks = [];
        if ($category) {
            $descendantIds = $category->getDescendantsIds();
            $tasks = Task::whereIn('category_id', $descendantIds)->get()->toArray();
        }
        return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => $tasks], 200);
    }
}
