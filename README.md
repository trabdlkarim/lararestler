<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/trabdlkarim/assets/main/lararestler/preview.png" alt="Lararestler"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/trabdlkarim/lararestler"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/trabdlkarim/lararestler"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/trabdlkarim/lararestler"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Restler for Laravel

Lararestler is a simple package that lets you to take advantage of all the features from both [Restler 5](https://restler5.luracast.com/) and Laravel as your API backend. With this package you can seamlessly integrate Restler into your existing laravel application without any hassle.

## Installation

You can easily install this package with `conmposer`.

```shell
composer require trabdlkarim/lararestler
```

> [!WARNING]
> You should note that this package works actually with Laravel framework `^12.x`.

After the installation, you must publish the package assets for customization. Run the following command:

```shell
php artisan vendor:publish --tag=lararestler-config --tag=lararestler-assets
```

The following files should be generated if everything works fine:

- `config/lararestler.php`
- `resources/views/vendor/lararestler/explorer.blade.php`
- `public/vendor/lararestler/*`

Now, feel free to change or modifiy any of these files as needed, to adapt them to your project.

## Usage

Before starting writting your API, make sure you've properly set the API resource `namespace` in `config/lararestler.php`.
The default namespace is `App\Http\Resources`.

### Add Resources to API

Create a new class and extend `Mirak\Lararestler\Http\Resources\Resource` class.
Add all the needed private and public methods as you would do with Restler.

Let's define a basic API class called `Users` as an example.

```php
<?php

namespace App\Http\Resources;

use App\Models\User;
use Mirak\Lararestler\Attributes\QueryParam as QParam;
use Mirak\Lararestler\Exceptions\RestException;
use Mirak\Lararestler\Http\Resources\Resource;

class Users extends Resource
{
    /**
     * Retrieve users
     * 
     * @param int $id User ID
     * @param string $email Email Address
     * @param string $name Name
     * @return App\Http\Responses\UserResponse
     */
    public function index(#[QParam('id')] ?int $id = 0, #[QParam('email')] ?string $email = '', #[QParam('name')] ?string $name = '')
    {
        $builder = User::select('*');
        if ($id) {
            $builder = $builder->where('id', $id);
        }
        if ($email) {
            $builder =  $builder->where('email', $email);
        }
        if ($name) {
            $builder = $builder->where('name', 'like', '%' . $name . '%');
        }
        return $builder->get();
    }

    /**
     * Retrieve a user info
     * 
     * Retrieve a given user details from storage
     * 
     * @param int $id User ID
     * @return bool
     * @url GET /users/{id}
     * @throws RestException
     */
    public function get(int $id)
    {
        $user = User::find($id);
        if (!$user) throw new RestException(404, "User #{$id} does not exist.");
        return $user;
    }
}
```

Note that we make use of `Mirak\Lararestler\Attributes\QueryParam` contextual attribute to caputure relevant query parameters from the current request.

There is also another attribute `Mirak\Lararestler\Attributes\RequestBody`, useful when you want to capture all request body as an array.

Afterwards, you need to add your newly created resource class to the API `resources` array in `config/lararestler.php` file as shown below.

```php
<?php

return [
    "name" => "REST API",
    "version" => 1,
    "path_prefix" => "api",
    "middleware" => ["api"],
    "allowed_envs" => ['local'],
    "namespace" => "App\\Http\\Resources",
    "resources" => [
        "users" => "Users",
    ],
];
```

That's it, you're done! Visit the Explorer at `{APP_URL}/api/explorer` to play with the API.

### Request Models

Your request models should extend `Mirak\Lararestler\Http\Requests\Model` class as follows:

```php
<?php

namespace App\Http\Requests;

use Mirak\Lararestler\Http\Requests\Model;

class UserRequest extends Model
{
    /**
     * @var string Name {@required true}
     */
    public $name;

    /**
     * @var string Email Address {@required true}
     */
    public $email;
}
```

Then, you could inject them into your resource class methods and use them as an instance of Laravel `Illuminate\Http\Request` class.
Check the example below.

```php
<?php

namespace App\Http\Resources;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Mirak\Lararestler\Attributes\QueryParam as QParam;
use Mirak\Lararestler\Exceptions\RestException;
use Mirak\Lararestler\Http\Resources\Resource;

class Users extends Resource{
    /**
     * Create a new user
     * 
     * @param UserRequest $postRequest
     * @return App\App\Http\Responses\UserResponse
     */
    public function post(UserRequest $postRequest)
    {
        $v = $postRequest->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $v['name'],
            'email' => $v['email'],
            'phone' => $v['phone'],
            'role' => $v['role'],
            'location' => $v['location'],
            'password' => 'pass123456',
        ]);

        return $user;
    }
}
```

## Protected API Routes

To protect a route in your resource class, simply add the `@middleware` annotation, followed by the name of the middleware, to the method documentation.

```php
/**
 * @param Illuminate\Http\Request $request
 * @url POST /test
 * @middleware auth:web,admin
 */
public function postTest(Request $request){
    //
}
```

## Contributing

Thank you for considering contributing to this package! You should take a look at the contribution guide [here](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Lararestler community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security

If you discover a security vulnerability within this package, please send me an e-mail via [mail@trabdlkarim.me](mailto:mail@trabdlkarim.me). All security vulnerabilities will be promptly addressed.

## License

This is an open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
