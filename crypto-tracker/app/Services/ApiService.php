<?php
declare(strict_types=1);

namespace App\Services;

use App\Enum\Method;
use App\Exceptions\ApiResponseException;
use ErrorException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiService
{
    private ?string $sessionId = null;

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }


    /**
     * @throws ApiResponseException
     */
    public function loginRequest(
        string  $login,
        string  $password,
        ?string $yksCookie = null
    ): Response
    {

        return $this->request(
            method: Method::POST,
            url: '/webmaster/login',
            data: [
                'login' => $login,
                'password' => $password,
                //                'permission' => $permission->value,
                'bearer' => 1,
            ],
            page: null,
            type: 'form_params',
            cookies: ['yks' => $yksCookie],
        );
    }

    public function tokenLogin(
        string  $token,
        ?string $yksCookie = null
    ): Response
    {

        return $this->request(
            method: Method::POST,
            url: '/webmaster/hijack',
            data: [
                'hash' => $token,
                'bearer' => 1,
            ],
            page: null,
            type: 'form_params',
        );

    }


    /**
     * @throws ApiResponseException|ErrorException
     */
    public function request(
        Method  $method,
        string  $url,
        array   $data,
        ?string $page = null,
        ?string $type = null,
        ?array  $cookies = null,
        ?array  $headers = [],
    ): Response
    {
        $response = false;

        try {

            $isProd = config('app.env') === 'production';

            $headers['X-Requested-With'] = 'XMLHttpRequest';


            if(!$isProd){
                $headers['debug'] = 123;
            }

            $response = Http::baseUrl(config('app.api'))
                ->timeout(60)
                ->when($this->sessionId !== null,
                    fn(PendingRequest $pendingRequest) => $pendingRequest->withToken((string)$this->sessionId)
                )
                ->withHeaders($headers)
                ->when($cookies !== null, function (PendingRequest $request) use ($cookies) {
                    return $request
                        ->withCookies((array)$cookies, parse_url(config('app.api'), PHP_URL_HOST));
                })
                ->when(!$isProd, fn($http) => $http->withoutVerifying())
                ->send($method->value, $url, [
                    $type => $data,
                ]);
        } catch (\Exception $exception) {

            $exception = $exception?->getMessage() ?? 'Something went wrong';

            $error  = $this->extractErrorsMessages($response);

            Log::critical('API Request Failed', [
                'exception' => $exception,
                'errors' => $error,
                'request_method' => $method->value,
                'request_url' => $url,
                'request_data' => $data,
                'page' => $page ?? '',
            ]);


                throw new ApiResponseException($error ?? '', $response ? $response->status() : 500);
        }

        if ($response && $response->failed()) {

            $responseBody = isset($response) ? $response?->json() : '';

            $error  = $this->extractErrorsMessages($response);

            Log::critical('API Request Failed', [
                'exception' => '',
                'error' => $error,
                'request_method' => $method->value,
                'request_url' => $url,
                'request_data' => $data,
                'response_body' => $responseBody,
                'page' => $page ?? '',
            ]);

            $code = $response->status();

            throw new ApiResponseException($error, $code);
        }

        if (!isset($response) || empty($response)) {

            $response = false;
        }


        return $response;
    }

    public function post(
        string  $url,
        array   $data,
        ?string $page = null,
        ?string $type = 'form_params',
        ?array  $cookies = null)
    {

        return $this->request(method: Method::POST, url: $url, data: $data, page: $page, type: $type, cookies: $cookies);
    }

    public function get(
        string  $url,
        ?array  $data = [],
        ?string $page = null,
        ?string $type = null,
        ?array  $cookies = null)
    {

        return $this->request(method: Method::GET, url: $url, data: $data, page: $page, type: $type, cookies: $cookies);
    }

    public function multipart(
        string $url,
        array $files = [],
        array $form = [],
        ?string $page = null,
        ?array $cookies = null
    ): Response {

        $multipartData = [];

        foreach ($files as $key => $file) {
            if (isset($file['contents']) && isset($file['original_name'])) {
                $multipartData[] = [
                    'name'     => $key,
                    'contents' => $file['contents'],
                    'filename' => $file['original_name'],
                ];
            }
        }

        foreach ($form as $key => $value) {
            $multipartData[] = [
                'name'     => $key,
                'contents' => $value,
            ];
        }

        return $this->request(
            method: Method::POST,
            url: $url,
            data: $multipartData,
            page: $page,
            type: 'multipart',
            cookies: $cookies
        );
    }

    private function extractErrorsMessages(mixed $response){
        if(!isset($response) || empty($response) || $response === false){
            return '';
        }
        $error ??= $response->json('error') ?? $response->json('errors') ?? $response->json('message')  ?? '';

        return  is_array($error) ? json_encode($error) : (string) $error;

    }
}
