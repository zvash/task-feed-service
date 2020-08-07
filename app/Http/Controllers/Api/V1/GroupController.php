<?php

namespace App\Http\Controllers\Api\V1;

use App\Group;
use App\TagTask;
use App\Task;
use Illuminate\Http\Request;
use App\Traits\ResponseMaker;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class GroupController extends Controller
{

    use ResponseMaker;

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:groups',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }
        $inputs = $request->all();
        $group = Group::create($inputs);
        return $this->success($group);
    }

    /**
     * @param Request $request
     * @param int $groupId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function addTags(Request $request, int $groupId)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|array|min:1',
            'tags.*' => 'int|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $group = Group::find($groupId);
        if ($group) {
            $group->tags()->attach($request->get('tags'));
            return $this->success($group->load('tags'));
        }
        return $this->failMessage('content not found', 404);
    }

    /**
     * @param Request $request
     * @param int $groupId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function resetTags(Request $request, int $groupId)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|array|min:1',
            'tags.*' => 'int|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $group = Group::find($groupId);
        if ($group) {
            $group->tags()->sync($request->get('tags'));
            return $this->success($group->load('tags'));
        }
        return $this->failMessage('content not found', 404);
    }

    /**
     * @param Request $request
     * @param int $groupId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function removeTags(Request $request, int $groupId)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|array|min:1',
            'tags.*' => 'int|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $group = Group::find($groupId);
        if ($group) {
            $group->tags()->detach($request->get('tags'));
            return $this->success($group->load('tags'));
        }
        return $this->failMessage('content not found', 404);
    }

    /**
     * @param Request $request
     * @param int $groupId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getTasks(Request $request, int $groupId)
    {
        $group = Group::find($groupId);
        if ($group) {

            $tagIds = $group->tags->pluck('id')->toArray();

            $taskIds = TagTask::whereIn('tag_id', $tagIds)
                ->pluck('task_id')
                ->unique()
                ->toArray();

            $tasks = Task::whereIn('id', $taskIds)
                ->with('images')
                ->where('expires_at', '<=', date('Y-md'))
                ->get()
                ->toArray();

            return $this->success($tasks);
        }
        return $this->failMessage('content not found', 404);
    }
}
