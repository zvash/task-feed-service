<?php

namespace App\Http\Controllers\Api\V1;

use App\Repositories\CategoryRepository;
use App\Task;
use App\Category;
use App\Traits\ResponseMaker;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    use ResponseMaker;

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'image' => 'mimes:jpeg,jpg,png',
            'svg' => 'mimes:svg',
            'is_main' => 'boolean',
            'parent_id' => 'integer|min:1|exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' => $validator->errors(), 'status' => false], 422);
        }
        $name = $request->get('name');
        $path = $this->saveImage($request, 'image');
        $svgPath = $this->saveImage($request, 'svg');
        $parentId = $request->exists('parent_id') ? $request->get('parent_id') : 1;
        $isMain = $request->exists('is_main') ? $request->get('is_main') : false;
        $category = Category::makeCategory($name, $parentId, $path, $svgPath, $isMain);
        return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => $category], 200);
    }

    /**
     * @param Request $request
     * @param int $parentId
     * @param CategoryRepository $categoryRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getSubCategories(Request $request, int $parentId, CategoryRepository $categoryRepository)
    {
        $user = Auth::user();
        if ($user) {
            $categoryRepository->setCountries([$user->country]);
        }
        $categories = $categoryRepository->getSubCategories($parentId);
        if ($categories) {
            return $this->success($categories);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $categoryId
     * @param CategoryRepository $categoryRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function tasks(Request $request, int $categoryId, CategoryRepository $categoryRepository)
    {
        $user = Auth::user();
        if ($user) {
            $categoryRepository->setCountries([$user->country]);
        }
        $category = Category::find($categoryId);
        $tasks = [];
        if ($category) {
            $tasks = $categoryRepository->getTasks($category, 10);
            return $this->success($tasks);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param CategoryRepository $categoryRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getMain(Request $request, CategoryRepository $categoryRepository)
    {
        $user = Auth::user();
        if ($user) {
            $categoryRepository->setCountries([$user->country]);
        }
        $categories = $categoryRepository->getAllMainCategories();
        return $this->success($categories);
    }

    /**
     * @param Request $request
     * @param string $key
     * @return string
     */
    private function saveImage(Request $request, string $key): string
    {
        $path = null;
        if ($request->hasFile($key)) {
            $publicImagesPath = rtrim(env('PUBLIC_IMAGES_PATH', 'public/images'), '/');
            $file = $request->file($key);
            $path = preg_replace(
                '#public/#',
                'storage/',
                Storage::putFile($publicImagesPath, $file)
            );
        }
        return $path;
    }
}
