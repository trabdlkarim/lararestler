<?php

namespace Mirak\Lararestler;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Mirak\Lararestler\Exceptions\RestException;
use Mirak\Lararestler\Routing\DynamicRoute;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class RestApi
{
    private static $defaultNamespace = "App\\Http\\Resources";

    public static function routes()
    {
        self::registerRoutes();
        foreach (range(1, self::version()) as $version) {
            Route::prefix('v' . $version)->group(function () use ($version) {
                self::registerRoutes($version);
            });
        }
    }

    public static function version()
    {
        return config('lararestler.version');
    }

    public static function resources()
    {
        return config("lararestler.resources");
    }

    /**
     * Register API routes for a specific version 
     * 
     * @param int $v [optional] API version
     * @return void
     */
    private static function registerRoutes($v = null)
    {
        if ($v) $v = "v{$v}";
        foreach (self::resources() as $path => $class) {
            if (!class_exists($class)) {
                $class = self::getResourceNamespace() . '\\' . $class;
            }

            if ($v) {
                $reflection = new \ReflectionClass($class);
                $default = $class;
                $class = $reflection->getNamespaceName() . "\\" . $v . "\\" . $reflection->getShortName();
                if (class_exists($class)) {
                    DynamicRoute::controller($class, $path);
                } else {
                    DynamicRoute::controller($default, $path);
                }
            } else {
                DynamicRoute::controller($class, $path);
            }
        }
    }

    public static function getResourceNamespace()
    {
        return trim(config('lararestler.namespace', self::$defaultNamespace), '\\');
    }

    public static function getPathPrefix()
    {
        return trim(config('lararestler.path_prefix', ''), '/');
    }

    public static function removePathPrefix(string $path)
    {
        $prefix = self::getPathPrefix();
        if($prefix){
            $prefix .= "/";
            $pos = strpos($path, $prefix);
            if ($pos === 0) {
                $path = substr($path, strlen($prefix));
            }
        }
        return $path;
    }

    /**
     * Register a renderable callback for rest exceptions.
     * 
     * @param Illuminate\Foundation\Configuration\Exceptions $exceptions
     * 
     * @return void
     */
    public static function renderExceptions($exceptions)
    {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->expectsJson();
        });
        //if ($request->expectsJson()) {
        $exceptions->render(function (HttpException $e, Request $request) {
            $code = $e->getStatusCode();
            $message = $e->getMessage();
            if (isset(RestException::$codes[$code])) {
                $message = RestException::$codes[$code] .
                    (empty($message) ? '' : ': ' . $message);
            }
            $res = [
                "error" => [
                    'endpoint' => $request->method() . ' /' . $request->path(),
                    'code' => $code,
                    'message' => $message,
                ],

            ];

            if (App::hasDebugModeEnabled()) {
                $res["debug"] = [
                    "exception" => get_class($e),
                    "file" => $e->getFile(),
                    "line" => $e->getLine(),
                    "trace" => $e->getTrace(),
                ];
            }
            return response()->json($res, $code);
        });
        // }
    }
}
