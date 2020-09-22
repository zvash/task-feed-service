<?php

namespace App\Http\Controllers\Api\V1;

use App\Filter;
use Illuminate\Http\Request;
use App\Traits\ResponseMaker;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class FilterController extends Controller
{
    use ResponseMaker;

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|filled|unique:filters',
            'filterable_id' => 'required|integer|min:1|exists:filterables,id',
            'selection_type' => 'required|string|in:range,equals,multi_select,single_select,switch',
            'acceptable_values' => [
                'string',
                'filled',
                'regex:/^\w+(,\w+)*$/'
            ],
            'is_active' => 'boolean',
            'order' => 'integer'
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $inputs = $request->all();
        $filter = Filter::create($inputs);

        return $this->success($filter);
    }

    /**
     * @param Request $request
     * @param int $filterId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function deactivate(Request $request, int $filterId)
    {
        $filter = Filter::find($filterId);
        if ($filter) {
            $filter->is_active = false;
            $filter->save();
            return $this->success($filter);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $filterId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function activate(Request $request, int $filterId)
    {
        $filter = Filter::find($filterId);
        if ($filter) {
            $filter->is_active = true;
            $filter->save();
            return $this->success($filter);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getAll(Request $request)
    {
        return $this->success(Filter::all());
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getActives(Request $request)
    {
        $activeFilters = Filter::where('is_active', true)->get();
        return $this->success($activeFilters);
    }
}
