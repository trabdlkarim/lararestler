<?php

namespace Mirak\Lararestler;

use Illuminate\Http\Request as HttpRequest;
use Luracast\Restler\CommentParser;
use Luracast\Restler\Defaults;
use Luracast\Restler\Resources as RestlerResources;
use Luracast\Restler\RestException;
use Luracast\Restler\Routes;
use Luracast\Restler\Util;
use Luracast\Restler\Data\Text;
use Luracast\Restler\Scope;

class Resources extends RestlerResources
{
    /**
     * @access hybrid
     * @return \stdClass
     */
    public function index()
    {
        if (!static::$accessControlFunction && Defaults::$accessControlFunction)
            static::$accessControlFunction = Defaults::$accessControlFunction;
        $version = $this->restler->getRequestedApiVersion();
        $allRoutes = Util::nestedValue(Routes::toArray(), "v$version");
        $r = $this->_resourceListing();
        $map = array();
        if (isset($allRoutes['*'])) {
            $this->_mapResources($allRoutes['*'], $map, $version);
            unset($allRoutes['*']);
        }
        $this->_mapResources($allRoutes, $map, $version);
        foreach ($map as $path => $description) {
            if (!Text::contains($path, '{')) {
                //add id
                $r->apis[] = array(
                    'path' => $path . $this->formatString,
                    'description' => $description
                );
            }
        }
        if (Defaults::$useUrlBasedVersioning && static::$listHigherVersions) {
            $nextVersion = $version + 1;
            if ($nextVersion <= $this->restler->getApiVersion()) {
                list($status, $data) = $this->_loadResource("/v$nextVersion/resources");
                if ($status === 200) {
                    $r->apis = array_merge($r->apis, $data->apis);
                    $r->apiVersion = $data->apiVersion;
                }
            }
        }
        return $r;
    }

