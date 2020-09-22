<?php

namespace App\Http\Controllers\Api\V1;

use App\Filterable;
use Illuminate\Http\Request;
use App\Traits\ResponseMaker;
use App\Http\Controllers\Controller;

class FilterableController extends Controller
{
    use ResponseMaker;

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getAll(Request $request)
    {
        $filterables = Filterable::all()->toArray();
        $result = [];
        foreach ($filterables as $filterable) {
            $result[] = [
                'id' => $filterable->id,
                'field' => $filterable->table . $filterable->column
            ];
        }
        return $this->success($result);
    }
}
