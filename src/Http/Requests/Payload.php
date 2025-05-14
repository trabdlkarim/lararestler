<?php

namespace Mirak\Lararestler\Http\Requests;

abstract class Payload extends \stdClass
{
    public function __construct()
    {
        $request = app()->make('request');
        foreach ($request->all() as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
