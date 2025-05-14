<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Mirak\Lararestler\Http\Controllers\ExplorerController;
use Mirak\Lararestler\Http\Controllers\ResourceController;
use Mirak\Lararestler\RestApi;

if (App::environment(['local', 'dev', 'testing'])) {
    Route::get(trim(config('lararestler.path_prefix'), '/') . '/explorer', [ExplorerController::class, 'index']);
}

Route::prefix(trim(config('lararestler.path_prefix'), '/'))
    ->middleware(config('lararestler.middleware'))->group(
        function () {
            if (App::environment(['local', 'dev', 'testing'])) {
                $versionRegex = 'v[0-9]+';
                $pathRegex = '[a-zA-Z0-9_-]+';

                Route::get('/resources', [ResourceController::class, 'index']);
                Route::get('/resources/{path}', [ResourceController::class, 'index'])->where('path', $pathRegex);

                Route::get('{version}/resources', [ResourceController::class, 'index'])->where('version', $versionRegex);
                Route::get('{version}/resources/{path}', [ResourceController::class, 'index'])
                    ->where('path', $pathRegex)
                    ->where('version', $versionRegex);
            }

            RestApi::routes();
        }
    );
