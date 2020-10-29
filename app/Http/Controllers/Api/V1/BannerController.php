<?php

namespace App\Http\Controllers\Api\V1;

use App\Banner;
use App\Country;
use App\Repositories\BannerRepository;
use App\Repositories\CountryRepository;
use Illuminate\Http\Request;
use App\Traits\ResponseMaker;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BannerController extends Controller
{
    use ResponseMaker;

    /**
     * @param Request $request
     * @param CountryRepository $countryRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function create(Request $request, CountryRepository $countryRepository)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer|exists:groups,id',
            'name' => 'required|string|unique:groups',
            'image' => 'required|mimes:jpeg,jpg,png',
            'countries' => 'required|array|filled',
            'countries.*' => 'required|string|in:ALL,' . $countryRepository->getAllNameVariationsAsString(),
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }
        $name = $request->get('name');
        $groupId = $request->get('group_id');
        $path = $this->saveImage($request, 'image');
        $banner = Banner::makeBanner($groupId, $name, $path);
        if ($banner) {
            $this->attachCountries($request, $banner, $countryRepository);
        }

        return $this->success($banner);
    }

    /**
     * @param Request $request
     * @param int $bannerId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function addTags(Request $request, int $bannerId)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|array|min:1',
            'tags.*' => 'int|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $banner = Banner::find($bannerId);
        if ($banner) {
            $banner->tags()->attach($request->get('tags'));
            return $this->success($banner->load('tags'));
        }
        return $this->failMessage('content not found', 404);
    }

    /**
     * @param Request $request
     * @param int $bannerId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function resetTags(Request $request, int $bannerId)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|array|min:1',
            'tags.*' => 'int|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $banner = Banner::find($bannerId);
        if ($banner) {
            $banner->tags()->sync($request->get('tags'));
            return $this->success($banner->load('tags'));
        }
        return $this->failMessage('content not found', 404);
    }

    /**
     * @param Request $request
     * @param int $bannerId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function removeTags(Request $request, int $bannerId)
    {
        $validator = Validator::make($request->all(), [
            'tags' => 'required|array|min:1',
            'tags.*' => 'int|exists:tags,id',
        ]);

        if ($validator->fails()) {
            return $this->failValidation($validator->errors());
        }

        $banner = Banner::find($bannerId);
        if ($banner) {
            $banner->tags()->detach($request->get('tags'));
            return $this->success($banner->load('tags'));
        }
        return $this->failMessage('content not found', 404);
    }

    /**
     * @param Request $request
     * @param int $bannerId
     * @param BannerRepository $bannerRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function getTasks(Request $request, int $bannerId, BannerRepository $bannerRepository)
    {
        $user = Auth::user();
        if ($user) {
            $bannerRepository->setCountries([$user->country]);
        } else {
            if ($request->attributes->get('country', null)) {
                $bannerRepository->setCountries([$request->attributes->get('country', null)]);
            }
        }
        $banner = Banner::find($bannerId);
        if ($banner) {

            $tasks = $bannerRepository->getTasks($banner, 10);
            $tasks = $tasks->toArray();
            $tasks['parent_entity'] = [
                'id' => $banner->id,
                'name' => $banner->name
            ];

            return $this->success($tasks);
        }
        return $this->failMessage('content not found', 404);
    }

    /**
     * @param Request $request
     * @param string $key
     * @return string|null
     */
    private function saveImage(Request $request, string $key)
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

    /**
     * @param Request $request
     * @param Banner $banner
     * @param CountryRepository $countryRepository
     */
    private function attachCountries(Request $request, Banner $banner, CountryRepository $countryRepository)
    {
        $countries = $request->get('countries');
        $allCountries = Country::all()->pluck('id', 'name')->toArray();
        $bannerCountriesByCountryId = [];
        foreach ($countries as $countryName) {
            $country = $countryRepository->getCountry($countryName);
            $countryId = $allCountries[$country['name']];
            $bannerCountriesByCountryId[$countryId] = [
                'currency' => $country['currency']
            ];
        }
        if ($bannerCountriesByCountryId) {
            $banner->countries()->attach($bannerCountriesByCountryId);
        }
    }
}
