<?php

namespace App\Http\Controllers\Api\V1;

use App\Repositories\SearchRepository;
use App\Repositories\TaskRepository;
use App\Services\AuthService;
use App\Services\BillingService;
use App\Tag;
use App\Task;
use App\Country;
use App\TaskImage;
use App\User;
use Illuminate\Http\Request;
use App\Traits\ResponseMaker;
use App\Services\AffiliateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Exceptions\ServiceException;
use Illuminate\Support\Facades\Storage;
use App\Repositories\CountryRepository;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{

    use ResponseMaker;

    /**
     * @param Request $request
     * @param CountryRepository $countryRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function create(Request $request, CountryRepository $countryRepository)
    {
        try {
            $this->validateTaskCreationRequest($request, $countryRepository);
        } catch (ServiceException $e) {
            return $this->failValidation($e->getData());
        }
        $inputs = $request->all();
        $inputs['token'] = Task::generateToken();
        //$inputs['destination_url'] = base64_encode($inputs['destination_url']);

        try {
            DB::beginTransaction();

            $task = Task::create($inputs);

            $this->storeTaskImages($request, $task);
            $this->attachTags($request, $task);
            $this->attachPrices($request, $task, $countryRepository);

            DB::commit();
            return $this->success($task->load('images'));

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->failMessage($exception->getMessage(), 400);
        }
    }

    /**
     * @param Request $request
     * @param int $taskId
     * @param TaskRepository $taskRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function get(Request $request, int $taskId, TaskRepository $taskRepository)
    {
        $user = Auth::user();
        if ($user) {
            $taskRepository->setCountries([$user->country]);
        } else {
            if ($request->attributes->get('country', null)) {
                $taskRepository->setCountries([$request->attributes->get('country', null)]);
            }
        }
        $task = $taskRepository->getTask($taskId);
        if ($task) {
            return $this->success($task);
        }

        return $this->failMessage('Content not found.', 404);
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
                //$response = $affiliateService->registerClick($task->id, $user->id, $task->offer_id);
                $response = $affiliateService->registerClaim($user->id, 'tasks', $task->id, $task->coin_reward, $task->offer_id);
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

    /**
     * @param Request $request
     * @param AffiliateService $affiliateService
     * @param AuthService $authService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function history(Request $request, AffiliateService $affiliateService, AuthService $authService)
    {
        $user = Auth::user();
        if ($user) {
            $page = $request->exists('page') ? $request->get('page') : 1;
            $userId = $user->id;
            $response = $affiliateService->getClaims($userId, $page);
            if ($response['status'] == 200) {
                $data = $response['data']['data'];
                $taskData = [];
                foreach ($data as $item) {
                    if ($item['claimable_type'] == 'tasks') {
                        $task = Task::find($item['claimable_id']);
                        if ($task) {
                            $affiliateItem = [
                                'claim_id' => $item['token'],
                                'title' => $task->title,
                                'coins' => $item['coin_reward'],
                                'date' => $item['created_at'],
                                'status' => $item['claimed_at'] ? 'received' : 'claimable'
                            ];
                        } else {
                            $affiliateItem = [
                                'claim_id' => $item['token'],
                                'title' => 'Deleted Task',
                                'coins' => $item['coin_reward'],
                                'date' => $item['created_at'],
                                'status' => $item['claimed_at'] ? 'received' : 'claimable'
                            ];
                        }
                    } else if ($item['claimable_type'] == 'referrals') {
                        $userData = $authService->getUserById($item['claimable_id']);
                        if ($userData['status'] == 200) {
                            $referringUser = $userData['data'];
                            $affiliateItem = [
                                'claim_id' => $item['token'],
                                'title' => "Referral Coins of {$referringUser['masked_phone']}",
                                'coins' => $item['coin_reward'],
                                'date' => $item['created_at'],
                                'status' => $item['claimed_at'] ? 'received' : 'claimable'
                            ];
                        } else {
                            $affiliateItem = [
                                'claim_id' => $item['token'],
                                'title' => "Referral Coins of Unknown User",
                                'coins' => $item['coin_reward'],
                                'date' => $item['created_at'],
                                'status' => $item['claimed_at'] ? 'received' : 'claimable'
                            ];
                        }
                    } else {
                        $affiliateItem = [
                            'claim_id' => $item['token'],
                            'title' => "Unknown Claimable",
                            'coins' => 0,
                            'date' => $item['created_at'],
                            'status' => $item['claimed_at'] ? 'received' : 'claimable'
                        ];
                    }
                    $taskData[] = $affiliateItem;
                }
                $taskHistory = $response['data'];
                $taskHistory['data'] = $taskData;
                $taskHistory['first_page_url'] = $this->replacePaginationUrlForHistory($taskHistory['first_page_url']);
                $taskHistory['last_page_url'] = $this->replacePaginationUrlForHistory($taskHistory['last_page_url']);
                $taskHistory['next_page_url'] = $this->replacePaginationUrlForHistory($taskHistory['next_page_url']);
                $taskHistory['prev_page_url'] = $this->replacePaginationUrlForHistory($taskHistory['prev_page_url']);
                $taskHistory['path'] = $this->replacePaginationUrlForHistory($taskHistory['path']);
                return $this->success($taskHistory);
            }
            return $this->failMessage('Something went wrong in affiliate service', 400);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $clickId
     * @param AffiliateService $affiliateService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function claim(Request $request, int $clickId, AffiliateService $affiliateService)
    {
        $user = Auth::user();
        if ($user) {
            $userId = $user->id;
            $response = $affiliateService->claim($clickId, $userId);
            if ($response['status'] == 200) {
                $taskId = $response['data']['task_id'];
                if ($taskId) {
                    $task = Task::find($taskId);
                    if ($task) {
                        return $this->success([
                            'successful_claim' => true,
                            'reward' => $task->coin_reward
                        ]);
                    } else {
                        return $this->success([
                            'successful_claim' => false,
                            'coin_reward' => 0
                        ]);
                    }
                } else {
                    return $this->success([
                        'successful_claim' => false,
                        'coin_reward' => 0
                    ]);
                }
            }
            return $this->failMessage('Something went wrong in affiliate service', 400);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param AffiliateService $affiliateService
     * @param BillingService $billingService
     * @param AuthService $authService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function claimByToken(Request $request, AffiliateService $affiliateService, BillingService $billingService, AuthService $authService)
    {
        $validator = Validator::make($request->all(), [
            'claim_id' => 'required|string|filled',
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $user = Auth::user();
        $claimId = $request->get('claim_id');
        if ($user) {
            $userId = $user->id;
            try {
                $claim = $this->getClaim($claimId, $userId, $affiliateService);
                return $this->handleClaim($claim, $billingService, $authService);
            } catch (\Exception $exception) {
                return $this->success([
                    'successful_claim' => false,
                    'reward' => 0,
                    'message' => $exception->getMessage()
                ]);
            }
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param string $token
     * @param int $userId
     * @param AffiliateService $affiliateService
     * @return mixed
     * @throws \Exception
     */
    private function getClaim(string $token, int $userId, AffiliateService $affiliateService)
    {
        $response = $affiliateService->claimByToken($token, $userId);
        if ($response['status'] == 200) {
            $claim = $response['data'];
            if (!$claim) {
                throw new \Exception('Something went wrong in affiliate service', 400);
            }
            return $claim;
        }
        throw new \Exception($response['data'], 400);
    }

    /**
     * @param array $claim
     * @param BillingService $billingService
     * @param AuthService $authService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    private function handleClaim(array $claim, BillingService $billingService, AuthService $authService)
    {
        $transactions = [$billingService->depositCoin($claim['user_id'], $claim['coin_reward'], $claim['claimable_type'], $claim['claimable_id'])];
        $billingService->createTransactions($transactions);
        $authService->taskCompleted($claim['user_id']);
        return $this->success([
            'successful_claim' => true,
            'reward' => $claim['coin_reward'],
            'message' => 'ok'
        ]);
    }

    /**
     * @param Request $request
     * @param int $taskId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getTags(Request $request, int $taskId)
    {
        $task = Task::find($taskId);
        if ($task) {
            $tags = $task->tags;
            return $this->success($tags);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $taskId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function resetTags(Request $request, int $taskId)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|array|min:1',
            'tags.*' => 'int|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }


        $task = Task::find($taskId);
        if ($task) {
            $task->tags()->sync($request->get('tags'));
            return $this->success($task->load('tags'));
        }
        return $this->failMessage('Content not found.', 404);
    }


    /**
     * @param Request $request
     * @param SearchRepository $searchRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function searchByText(Request $request, SearchRepository $searchRepository)
    {
        if ($request->has('filters')) {
            $filters = $request->get('filters');
            $searchRepository->setFilters($filters);
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
            $searchRepository->setCountries([$user->country]);
        } else {
            if ($request->attributes->get('country', null)) {
                $searchRepository->setCountries([$request->attributes->get('country', null)]);
            }
        }

        $tasks = $searchRepository->searchTasksByText($query, 10);
        $filterOptions = $searchRepository->filterOptions();
        $tasks = ($tasks->appends(request()->except('page')))->toArray();
        $tasks['filter_options'] = $filterOptions;
        return $this->success($tasks);

    }

    /**
     * @param CountryRepository $countryRepository
     * @param Request $request
     * @throws ServiceException
     * @return void
     */
    private function validateTaskCreationRequest(Request $request, CountryRepository $countryRepository)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'category_id' => 'required|integer|exists:categories,id',
            'offer_id' => 'required|integer|min:1',
            'store' => 'required|string',
            'prices' => 'required|array|filled',
            'prices.*.country' => 'required|string|in:' . $countryRepository->getAllNameVariationsAsString(),
            'prices.*.currency' => 'required|string|in:' . $countryRepository->getAllCurrenciesAsString(),
            'prices.*.original_price' => 'numeric|min:0',
            'prices.*.payable_price' => 'required|numeric|min:0',
            'prices.*.has_shipment' => 'required|boolean',
            'prices.*.shipment_price' => 'numeric|min:0|required_if:prices.*.has_shipment,1',
            'coupon_code' => 'string',
            'expires_at' => 'date_format:Y-m-d H:i:s|after:today',
            'description' => 'string',
            'destination_url' => 'required|string',
            'coin_reward' => 'required|integer|min:0',
            'custom_attributes' => 'json',
            'images' => 'array|min:1',
            'images.*' => 'mimes:jpeg,jpg,png',
            'tags' => 'array|min:1',
            'tags.*' => 'string|distinct',
        ]);

        if ($validator->fails()) {
            throw new ServiceException('Validation Error.', $validator->errors()->toArray());
        }
    }

    /**
     * @param Request $request
     * @param Task $task
     */
    private function attachTags(Request $request, Task $task)
    {
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
    }

    /**
     * @param Request $request
     * @param Task $task
     * @param CountryRepository $countryRepository
     */
    private function attachPrices(Request $request, Task $task, CountryRepository $countryRepository)
    {
        if ($request->exists('prices')) {
            $prices = $request->get('prices');
            $allCountries = Country::all()->pluck('id', 'name')->toArray();
            $pricesByCountryId = [];
            foreach ($prices as $price) {
                $country = $countryRepository->getCountry($price['country']);
                $countryName = $country['name'];
                $countryId = $allCountries[$countryName];
                $pricesByCountryId[$countryId] = [
                    'currency' => $price['currency'],
                    'payable_price' => $price['payable_price'],
                    'original_price' => $price['original_price'] ?? $price['payable_price'],
                    'has_shipment' => $price['has_shipment'],
                    'shipment_price' => $price['has_shipment'] ? $price['shipment_price'] : 0
                ];
            }

            if ($pricesByCountryId) {
                $task->countries()->attach($pricesByCountryId);
            }
        }
    }

    /**
     * @param Request $request
     * @param Task $task
     */
    private function storeTaskImages(Request $request, Task $task): void
    {
        $publicImagesPath = rtrim(env('PUBLIC_IMAGES_PATH', 'public/images'), '/');
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
    }

    /**
     * @param null|string $currentUrl
     * @return null|string
     */
    private function replacePaginationUrlForHistory(?string $currentUrl)
    {
        if (!$currentUrl) {
            return null;
        }
        $serviceUrl = rtrim(env('APP_URL'), '/');
        $currentUrlParts = explode('?', $currentUrl);
        if (isset($currentUrlParts[1])) {
            return "$serviceUrl/api/v1/tasks/history?{$currentUrlParts[1]}";
        }
        return "$serviceUrl/api/v1/tasks/history";
    }
}
