<?php

return [
    "name" => "REST API",
    "version" => 1,
    "path_prefix" => "api",
    "middleware" => ["api"],
    "allowed_envs" => ['local'],
    "namespace" => "App\\Http\\Resources",
    "resources" => [
        // Your API resources go here
        // "path" => "class"
    ],
];