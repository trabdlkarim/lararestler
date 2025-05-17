<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your API, which will be used when the
    | package needs to place the API's name in UI elements like in the API Explorer.
    |
    */
    "name" => "REST API",

    /*
    |--------------------------------------------------------------------------
    | API Supported Version
    |--------------------------------------------------------------------------
    |
    | This value is the maximum version number supported by the API. It will be 
    | passed to Restler::setAPIVersion() method.
    |
    */
    "version" => 1,


    /*
    |--------------------------------------------------------------------------
    | API Path Prefix
    |--------------------------------------------------------------------------
    |
    | This is the path prefix of your API. It's used to determine the API base url.
    | If left empty, the package will assume the API base url is the same as 
    | the application base url (i.e. APP_URL).
    |
    */
    "path_prefix" => "api",

    /*
    |--------------------------------------------------------------------------
    | Middleware Group
    |--------------------------------------------------------------------------
    |
    | Determine which middlware group that should be applied to all the API routes.
    | The default middlware group applied is  the 'api' group. You are free to use
    | any predefined or custom middleware groups here. 
    |
    */
    "middleware" => ["api"],

    /*
    |--------------------------------------------------------------------------
    | Allowed Environements
    |--------------------------------------------------------------------------
    |
    | Add all the environments where you would like the API Explorer to be visible.
    | The default behaviour is to allow only the Explorer in local or development
    | environment for security reasons.
    |
    */
    "allowed_envs" => ['local'],

    /*
    |--------------------------------------------------------------------------
    | API Resource Namespace
    |--------------------------------------------------------------------------
    |
    | This is the parent namespace where all the API resources reside. It
    | tells Lararestler where your resources are located. The default namespace
    | is 'App\Http\Resources', but you're free to set it to whatever namespace 
    | you want. If not set or empty, you're force to use the class full name
    | when adding resources to the API.
    |
    */
    "namespace" => "App\\Http\\Resources",

    /*
    |--------------------------------------------------------------------------
    | API Resources
    |--------------------------------------------------------------------------
    |
    | Add all your API resources here. If config('lararestler.namespace') is set,
    | it's possible to use only the class base name, otherwise you must use the 
    | class full name.
    |
    */
    "resources" => [
        // "path" => "MyClass"
    ],
];
