<?php

namespace Mirak\Lararestler\Http\Controllers;

use Illuminate\Http\Request;

class ExplorerController extends Controller
{
    public function index(Request $request)
    {
        $currentVersion = config('lararestler.version');
        $version = $request->query('v', $currentVersion);
        if (is_numeric($version)) {
            $version = (int) $version;
            if ($version > $currentVersion  || $version < 0)
                $version = $currentVersion;
        } else {
            $version = $currentVersion;
        }
        
        return view('lararestler::explorer', [
            "version" => $version,
        ]);
    }
}
