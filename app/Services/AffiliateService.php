<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;

class AffiliateService
{
    /**
     * @var array $headers
     */
    protected $headers;

    /**
     * @var Client $client
     */
    protected $client;

    /**
     * AuthService constructor.
     */
    public function __construct()
    {
        $baseUrl = rtrim(env('AFFILIATE_MANAGEMENT_SERVICE_URL', 'internal-affiliate'), '/') . '/';
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Service-Token' => env('AFFILIATE_MANAGEMENT_SERVICE_TOKEN', '')
        ];
        $this->client = new Client(
            [
                'base_uri' => $baseUrl,
                'timeout' => config('timeout', 5)
            ]
        );
    }

    /**
     * @param int $taskId
     * @param int $userId
     * @param int $offerId
     * @return array
     */
    public function registerClick(int $taskId, int $userId, int $offerId)
    {
        $payload = [
            'task_id' => $taskId,
            'user_id' => $userId,
            'offer_id' => $offerId
        ];

        try {
            $response = $this->client->request(
                'POST',
                $this->getRegisterClickUrl(),
                [
                    'json' => $payload,
                    'headers' => $this->headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
        } catch (GuzzleException $exception) {
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @return string
     */
    private function getRegisterClickUrl()
    {
        return 'api/v1/clicks/create';
    }
}