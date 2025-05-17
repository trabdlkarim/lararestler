<?php

namespace Mirak\Lararestler\Http\Requests;

abstract class Model extends \stdClass
{
    public function __construct()
    {
        $request = app()->make('request');
        foreach ($request->all() as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function __call($name, $arguments)
    {
        $request = app()->make('request');
        return $request->{$name}(...$arguments);
    }
}
