<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\AffiliateService;
use App\Tag;
use App\Task;
use App\TaskImage;
use App\Traits\ResponseMaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{

    use ResponseMaker;

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function create(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'category_id' => 'required|integer|exists:categories,id',
            'offer_id' => 'required|integer|min:1',
            'currency' => 'required|string',
            'original_price' => 'required|numeric|min:0',
            'payable_price' => 'required|numeric|min:0',
            'has_shipment' => 'required|boolean',
            'shipment_price' => 'required|numeric:min:0',
            'coupon_code' => 'string',
            'expires_at' => 'date_format:Y-m-d|after:today',
            'description' => 'string',
            'destination_url' => 'required|url',
            'coin_reward' => 'required|integer|min:0',
            'custom_attributes' => 'json',
            'images' => 'array|min:1',
            'images.*' => 'mimes:jpeg,jpg,png',
            'tags' => 'array|min:1',
            'tags.*' => 'string|distinct'
        ]);

        if ($validator->fails()) {
            return response(['message' => 'Validation errors', 'errors' => $validator->errors(), 'status' => false], 422);
        }

        $inputs = $request->all();
        $inputs['token'] = Task::generateToken();
        $inputs['destination_url'] = base64_encode($inputs['destination_url']);

        try {
            DB::beginTransaction();
            $task = Task::create($inputs);
            $publicImagesPath = rtrim(env('PUBLIC_IMAGES_PATH', 'public/images'), '/') . '/';
            if ($request->hasFile('images')) {
                $files = $request->file('images');
                foreach ($files as $file) {
                    $path = preg_replace(
                        '#public/#',
                        'storage/',
                        Storage::putFile($publicImagesPath, $file)
                    );
                    $taskImage = ['task_id' => $task->id, 'url' => $path];
                    TaskImage::create($taskImage);
                }
            }
            if ($request->exists('tags')) {
                $tags = $request->get('tags');
                $tagIds = [];
                foreach ($tags as $tagName) {
                    $tag = Tag::makeTag($tagName);
                    $tagIds[] = $tag->id;
                }
                if ($tagIds) {
                    $task->tags()->attach($tagIds);
                }
            }
            DB::commit();
            return response(['message' => 'success', 'errors' => null, 'status' => true, 'data' => $task], 200);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response(
                [
                    'message' => 'failed',
                    'errors' => ['exception' => $exception->getMessage()],
                    'status' => false,
                    'data' => []
                ],
                400
            );
        }

    }

    /**
     * @param Request $request
     * @param int $taskId
     * @param AffiliateService $affiliateService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getLandingUrl(Request $request, int $taskId, AffiliateService $affiliateService)
    {
        $user = Auth::user();
        if ($user) {
            $task = Task::find($taskId);
            if ($task) {
                $response = $affiliateService->registerClick($taskId->id, $user->id, $task->offer_id);
                if ($response['status'] == 200) {
                    $parameter = $response['data']['query_param'];
                    $url = add_query_param_to_url($task->destination_url, $parameter);
                    return $this->success(['landing_url' => $url]);
                }
                return $this->failData($response['data'], $response['status']);
            }
            return $this->failMessage('Content not found.', 404);
        }
        return $this->failMessage('Content not found.', 404);

    }
}
