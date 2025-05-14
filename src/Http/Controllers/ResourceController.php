<?php

namespace Mirak\Lararestler\Http\Controllers;

use Mirak\Lararestler\Resources;
use Mirak\Lararestler\Restler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Luracast\Restler\Defaults;
use Luracast\Restler\Filter\RateLimit;

class ResourceController extends Controller
{

    public function index(Request $request)
    {
        Defaults::$useUrlBasedVersioning = true;
        Defaults::$cacheDirectory = storage_path('framework/cache/data');
        Defaults::$returnResponse = true;
        Defaults::$fullRequestDataName = "request";

        $api = new Restler(App::environment(['production', 'prod']), true);
        $api->setAPIVersion(config('lararestler.version'));
        // $api->setSupportedFormats('JsonFormat', 'XmlFormat');
        $api->setOverridingFormats('JsonFormat', 'HtmlFormat', 'UploadFormat');

        $apiResources = config('lararestler.resources');
        ksort($apiResources);

        foreach ($apiResources as $path => $resource) {
            $api->addAPIClass($resource, $path);
        }

        Resources::$useFormatAsExtension = false;
        Resources::$hideProtected = false;
        Resources::$listHigherVersions = false;

        $api->addFilterClass(RateLimit::class);
        $api->addAPIClass(Resources::class);
        // $api->addAPIClass(Explorer::class);

        $response = $api->handle();
        return response()->json(json_decode($response, true));
    }
}
