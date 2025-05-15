<?php

namespace Mirak\Lararestler;

use Exception;
use Illuminate\Http\Request;
use Luracast\Restler\CommentParser;
use Luracast\Restler\Defaults;
use Luracast\Restler\RestException;
use Luracast\Restler\Routes as RestlerRoutes;
use Luracast\Restler\Scope;
use Luracast\Restler\Data\Text;
use Luracast\Restler\Util;
use ReflectionClass;
use ReflectionMethod;



class Routes extends RestlerRoutes
{

    public static function addAPIClass($className, $resourcePath = '', $version = 1)
    {
        /*
         * Mapping Rules
         * =============
         *
         * - Optional parameters should not be mapped to URL
         * - If a required parameter is of primitive type
         *      - If one of the self::$prefixingParameterNames
         *              - Map it to URL
         *      - Else If request method is POST/PUT/PATCH
         *              - Map it to body
         *      - Else If request method is GET/DELETE
         *              - Map it to body
         * - If a required parameter is not primitive type
         *      - Do not include it in URL
         */
        $class = new ReflectionClass($className);
        $dataName = CommentParser::$embeddedDataName;
        try {
            $classMetadata = CommentParser::parse($class->getDocComment());
        } catch (Exception $e) {
            throw new RestException(500, "Error while parsing comments of `$className` class. " . $e->getMessage());
        }
        $classMetadata['scope'] = $scope = static::scope($class);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC +
            ReflectionMethod::IS_PROTECTED);
        foreach ($methods as $method) {
            $methodUrl = strtolower($method->getName());
            //method name should not begin with _
            if ($methodUrl[0] === '_') {
                continue;
            }
            $doc = $method->getDocComment();
            try {
                $metadata = CommentParser::parse($doc) + $classMetadata;
            } catch (Exception $e) {
                throw new RestException(500, "Error while parsing comments of `{$className}::{$method->getName()}` method. " . $e->getMessage());
            }
            //@access should not be private
            if (
                isset($metadata['access'])
                && $metadata['access'] === 'private'
            ) {
                continue;
            }

            if (isset($metadata['middleware']) && str_contains($metadata['middleware'], 'sanctum')) {
                $metadata['access'] = 'protected';
            }

            $arguments = array();
            $defaults = array();
            $params = $method->getParameters();
            $position = 0;
            $pathParams = array();
            $allowAmbiguity
                = (isset($metadata['smart-auto-routing'])
                    && $metadata['smart-auto-routing'] != 'true')
                || !Defaults::$smartAutoRouting;
            $metadata['resourcePath'] = trim($resourcePath, '/');
            if (isset($classMetadata['description'])) {
                $metadata['classDescription'] = $classMetadata['description'];
            }
            if (isset($classMetadata['classLongDescription'])) {
                $metadata['classLongDescription']
                    = $classMetadata['longDescription'];
            }
            if (!isset($metadata['param'])) {
                $metadata['param'] = array();
            }
            if (isset($metadata['return']['type'])) {
                if ($qualified = Scope::resolve($metadata['return']['type'], $scope))
                    list($metadata['return']['type'], $metadata['return']['children']) =
                        static::getTypeAndModel(new ReflectionClass($qualified), $scope);
            } else {
                //assume return type is array
                $metadata['return']['type'] = 'array';
            }

            foreach ($params as $k => $param) {
                $children = array();
                $type = version_compare(phpversion(), '8.0.0', '<') ?
                    // PHP < 8.0
                    ($param->isArray() ? 'array' : $param->getClass()) :
                    // PHP >= 8.0
                    ($param->getType() && $param->getType()->getName() === 'array' ? 'array' : (
                        $param->getType() && !$param->getType()->isBuiltin()
                        ? new ReflectionClass($param->getType()->getName())
                        : null
                    ));

                if ($type && ($type instanceof ReflectionClass) && in_array($type->getName(), [Request::class, "Request"])) {
                    unset($params[$k]);
                    continue;
                }

                $arguments[$param->getName()] = $position;
                $defaults[$position] = $param->isDefaultValueAvailable() ?
                    $param->getDefaultValue() : null;
                if (!isset($metadata['param'][$position])) {
                    $metadata['param'][$position] = array();
                }
                $m = &$metadata['param'][$position];
                $m['name'] = $param->getName();
                if (!isset($m[$dataName])) {
                    $m[$dataName] = array();
                }
                $p = &$m[$dataName];
                if (empty($m['label']))
                    $m['label'] = Text::title($m['name']);
                if (is_null($type) && isset($m['type'])) {
                    $type = $m['type'];
                }
                if (isset(static::$fieldTypesByName[$m['name']]) && empty($p['type']) && $type === 'string') {
                    $p['type'] = static::$fieldTypesByName[$m['name']];
                }
                $m['default'] = $defaults[$position];
                $m['required'] = !$param->isOptional();
                $contentType = Util::nestedValue($p, 'type');
                if ($type === 'array' && $contentType && $qualified = Scope::resolve($contentType, $scope)) {
                    list($p['type'], $children, $modelName) = static::getTypeAndModel(
                        new ReflectionClass($qualified),
                        $scope,
                        $className . Text::title($methodUrl),
                        $p
                    );
                }
                if ($type instanceof ReflectionClass) {
                    list($type, $children, $modelName) = static::getTypeAndModel(
                        $type,
                        $scope,
                        $className . Text::title($methodUrl),
                        $p
                    );
                } elseif ($type && is_string($type) && $qualified = Scope::resolve($type, $scope)) {
                    list($type, $children, $modelName)
                        = static::getTypeAndModel(
                            new ReflectionClass($qualified),
                            $scope,
                            $className . Text::title($methodUrl),
                            $p
                        );
                }
                if (isset($type)) {
                    $m['type'] = $type;
                }

                $m['children'] = $children;
                if (isset($modelName)) {
                    $m['model'] = $modelName;
                }
                if ($m['name'] === Defaults::$fullRequestDataName) {
                    $from = 'body';
                    if (!isset($m['type'])) {
                        $type = $m['type'] = 'array';
                    }
                } elseif (isset($p['from'])) {
                    $from = $p['from'];
                } else {
                    if ((isset($type) && Util::isObjectOrArray($type))) {
                        $from = 'body';
                        if (!isset($type)) {
                            $type = $m['type'] = 'array';
                        }
                    } elseif ($m['required'] && in_array($m['name'], static::$prefixingParameterNames)) {
                        $from = 'path';
                    } else {
                        $from = 'body';
                    }
                }
                $p['from'] = $from;
                if (!isset($m['type'])) {
                    $type = $m['type'] = static::type($defaults[$position]);
                }

                if ($allowAmbiguity || $from === 'path') {
                    $pathParams[] = $position;
                }
                $position++;
            }

            $accessLevel = 0;
            if ($method->isProtected()) {
                $accessLevel = 3;
            } elseif (isset($metadata['access'])) {
                if ($metadata['access'] === 'protected') {
                    $accessLevel = 2;
                } elseif ($metadata['access'] === 'hybrid') {
                    $accessLevel = 1;
                }
            } elseif (isset($metadata['protected'])) {
                $accessLevel = 2;
            }
            /*
            echo " access level $accessLevel for $className::"
            .$method->getName().$method->isProtected().PHP_EOL;
            */

            // take note of the order
            $call = array(
                'url' => null,
                'className' => $className,
                'path' => rtrim($resourcePath, '/'),
                'methodName' => $method->getName(),
                'arguments' => $arguments,
                'defaults' => $defaults,
                'metadata' => $metadata,
                'accessLevel' => $accessLevel,
            );
            // if manual route
            if (preg_match_all(
                '/@url\s+(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)'
                    . '[ \t]*\/?(\S*)/s',
                $doc,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $match) {
                    $httpMethod = $match[1];
                    $url = rtrim($resourcePath . $match[2], '/');
                    //deep copy the call, as it may change for each @url
                    $copy = unserialize(serialize($call));
                    foreach ($copy['metadata']['param'] as $i => $p) {
                        $inPath =
                            strpos($url, '{' . $p['name'] . '}') ||
                            strpos($url, ':' . $p['name']);
                        if ($inPath) {
                            $copy['metadata']['param'][$i][$dataName]['from'] = 'path';
                        } elseif (isset($p[$dataName]['from']) && 'header' === $p[$dataName]['from']) {
                            continue;
                        } elseif ($httpMethod === 'GET' || $httpMethod === 'DELETE') {
                            $copy['metadata']['param'][$i][$dataName]['from'] = 'query';
                        } elseif (empty($p[$dataName]['from']) || $p[$dataName]['from'] === 'path') {
                            $copy['metadata']['param'][$i][$dataName]['from'] = 'body';
                        }
                    }
                    $url = preg_replace_callback(
                        '/{[^}]+}|:[^\/]+/',
                        function ($matches) use ($copy) {
                            $match = trim($matches[0], '{}:');
                            $index = $copy['arguments'][$match];
                            return '{' .
                                Routes::typeChar(isset(
                                    $copy['metadata']['param'][$index]['type']
                                )
                                    ? $copy['metadata']['param'][$index]['type']
                                    : null)
                                . $index . '}';
                        },
                        $url
                    );
                    static::addPath($url, $copy, $httpMethod, $version);
                }
                //if auto route enabled, do so
            } elseif (Defaults::$autoRoutingEnabled) {
                // no configuration found so use convention
                if (preg_match_all(
                    '/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)/i',
                    $methodUrl,
                    $matches
                )) {
                    $httpMethod = strtoupper($matches[0][0]);
                    $methodUrl = substr($methodUrl, strlen($httpMethod));
                } else {
                    $httpMethod = 'GET';
                }
                if ($methodUrl === 'index') {
                    $methodUrl = '';
                }
                $url = empty($methodUrl) ? rtrim($resourcePath, '/')
                    : $resourcePath . $methodUrl;
                for ($position = 0; $position < count($params); $position++) {
                    $from = $metadata['param'][$position][$dataName]['from'];
                    if (
                        $from === 'body' && ($httpMethod === 'GET' ||
                            $httpMethod === 'DELETE')
                    ) {
                        $call['metadata']['param'][$position][$dataName]['from']
                            = 'query';
                    }
                }
                if (empty($pathParams) || $allowAmbiguity) {
                    static::addPath($url, $call, $httpMethod, $version);
                }
                $lastPathParam = end($pathParams);
                foreach ($pathParams as $position) {
                    if (!empty($url))
                        $url .= '/';
                    $url .= '{' .
                        static::typeChar(isset($call['metadata']['param'][$position]['type'])
                            ? $call['metadata']['param'][$position]['type']
                            : null)
                        . $position . '}';
                    if ($allowAmbiguity || $position === $lastPathParam) {
                        static::addPath($url, $call, $httpMethod, $version);
                    }
                }
            }
        }
    }
}
