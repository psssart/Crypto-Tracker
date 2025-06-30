<?php
declare(strict_types=1);

namespace App\Enum;

enum Method: string
{
    case GET = 'get';
    case POST = 'post';
    case PUT = 'put';
    case PATCH = 'patch';
    case DELETE = 'delete';
}
