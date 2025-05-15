<html>

<head>
    <title>{{ config('lararestler.name') }} Explorer</title>
    <link rel="stylesheet" href="/vendor/lararestler/css/bootstrap.min.css" />
    <link href='http://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css' />
    <link href="http://netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.css" rel="stylesheet" />
    <link href='/vendor/lararestler/css/screen.css' media='screen' rel='stylesheet' type='text/css' />

    <script src="/vendor/lararestler/lib/bootstrap.min.js" type="text/javascript"></script>
    <script src='/vendor/lararestler/lib/jquery-1.8.0.min.js' type='text/javascript'></script>
    <script src='/vendor/lararestler/lib/jquery.slideto.min.js' type='text/javascript'></script>
    <script src='/vendor/lararestler/lib/jquery.wiggle.min.js' type='text/javascript'></script>
    <script src='/vendor/lararestler/lib/jquery.ba-bbq.min.js' type='text/javascript'></script>
    <script src='/vendor/lararestler/lib/handlebars-1.0.rc.1.js' type='text/javascript'></script>
    <script src='/vendor/lararestler/lib/underscore-min.js' type='text/javascript'></script>
    <script src='/vendor/lararestler/lib/backbone-min.js' type='text/javascript'></script>
    <script src='/vendor/lararestler/lib/swagger.js' type='text/javascript'></script>
    <script src='/vendor/lararestler/swagger-ui.js' type='text/javascript'></script>

    <style type="text/css">
        .swagger-ui-wrap {
            max-width: 960px;
            margin-left: auto;
            margin-right: auto;
        }

        .icon-btn {
            cursor: pointer;
        }

        #message-bar {
            min-height: 30px;
            text-align: center;
            padding-top: 10px;
        }

        .message-success {
            color: #89BF04;
        }

        .message-fail {
            color: #cc0000;
        }
    </style>

    <script type="text/javascript">
        $version = "{{ $version }}"
        $prefix = "{{ trim(config('lararestler.path_prefix'), '/') }}";
        $(function() {
            if ($version) $version = "/v" + $version
            if ($prefix) $version = "/" + $prefix + $version
            window.swaggerUi = new SwaggerUi({
                discoveryUrl: $version + "/resources",
                apiKey: "",
                apiKeyName: "token",
                dom_id: "swagger-ui-container",
                supportHeaderParams: false,
                supportedSubmitMethods: ['get', 'post', 'put', 'patch', 'delete'],
                onComplete: function(swaggerApi, swaggerUi) {
                    if (console) {
                        console.log("Loaded SwaggerUI")
                        console.log(swaggerApi);
                        console.log(swaggerUi);
                    }
                },
                onFailure: function(data) {
                    if (console) {
                        console.log("Unable to Load SwaggerUI");
                        console.log(data);
                    }
                },
                docExpansion: "none"
            });

            window.swaggerUi.load();
        });
    </script>
</head>

<body>
    <div id='header'>
        <div class="swagger-ui-wrap">
            <div class="row justify-content-between">
                <div class="col-6">
                    <a id="logo"
                        href="{{ config('lararestler.path_prefix') ? '/' . trim(config('lararestler.path_prefix'), '/') . '/explorer' : '/explorer' }}"
                        target="_blank">{{ config('lararestler.name') }} Explorer</a>
                </div>
                <div class="col-2 text-end">
                    <select name="version" id="ver_selector" class="form-control-sm">
                        @foreach (range(1, config('lararestler.version')) as $v)
                            <option value="{{ $v }}" @selected($v == $version)>v{{ $v }}.0
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4">
                    <form id='api_selector'>
                        <div class='input'>
                            <input placeholder="http://api.example.com" id="input_baseUrl" name="baseUrl"
                                type="hidden" />
                        </div>
                        <div class='input'>
                            <input class="form-control" placeholder="Access token" id="input_apiKey" name="apiKey"
                                type="text" />
                        </div>
                        <div class='input'>
                            <a class="btn btn-sm py-1" id="explore" href="#">Explore</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <div id="message-bar" class="swagger-ui-wrap">
        &nbsp;
    </div>

    <div id="swagger-ui-container" class="swagger-ui-wrap">

    </div>
    <script>
        $(function() {
            $("#ver_selector").on('change', function() {
                $version = $(this).val();
                $explorerPath =
                    "{{ config('lararestler.path_prefix') ? '/' . trim(config('lararestler.path_prefix'), '/') . '/explorer' : '/explorer' }}"
                window.location.href = $explorerPath + "?" + "v=" + $version;
            });
        });
    </script>
</body>

</html>
