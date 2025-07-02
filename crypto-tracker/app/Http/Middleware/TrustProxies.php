<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class TrustProxies extends Middleware
{
    /**
     * Trust all proxies.
     *
     * @var array|string|null
     */
    protected $proxies = '*';

    /**
     * Explicitly specify which X-Forwarded-* headers to use
     *
     * @var int
     */
    protected $headers =
        SymfonyRequest::HEADER_X_FORWARDED_FOR   // client IP
        | SymfonyRequest::HEADER_X_FORWARDED_HOST  // original Host
        | SymfonyRequest::HEADER_X_FORWARDED_PROTO // http/https
        | SymfonyRequest::HEADER_X_FORWARDED_PORT; // port
}