    /**
     * @access hybrid
     *
     * @param string $id
     *
     * @throws RestException
     * @return null|stdClass
     *
     * @url GET {id}
     */
    public function get($id = '')
    {
        $version = $this->restler->getRequestedApiVersion();
        if (empty($id)) {
            //do nothing
        } elseif (false !== ($pos = strpos($id, '-v'))) {
            //$version = intval(substr($id, $pos + 2));
            $id = substr($id, 0, $pos);
        } elseif ($id[0] === 'v' && is_numeric($v = substr($id, 1))) {
            $id = '';
            //$version = $v;
        } elseif ($id === 'root' || $id === 'index') {
            $id = '';
        }
        $this->_models = new \stdClass();
        $r = null;
        $count = 0;

        $tSlash = !empty($id);
        $target = empty($id) ? '' : $id;
        $tLen = strlen($target);

        $filter = array();

        $routes
            = Util::nestedValue(Routes::toArray(), "v$version")
            ?: array();

        $prefix = Defaults::$useUrlBasedVersioning ? "/v$version" : '';

        foreach ($routes as $value) {
            foreach ($value as $httpMethod => $route) {
                if (in_array($httpMethod, static::$excludedHttpMethods)) {
                    continue;
                }
                $fullPath = $route['url'];
                if ($fullPath !== $target && !Text::beginsWith($fullPath, $target)) {
                    continue;
                }
                $fLen = strlen($fullPath);
                if ($tSlash) {
                    if ($fLen != $tLen && !Text::beginsWith($fullPath, $target . '/'))
                        continue;
                } elseif ($fLen > $tLen + 1 && $fullPath[$tLen + 1] != '{' && !Text::beginsWith($fullPath, '{')) {
                    //when mapped to root exclude paths that have static parts
                    //they are listed else where under that static part name
                    continue;
                }

                if (!static::verifyAccess($route)) {
                    continue;
                }
                foreach (static::$excludedPaths as $exclude) {
                    if (empty($exclude)) {
                        if ($fullPath === $exclude)
                            continue 2;
                    } elseif (Text::beginsWith($fullPath, $exclude)) {
                        continue 2;
                    }
                }
                $m = $route['metadata'];
                if ($id === '' && $m['resourcePath'] != '') {
                    continue;
                }
                if (isset($filter[$httpMethod][$fullPath])) {
                    continue;
                }
                $filter[$httpMethod][$fullPath] = true;
                // reset body params
                $this->_bodyParam = array(
                    'required' => false,
                    'description' => array()
                );
                $count++;
                $className = $this->_noNamespace($route['className']);
                if (!$r) {
                    $resourcePath = '/'
                        . trim($m['resourcePath'], '/');
                    $r = $this->_operationListing($resourcePath);
                }
                $parts = explode('/', $fullPath);
                $pos = count($parts) - 1;
                if (count($parts) === 1 && $httpMethod === 'GET') {
                } else {
                    for ($i = 0; $i < count($parts); $i++) {
                        if (strlen($parts[$i]) && $parts[$i][0] === '{') {
                            $pos = $i - 1;
                            break;
                        }
                    }
                }
                $nickname = $this->_nickname($route);
                $index = static::$placeFormatExtensionBeforeDynamicParts && $pos > 0 ? $pos : 0;
                if (!empty($parts[$index]))
                    $parts[$index] .= $this->formatString;

                $fullPath = implode('/', $parts);
                $description = isset(
                    $m['classDescription']
                )
                    ? $m['classDescription']
                    : $className . ' API';
                if (empty($m['description'])) {
                    $m['description'] = $this->restler->getProductionMode()
                        ? ''
                        : 'routes to <mark>'
                        . $route['className']
                        . '::'
                        . $route['methodName'] . '();</mark>';
                }
                if (empty($m['longDescription'])) {
                    $m['longDescription'] = $this->restler->getProductionMode()
                        ? ''
                        : 'Add PHPDoc long description to '
                        . "<mark>$className::"
                        . $route['methodName'] . '();</mark>'
                        . '  (the api method) to write here';
                }
                $operation = $this->_operation(
                    $route,
                    $nickname,
                    $httpMethod,
                    $m['description'],
                    $m['longDescription']
                );
                if (isset($m['throws'])) {
                    foreach ($m['throws'] as $exception) {
                        $operation->errorResponses[] = array(
                            'reason' => $exception['message'],
                            'code' => $exception['code']
                        );
                    }
                }
                if (isset($m['param'])) {
                    foreach ($m['param'] as $i => $param) {
                        //combine body params as one
                        $p = $this->_parameter($param);
                        if (in_array($param['type'], ["Request", HttpRequest::class])) {
                            if (in_array($httpMethod, ["GET", "DELETE"])) {
                                unset($m['param'][$i]);
                                continue;
                            } else {
                                $p->paramType = "body";
                                $p->required = true;
                            }
                        }
                        if ($p->paramType === 'body') {
                            $this->_appendToBody($p);
                        } else {
                            $operation->parameters[] = $p;
                        }
                    }
                }
                if (
                    count($this->_bodyParam['description']) ||
                    (
                        $this->_fullDataRequested &&
                        $httpMethod != 'GET' &&
                        $httpMethod != 'DELETE'
                    )
                ) {
                    $operation->parameters[] = $this->_getBody();
                }
                if (isset($m['return']['type'])) {
                    $responseClass = $m['return']['type'];
                    if (is_string($responseClass)) {
                        if (class_exists($responseClass)) {
                            $this->_model($responseClass);
                            $operation->responseClass
                                = $this->_noNamespace($responseClass);
                        } elseif (strtolower($responseClass) === 'array') {
                            $operation->responseClass = 'Array';
                            $rt = $m['return'];
                            if (isset(
                                $rt[CommentParser::$embeddedDataName]['type']
                            )) {
                                $rt = $rt[CommentParser::$embeddedDataName]['type'];
                                if (class_exists($rt)) {
                                    $this->_model($rt);
                                    $operation->responseClass .= '[' .
                                        $this->_noNamespace($rt) . ']';
                                }
                            }
                        }
                    }
                }
                $api = false;

                if (static::$groupOperations) {
                    foreach ($r->apis as $a) {
                        if ($a->path === "$prefix/$fullPath") {
                            $api = $a;
                            break;
                        }
                    }
                }

                if (!$api) {
                    $api = $this->_api("$prefix/$fullPath", $description);
                    $r->apis[] = $api;
                }

                $api->operations[] = $operation;
            }
        }
        if (!$count) {
            throw new RestException(404);
        }
        if (!is_null($r))
            $r->models = $this->_models;
        usort(
            $r->apis,
            function ($a, $b) {
                $order = array(
                    'GET' => 1,
                    'POST' => 2,
                    'PUT' => 3,
                    'PATCH' => 4,
                    'DELETE' => 5
                );
                return
                    $a->operations[0]->httpMethod ==
                    $b->operations[0]->httpMethod
                    ? strcmp($a->path, $b->path)
                    : strcmp(
                        $order[$a->operations[0]->httpMethod],
                        $order[$b->operations[0]->httpMethod]
                    );
            }
        );
        return $r;
    }

