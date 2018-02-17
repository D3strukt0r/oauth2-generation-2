# OrbitronDev Provider for OAuth 2.0 Client

[![Build Status](https://img.shields.io/travis/orbitrondev/oauth2-orbitrondev.svg)](https://travis-ci.org/OrbitronDev/oauth2-orbitrondev)
[![Code Coverage](https://img.shields.io/coveralls/orbitrondev/oauth2-orbitrondev.svg)](https://coveralls.io/r/OrbitronDev/oauth2-orbitrondev)
[![Code Quality](https://img.shields.io/scrutinizer/g/orbitrondev/oauth2-orbitrondev.svg)](https://scrutinizer-ci.com/g/orbitrondev/oauth2-orbitrondev/)
[![License](https://img.shields.io/packagist/l/orbitrondev/oauth2-orbitrondev.svg)](https://github.com/orbitrondev/oauth2-orbitrondev/blob/master/LICENSE.md)
[![Latest Stable Version](https://img.shields.io/packagist/v/orbitrondev/oauth2-orbitrondev.svg)](https://packagist.org/packages/orbitrondev/oauth2-orbitrondev)

This package provides OrbitronDev OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

This package is compliant with [PSR-1][], [PSR-2][] and [PSR-4][]. If you notice compliance oversights, please send
a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## Requirements

The following versions of PHP are supported.

* PHP 5.6
* PHP 7.0
* PHP 7.1
* HHVM

[OrbitronDev App](https://account.orbitrondev.org/p/developer-create-application) will also need to be set up, which will provide you with the `{app-id}` and `{app-secret}` required (see [Usage](#usage) below).

## Installation

To install, use composer:

```
composer require orbitrondev/oauth2-orbitrondev
```

## Usage

### Authorization Code Flow

```php
$provider = new OrbitronDev\OAuth2\Client\Provider\OrbitronDev([
    'clientId'     => '{app-id}',
    'clientSecret' => '{app-secret}',
    'redirectUri'  => 'https://example.com/callback-url',
]);

if (!empty($_GET['error'])) {

    // Got an error, probably user denied access
    exit('Got error: ' . htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'));

} elseif (empty($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;

} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    // State is invalid, possible CSRF attack in progress
    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the owner details
        $ownerDetails = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $ownerDetails->getFirstName());

    } catch (Exception $e) {

        // Failed to get user details
        exit('Something went wrong: ' . $e->getMessage());

    }

    // Use this to interact with an API on the users behalf
    echo $token->getToken();

    // Use this to get a new access token if the old one expires
    echo $token->getRefreshToken();

    // Number of seconds until the access token will expire, and need refreshing
    echo $token->getExpires();
}
```

### Refreshing a Token

Refresh tokens are only provided to applications which request offline access. You can specify offline access by setting the `accessType` option in your provider:

```php
$provider = new OrbitronDev\OAuth2\Client\Provider\OrbitronDev([
    'clientId'     => '{app-id}',
    'clientSecret' => '{app-secret}',
    'redirectUri'  => 'https://example.com/callback-url',
]);
```

It is important to note that the refresh token is only returned on the first request after this it will be `null`. You should securely store the refresh token when it is returned:

```php
$token = $provider->getAccessToken('authorization_code', [
    'code' => $code
]);

// persist the token in a database
$refreshToken = $token->getRefreshToken();
```

If you ever need to get a new refresh token you can request one by forcing the approval prompt:

```php
$authUrl = $provider->getAuthorizationUrl(['approval_prompt' => 'force']);
```

Now you have everything you need to refresh an access token using a refresh token:

```php
$provider = new OrbitronDev\OAuth2\Client\Provider\OrbitronDev([
    'clientId'     => '{app-id}',
    'clientSecret' => '{app-secret}',
    'redirectUri'  => 'https://example.com/callback-url',
]);

$grant = new League\OAuth2\Client\Grant\RefreshToken();
$token = $provider->getAccessToken($grant, ['refresh_token' => $refreshToken]);
```

## Scopes

If needed, you can include an array of scopes when getting the authorization url. Example:

```
$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => [
        'user:email',
    ]
]);
header('Location: ' . $authorizationUrl);
exit;
```

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/OrbitronDev/oauth2-orbitrondev/blob/master/CONTRIBUTING.md) for details.


## Credits

- [Manuele Vaccari](https://github.com/D3strukt0r)
- [All Contributors](https://github.com/OrbitronDev/oauth2-orbitrondev/contributors)


## License

The MIT License (MIT). Please see [License File](https://github.com/OrbitronDev/oauth2-orbitrondev/blob/master/LICENSE.md) for more information.
