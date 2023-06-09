<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\GroupNotFoundException;
use App\Group;
use App\Repositories\GroupRepository;
use App\TagTask;
use App\Task;
use Illuminate\Http\Request;
use App\Traits\ResponseMaker;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'type' => 'required|string|in:tasks,banners',
            'is_active' => 'boolean',
            'order' => 'integer|min:1',
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
     * @param GroupRepository $groupRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getItems(Request $request, int $groupId, GroupRepository $groupRepository)
    {
        $user = Auth::user();
        if ($user) {
            $groupRepository->setCountries([$user->country]);
        } else {
            if ($request->attributes->get('country', null)) {
                $groupRepository->setCountries([$request->attributes->get('country', null)]);
            }
        }
        $group = Group::find($groupId);
        if ($group) {

            $tasks = $groupRepository->getGroupItems($group, 10);
            $tasks = $tasks->toArray();
            $tasks['parent_entity'] = [
                'id' => $group->id,
                'name' => $group->name
            ];

            return $this->success($tasks);
        }
        return $this->failMessage('content not found', 404);
    }


    /**
     * @param Request $request
     * @param GroupRepository $groupRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getActiveGroups(Request $request, GroupRepository $groupRepository)
    {
        $user = Auth::user();
        if ($user) {
            $groupRepository->setCountries([$user->country]);
        } else {
            if ($request->attributes->get('country', null)) {
                $groupRepository->setCountries([$request->attributes->get('country', null)]);
            }
        }
        $groups = Group::where('is_active', true)->orderBy('order')->paginate(5)->toArray();
        if ($groups) {
            foreach ($groups['data'] as $index => $group) {
                $type = $group['type'];
                try {
                    $items = $groupRepository->getItems($group['id'], 5);
                } catch (GroupNotFoundException $e) {
                    $items = [];
                }
                $groups['data'][$index][$type] = $items;
                if ($type == 'tasks') {
                    $groups['data'][$index]['banners'] = null;
                } else if ($type == 'banners'){
                    $groups['data'][$index]['tasks'] = null;
                }
            }
            return $this->success($groups);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $groupId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function moveToTop(Request $request, int $groupId)
    {
        $group = Group::find($groupId);
        if ($group) {
            $groupOrder = $group->order;
            Group::where('id', '<>', $group->id)
                ->where('order', '<=', $groupOrder)
                ->update([
                    'order' => DB::raw('`order` + 1')
                ]);
            $group->order = 1;
            $group->save();
            return $this->success($group);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $groupId
     * @param GroupRepository $groupRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function search(Request $request, int $groupId, GroupRepository $groupRepository)
    {
        if ($request->has('filters')) {
            $filters = $request->get('filters');
            $groupRepository->setFilters($filters);
        }

        $q = $request->get('q');
        $query = urldecode($q);
        $data['query'] = $query;
        $validator = Validator::make($data, [
            'query' => 'required|filled|string'
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $user = Auth::user();
        if ($user) {
            $groupRepository->setCountries([$user->country]);
        } else {
            if ($request->attributes->get('country', null)) {
                $groupRepository->setCountries([$request->attributes->get('country', null)]);
            }
        }
        $group = Group::find($groupId);
        $tasks = [];
        if ($group) {
            $tasks = $groupRepository->searchByText($group, $query, 10);
            $filterOptions = $groupRepository->filterOptions();
            $tasks = ($tasks->appends(request()->except('page')))->toArray();
            $tasks['filter_options'] = $filterOptions;
            $tasks['parent_entity'] = [
                'id' => $group->id,
                'name' => $group->name
            ];

            return $this->success($tasks);
        }
        return $this->failMessage('Content not found.', 404);
    }
}
