<?php

namespace Mirak\Lararestler\Http\Resources;

use Luracast\Restler\iProvideMultiVersionApi;

abstract class Resource implements iProvideMultiVersionApi
{
    public static function __getMaximumSupportedVersion()
    {
        return config('lararestler.version');
    }
}