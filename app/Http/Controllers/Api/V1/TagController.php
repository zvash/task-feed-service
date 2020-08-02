<?php

namespace App\Http\Controllers\Api\V1;

use App\Tag;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class TagController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getAll(Request $request)
    {
        $tags = Tag::all();
        return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => $tags], 200);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function create(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'unique:tags'],
            'display_name' => 'string|max:255'
        ]);

        if ($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' => $validator->errors(), 'status' => false], 422);
        }
        $name = $request->get('name');
        $displayName = $request->exists('display_name') ?
            $request->get('display_name') : null;

        $tag = Tag::makeTag($name, $displayName);

        return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => $tag], 200);

    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function createMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|array|min:1',
            'tags.*.name' => ['required', 'unique:tags', 'distinct'],
            'tags.*.display_name' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' => $validator->errors(), 'status' => false], 422);
        }
        $input = $request->all();
        $tags = [];
        foreach ($input['tags'] as $tag) {
            $name = $tag['name'];
            $displayName = array_key_exists('display_name', $tag) ?
                $tag['display_name'] : null;
            $tags[] = Tag::makeTag($name, $displayName);
        }
        return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => $tags], 200);
    }
}
