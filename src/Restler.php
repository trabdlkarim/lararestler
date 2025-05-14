<?php

namespace Mirak\Lararestler;

use Exception;
use Luracast\Restler\Defaults;
use Luracast\Restler\RestException;
use Luracast\Restler\Restler as RestlerRestler;
use Luracast\Restler\Scope;
use Luracast\Restler\Util;

class Restler extends RestlerRestler
{
    public function handle()
    {
        try {
            try {
                try {
                    $this->get();
                } catch (Exception $e) {
                    $this->requestData
                        = array(Defaults::$fullRequestDataName => array());
                    if (!$e instanceof RestException) {
                        $e = new RestException(
                            500,
                            $this->productionMode ? null : $e->getMessage(),
                            array(),
                            $e
                        );
                    }
                    $this->route();
                    throw $e;
                }
                if (Defaults::$useVendorMIMEVersioning)
                    $this->responseFormat = $this->negotiateResponseFormat();
                $this->route();
            } catch (Exception $e) {
                $this->negotiate();
                if (!$e instanceof RestException) {
                    $e = new RestException(
                        500,
                        $this->productionMode ? null : $e->getMessage(),
                        array(),
                        $e
                    );
                }
                throw $e;
            }
            $this->negotiate();
            $this->preAuthFilter();
            $this->authenticate();
            $this->postAuthFilter();
            $this->validate();
            $this->preCall();
            $this->call();
            $this->compose();
            $this->postCall();
            if (Defaults::$returnResponse) {
                return $this->respond();
            }
            $this->respond();
        } catch (Exception $e) {
            try {
                if (Defaults::$returnResponse) {
                    return $this->message($e);
                }
                $this->message($e);
            } catch (Exception $e2) {
                if (Defaults::$returnResponse) {
                    return $this->message($e2);
                }
                $this->message($e2);
            }
        }
    }

    public function addAPIClass($className, $resourcePath = null)
    {
        try{
            if ($this->productionMode && is_null($this->cached)) {
                $routes = $this->cache->get('routes');
                if (isset($routes) && is_array($routes)) {
                    $this->apiVersionMap = $routes['apiVersionMap'];
                    unset($routes['apiVersionMap']);
                    Routes::fromArray($routes);
                    $this->cached = true;
                } else {
                    $this->cached = false;
                }
            }
            if (isset(Scope::$classAliases[$className])) {
                $className = Scope::$classAliases[$className];
            }
            if (!$this->cached) {
                $maxVersionMethod = '__getMaximumSupportedVersion';
                if (class_exists($className)) {
                    if (method_exists($className, $maxVersionMethod)) {
                        $max = $className::$maxVersionMethod();
                        for ($i = 1; $i <= $max; $i++) {
                            $this->apiVersionMap[$className][$i] = $className;
                        }
                    } else {
                        $this->apiVersionMap[$className][1] = $className;
                    }
                }
                //versioned api
                if (false !== ($index = strrpos($className, '\\'))) {
                    $name = substr($className, 0, $index)
                        . '\\v{$version}' . substr($className, $index);
                } else if (false !== ($index = strrpos($className, '_'))) {
                    $name = substr($className, 0, $index)
                        . '_v{$version}' . substr($className, $index);
                } else {
                    $name = 'v{$version}\\' . $className;
                }

                for ($version = $this->apiMinimumVersion;
                     $version <= $this->apiVersion;
                     $version++) {

                    $versionedClassName = str_replace('{$version}', $version,
                        $name);
                    if (class_exists($versionedClassName)) {
                        Routes::addAPIClass($versionedClassName,
                            Util::getResourcePath(
                                $className,
                                $resourcePath
                            ),
                            $version
                        );
                        if (method_exists($versionedClassName, $maxVersionMethod)) {
                            $max = $versionedClassName::$maxVersionMethod();
                            for ($i = $version; $i <= $max; $i++) {
                                $this->apiVersionMap[$className][$i] = $versionedClassName;
                            }
                        } else {
                            $this->apiVersionMap[$className][$version] = $versionedClassName;
                        }
                    } elseif (isset($this->apiVersionMap[$className][$version])) {
                        Routes::addAPIClass($this->apiVersionMap[$className][$version],
                            Util::getResourcePath(
                                $className,
                                $resourcePath
                            ),
                            $version
                        );
                    }
                }

            }
        } catch (Exception $e) {
            $e = new Exception(
                "addAPIClass('$className') failed. ".$e->getMessage(),
                $e->getCode(),
                $e
            );
            $this->setSupportedFormats('JsonFormat');
            $this->message($e);
        }
    }
    
    /**
     * Find the api method to execute for the requested Url
     */
    protected function route()
    {
        $this->dispatch('route');

        $params = $this->getRequestData();

        //backward compatibility for restler 2 and below
        if (!Defaults::$smartParameterParsing) {
            $params = $params + array(Defaults::$fullRequestDataName => $params);
        }

        $this->apiMethodInfo = $o = Routes::find(
            $this->url,
            $this->requestMethod,
            $this->requestedApiVersion,
            $params
        );
        //set defaults based on api method comments
        if (isset($o->metadata)) {
            foreach (Defaults::$fromComments as $key => $defaultsKey) {
                if (array_key_exists($key, $o->metadata)) {
                    $value = $o->metadata[$key];
                    Defaults::setProperty($defaultsKey, $value);
                }
            }
        }
        if (!isset($o->className))
            throw new RestException(404);

        if (isset($this->apiVersionMap[$o->className])) {
            Scope::$classAliases[Util::getShortName($o->className)]
                = $this->apiVersionMap[$o->className][$this->requestedApiVersion];
        }

        foreach ($this->authClasses as $auth) {
            if (isset($this->apiVersionMap[$auth])) {
                Scope::$classAliases[$auth] = $this->apiVersionMap[$auth][$this->requestedApiVersion];
            } elseif (isset($this->apiVersionMap[Scope::$classAliases[$auth]])) {
                Scope::$classAliases[$auth]
                    = $this->apiVersionMap[Scope::$classAliases[$auth]][$this->requestedApiVersion];
            }
        }
    }
}
