<?php

namespace Mirak\Lararestler;

use Mirak\Lararestler\Routing\DynamicRoute;
use Illuminate\Support\Facades\Route;

class RestApi
{

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
}