    protected function _appendToBody($p)
    {
        if (($p->name === Defaults::$fullRequestDataName) && in_array($p->dataType, [HttpRequest::class, "Request"])) {
            $this->_fullDataRequested = $p;
            unset($this->_bodyParam['names'][Defaults::$fullRequestDataName]);
            return;
        }
        $this->_bodyParam['description'][$p->name]
            = "$p->name"
            . ' : <tag>' . $p->dataType . '</tag> '
            . ($p->required ? ' <i>(required)</i> - ' : ' - ')
            . $p->description;
        $this->_bodyParam['required'] = $p->required
            || $this->_bodyParam['required'];
        $this->_bodyParam['names'][$p->name] = $p;
    }


    protected function _getBody()
    {
        $r = new \stdClass();
        $n = isset($this->_bodyParam['names'])
            ? array_values($this->_bodyParam['names'])
            : array();
        if (count($n) === 1) {
            if (isset($this->_models->{$this->_noNamespace($n[0]->dataType)})) {
                // ============ custom class ===================
                $r = $n[0];
                $c = $this->_models->{$this->_noNamespace($r->dataType)};
                $a = $c->properties;
                $r->description = "Paste JSON data here";
                if (count($a)) {
                    $r->description .= " with the following"
                        . (count($a) > 1 ? ' properties.' : ' property.');
                    foreach ($a as $k => $v) {
                        $r->description .= "<hr/>$k : <tag>"
                            . $v['type'] . '</tag> '
                            . (isset($v['required']) ? '(required)' : '')
                            . ' - ' . $v['description'];
                    }
                }
                $r->dataType = $this->_noNamespace($r->dataType);
                $r->defaultValue = "{\n    \""
                    . implode(
                        "\": \"\",\n    \"",
                        array_keys($c->properties)
                    )
                    . "\": \"\"\n}";
                return $r;
            } elseif (false !== ($p = strpos($n[0]->dataType, '['))) {
                // ============ array of custom class ===============
                $r = $n[0];
                $t = substr($r->dataType, $p + 1, -1);
                if ($c = Util::nestedValue($this->_models, $this->_noNamespace($t))) {
                    $a = $c->properties;
                    $r->description = "Paste JSON data here";
                    if (count($a)) {
                        $r->description .= " with an array of objects with the following"
                            . (count($a) > 1 ? ' properties.' : ' property.');
                        foreach ($a as $k => $v) {
                            $r->description .= "<hr/>$k : <tag>"
                                . $v['type'] . '</tag> '
                                . (isset($v['required']) ? '(required)' : '')
                                . ' - ' . $v['description'];
                        }
                    }
                    $r->dataType = "Array[" . $this->_noNamespace($t) . "]";
                    $r->defaultValue = "[\n    {\n        \""
                        . implode(
                            "\": \"\",\n        \"",
                            array_keys($c->properties)
                        )
                        . "\": \"\"\n    }\n]";
                    return $r;
                } else {
                    $r->description = "Paste JSON data here with an array of $t values.";
                    $r->defaultValue = "[ ]";
                    return $r;
                }
            } elseif ($n[0]->dataType === 'Array') {
                // ============ array ===============================
                $r = $n[0];
                $r->description = "Paste JSON array data here"
                    . ($r->required ? ' (required) . ' : '. ')
                    . "<br/>$r->description";
                $r->defaultValue = "[\n    {\n        \""
                    . "property\" : \"\"\n    }\n]";
                return $r;
            } elseif ($n[0]->dataType === 'Object') {
                // ============ object ==============================
                $r = $n[0];
                $r->description = "Paste JSON object data here"
                    . ($r->required ? ' (required) . ' : '. ')
                    . "<br/>$r->description";
                $r->defaultValue = "{\n    \""
                    . "property\" : \"\"\n}";
                return $r;
            }
        }
        $p = array_values($this->_bodyParam['description']);
        $r->name = 'payload';
        $r->description = "Paste JSON data here";
        if (count($p) === 0 && $this->_fullDataRequested) {
            $r->required = $this->_fullDataRequested->required;
            $r->defaultValue = "{\n    \"field\" : \"value\"\n}";
        } else {
            $r->description .= " with the following"
                . (count($p) > 1 ? ' properties.' : ' property.')
                . '<hr/>'
                . implode("<hr/>", $p);
            $r->required = $this->_bodyParam['required'];
            // Create default object that includes parameters to be submitted
            $defaultObject = new \StdClass();
            foreach ($this->_bodyParam['names'] as $name => $values) {
                if (!$values->required)
                    continue;
                if (class_exists($values->dataType)) {
                    $myClassName = $values->dataType;
                    $defaultObject->$name = new $myClassName();
                } else {
                    $defaultObject->$name = '';
                }
            }
            $r->defaultValue = Scope::get('JsonFormat')->encode($defaultObject, true);
        }
        $r->paramType = 'body';
        $r->allowMultiple = false;
        $r->dataType = 'Object';
        return $r;
    }

