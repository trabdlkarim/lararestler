<?php

namespace Mirak\Lararestler\Routing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Luracast\Restler\CommentParser;
use Mirak\Lararestler\Attributes\QueryParam;
use Mirak\Lararestler\Http\Requests\Payload;

use ReflectionAttribute;

class DynamicRoute
{
    private static $httpMethods = ['any', 'get', 'post', 'put', 'patch', 'delete'];
    private const EMIT_ROUTE_STATEMENTS = false;
    private static $reservedMethods = ['getMiddleware'];

    public static $defaultNamespace = "App\\Http\\Resources";

    /**
     * Main entry point to register routes dynamically for a given controller.
     *
     * @param string $controllerClassName
     * @param string $path
     * 
     * @return void
     */
    public static function controller($controllerClassName, $path = null)
    {
        $reflection = self::getClassReflection($controllerClassName);

        if (!$path) {
            $path = strtolower($reflection->getShortName());
        }

        $routes = self::buildRoutes($path, $reflection);

        foreach ($routes as $route) {
            if (isset($route->middleware)) {
                Route::{$route->httpMethod}($route->slug, $route->target)->middleware($route->middleware);
            } else {
                Route::{$route->httpMethod}($route->slug, $route->target);
            }
        }

        if (self::EMIT_ROUTE_STATEMENTS) {
            self::emitRoutes($controllerClassName, $routes);
        }
    }

    /**
     * Get a reflection instance of the controller class.
     *
     * @param string $controllerClassName
     * @return \ReflectionClass
     */
    private static function getClassReflection($controllerClassName)
    {
        return class_exists($controllerClassName) ?
            new \ReflectionClass($controllerClassName) :
            new \ReflectionClass(self::$defaultNamespace . "\\" . $controllerClassName);
    }

    /**
     * Build routes based on the public methods of the controller.
     *
     * @param string $path
     * @param \ReflectionClass $class
     * @return array
     */
    private static function buildRoutes($path, \ReflectionClass $class)
    {
        $routes = [];
        $methods = $class->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (in_array($method->name, self::$reservedMethods) || self::startsWith($method->name, '_')) {
                continue;
            }
            $route = self::createRouteObject($path, $method, $class);
            if ($route) {
                $routes[] = $route;
            }
        }

        return self::prioritizeRoutes($routes);
    }

    /**
     * Generate a URL slug based on the method name and its parameters.
     *
     * @param \ReflectionMethod $method
     * @return string
     */
    private static function generateSlug(\ReflectionMethod $method)
    {
        $slug = Str::slug(Str::snake(preg_replace(self::getMethodPattern(), '', $method->name), '-'));

        if ($slug === "index") {
            $slug = '';
        }

        foreach ($method->getParameters() as $parameter) {
            // Skip the parameter if it is of type Request
            if (self::isQueryParameter($parameter) || self::isRequestBody($parameter)) {
                continue;
            }

            $slug .= sprintf('/{%s%s}', self::getParameterName($parameter), $parameter->isDefaultValueAvailable() ? '?' : '');
        }

        return trim($slug, "/");
    }


    private static function isRequestBody(\ReflectionParameter $parameter)
    {
        if ($parameter->hasType()) {
            $type = $parameter->getType()->getName();
            if ($type === Request::class || $type === 'array') {
                return true;
            }
            
            if(!$parameter->getType()->isBuiltin()){
                $instance = new $type();
                if (is_subclass_of($instance, Payload::class)) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function isQueryParameter(\ReflectionParameter $parameter)
    {
        $attribute = collect($parameter->getAttributes(QueryParam::class, ReflectionAttribute::IS_INSTANCEOF))->first();
        if ($attribute) {
            return $attribute->getName() === QueryParam::class;
        }
        return false;
    }

    /**
     * Get the pattern to match HTTP method names.
     *
     * @return string
     */
    private static function getMethodPattern()
    {
        return '/^(' . implode('|', self::$httpMethods) . ')/';
    }

    /**
     * Extract parameter name from the method parameter.
     *
     * @param \ReflectionParameter $parameter
     * @return string
     */
    private static function getParameterName(\ReflectionParameter $parameter)
    {
        if ($parameter->hasType() && !$parameter->getType()->isBuiltin()) {
            return strtolower(class_basename($parameter->getType()->getName()));
        }
        return strtolower($parameter->getName());
    }

    /**
     * Create a route object with method and path details.
     *
     * @param string $path
     * @param \ReflectionMethod $method
     * @param \ReflectionClass $class
     * @return \stdClass|null
     */
    private static function createRouteObject($path, \ReflectionMethod $method, \ReflectionClass $class)
    {
        $metadata = CommentParser::parse($method->getDocComment());
        //@access should not be private
        if (
            isset($metadata['access']) && $metadata['access'] === 'private'
        ) {
            return;
        }

        $route = new \stdClass();
        $withoutMethod = true;
        // Generate the default slug for the method.
        $slug = self::generateSlug($method);

        // Check if @url annotation is present in doc comment
        // and override route path accordingly.
        if (isset($metadata['url'])) {
            $url = explode(' ', $metadata['url']);
            $httpMethod = strtolower($url[0]);
            if (in_array($httpMethod, self::$httpMethods)) $route->httpMethod = $httpMethod;
            if (isset($url[1])) $slug = trim($url[1], '/');
        }

        if (isset($metadata['middleware'])) {
            $middleware = explode(' ', $metadata['middleware']);
            $route->middleware = $middleware;
        }

        $slugPath = trim($path . '/' . $slug, '/');

        foreach (self::$httpMethods as $httpMethod) {
            if (self::startsWith($method->name, $httpMethod)) {
                $route->httpMethod = $httpMethod;
                $withoutMethod = false;
                break;
            }
        }

        if ($withoutMethod && !isset($route->httpMethod)) $route->httpMethod = 'get';

        $route->slug = $slugPath;
        $route->target = $class->getName() . '@' . $method->name;

        return $route;
    }

    /**
     * Prioritize routes to ensure specific routes are handled before parameterized ones.
     *
     * @param array $routes
     * @return array
     */
    private static function prioritizeRoutes(array $routes)
    {
        usort($routes, function ($a, $b) {
            $aHasParam = strpos($a->slug, '{') !== false;
            $bHasParam = strpos($b->slug, '{') !== false;

            return $aHasParam === $bHasParam ? strcmp($a->slug, $b->slug) : ($aHasParam ? 1 : -1);
        });

        return $routes;
    }

    /**
     * Check if the string starts with a specific match.
     *
     * @param string $string
     * @param string $match
     * @return bool
     */
    private static function startsWith($string, $match)
    {
        return strpos($string, $match) === 0;
    }

    /**
     * Optionally emit the route statements to a file.
     *
     * @param string $controllerClassName
     * @param array $routes
     */
    private static function emitRoutes($controllerClassName, array $routes)
    {
        $directory = storage_path('tmp/dynamic-routes');
        $filename = class_basename($controllerClassName);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $routeList = "<?php\n// Routes for $controllerClassName\n";
        $routeList .= "use Illuminate\Support\Facades\Route; \n\n";

        foreach ($routes as $route) {
            $mid = isset($route->middleware) ? $route->middleware[0] : '';
            $routeList .= sprintf("Route::%s('%s', '%s')->middleware('%s');\n", $route->httpMethod, $route->slug, $route->target, $mid);
        }

        file_put_contents("$directory/{$filename}.php", $routeList . PHP_EOL);
    }
}
