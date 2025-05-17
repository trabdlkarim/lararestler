<?php

namespace Mirak\Lararestler\Http\Middleware;

use Mirak\Lararestler\Exceptions\RestException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceJsonContentType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS', 'DELETE']) && !$request->expectsJson()) {
            $message = "Content-Type header is missing.";
            if ($request->hasHeader('Content-Type')) {
                $message = 'Content type `' . $request->header('Content-Type') . '` is not supported.';
            }
            throw new RestException(406,  $message);
        }
        return $next($request);
    }
}