    protected function _loadResource($path)
    {
        $url = $this->restler->getBaseUrl() . $path;
        $ch = curl_init($url . (empty($_GET) ? '' : '?' . http_build_query($_GET)));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'X-CSRF-TOKEN: ' . csrf_token(),
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $result = json_decode(curl_exec($ch));
        $http_status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array($http_status, $result);
    }


    protected function _parameter($param)
    {
        $r = new \stdClass();
        $r->name = $param['name'];
        $r->description = !empty($param['description'])
            ? $param['description'] . '.'
            : ($this->restler->getProductionMode()
                ? ''
                : 'add <mark>@param {type} $' . $r->name
                . ' {comment}</mark> to describe here');
        //paramType can be path or query or body or header
        $r->paramType = Util::nestedValue($param, CommentParser::$embeddedDataName, 'from') ?: 'query';
        $r->required = isset($param['required']) && $param['required'];
        if (isset($param['default'])) {
            $r->defaultValue = $param['default'];
        } elseif (isset($param[CommentParser::$embeddedDataName]['example'])) {
            $r->defaultValue
                = $param[CommentParser::$embeddedDataName]['example'];
        }
        $r->allowMultiple = false;
        $type = 'string';
        if (isset($param['type'])) {
            $type = $param['type'];
            if (is_array($type)) {
                $type = array_shift($type);
            }
            if ($type === 'array') {
                $contentType = Util::nestedValue(
                    $param,
                    CommentParser::$embeddedDataName,
                    'type'
                );
                if ($contentType) {
                    if ($contentType === 'indexed') {
                        $type = 'Array';
                    } elseif ($contentType === 'associative') {
                        $type = 'Object';
                    } else {
                        $type = "Array[$contentType]";
                    }
                    if (Util::isObjectOrArray($contentType)) {
                        $this->_model($contentType);
                    }
                } elseif (isset(static::$dataTypeAlias[$type])) {
                    $type = static::$dataTypeAlias[$type];
                }
            } elseif (Util::isObjectOrArray($type)) {
                $this->_model($type);
            } elseif (isset(static::$dataTypeAlias[$type])) {
                $type = static::$dataTypeAlias[$type];
            }
        }
        $r->dataType = $type;
        if (isset($param[CommentParser::$embeddedDataName])) {
            $p = $param[CommentParser::$embeddedDataName];
            if (isset($p['min']) && isset($p['max'])) {
                $r->allowableValues = array(
                    'valueType' => 'RANGE',
                    'min' => $p['min'],
                    'max' => $p['max'],
                );
            } elseif (isset($p['choice'])) {
                $r->allowableValues = array(
                    'valueType' => 'LIST',
                    'values' => $p['choice']
                );
            }
        }
        return $r;
    }
}
