<?php
declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ApiResponseException;

class ApiService
{
    /**
     * Send a GET request.
     *
     * @param string $url     The full URL to call.
     * @param array  $query   Optional query parameters.
     * @param array  $headers Optional headers.
     *
     * @throws ApiResponseException
     */
    public function get(string $url, array $query = [], array $headers = []): Response
    {
        try {
            $response = Http::withHeaders($headers)
                ->timeout(60)
                ->get($url, $query);
        } catch (\Throwable $e) {
            Log::error('API GET request failed', [
                'url'       => $url,
                'query'     => $query,
                'exception' => $e->getMessage(),
            ]);
            throw new ApiResponseException('Network error during GET request', 0, $e);
        }

        return $this->handleResponse($response, 'GET', $url, $query);
    }

    /**
     * Send a POST request with JSON body.
     *
     * @param string $url     The full URL to call.
     * @param array  $data    The request payload.
     * @param array  $headers Optional headers.
     *
     * @throws ApiResponseException
     */
    public function post(string $url, array $data = [], array $headers = []): Response
    {
        try {
            $response = Http::withHeaders($headers)
                ->timeout(60)
                ->post($url, $data);
        } catch (\Throwable $e) {
            Log::error('API POST request failed', [
                'url'       => $url,
                'data'      => $data,
                'exception' => $e->getMessage(),
            ]);
            throw new ApiResponseException('Network error during POST request', 0, $e);
        }

        return $this->handleResponse($response, 'POST', $url, $data);
    }

    /**
     * Send a multipart/form-data POST request.
     *
     * @param string $url       The full URL to call.
     * @param array  $multipart An array of ['name'=>'…','contents'=>'…','filename'=>'…'] items.
     * @param array  $headers   Optional headers.
     *
     * @throws ApiResponseException
     */
    public function multipart(string $url, array $multipart, array $headers = []): Response
    {
        try {
            $response = Http::withHeaders($headers)
                ->timeout(60)
                ->asMultipart()
                ->post($url, $multipart);
        } catch (\Throwable $e) {
            Log::error('API multipart request failed', [
                'url'        => $url,
                'multipart'  => $multipart,
                'exception'  => $e->getMessage(),
            ]);
            throw new ApiResponseException('Network error during multipart request', 0, $e);
        }

        return $this->handleResponse($response, 'POST (multipart)', $url, $multipart);
    }

    /**
     * Check for HTTP errors and throw if needed.
     */
    protected function handleResponse(Response $response, string $method, string $url, array $payload): Response
    {
        if ($response->failed()) {
            $status  = $response->status();
            $body    = $response->json();
            $message = $body['error'] ?? $body['message'] ?? 'Unknown API error';

            Log::error('API response error', compact('method', 'url', 'status', 'payload', 'body'));

            throw new ApiResponseException($message, $status);
        }

        return $response;
    }
}
