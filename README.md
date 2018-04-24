# OrbitronDev Provider for OAuth 2.0 Client

[![Packagist](https://img.shields.io/packagist/v/d3strukt0r/oauth2-orbitrondev.svg)](https://packagist.org/packages/d3strukt0r/oauth2-orbitrondev)
[![Packagist Pre Release](https://img.shields.io/packagist/vpre/d3strukt0r/oauth2-orbitrondev.svg)](https://packagist.org/packages/d3strukt0r/oauth2-orbitrondev)
[![Packagist](https://img.shields.io/packagist/dt/d3strukt0r/oauth2-orbitrondev.svg)](https://packagist.org/packages/d3strukt0r/oauth2-orbitrondev)
[![Packagist](https://img.shields.io/packagist/l/d3strukt0r/oauth2-orbitrondev.svg)](https://github.com/d3strukt0r/oauth2-orbitrondev/blob/master/LICENSE)

[![Travis](https://img.shields.io/travis/D3strukt0r/oauth2-orbitrondev.svg)](https://travis-ci.org/D3strukt0r/oauth2-orbitrondev)
[![Coveralls](https://img.shields.io/coveralls/D3strukt0r/oauth2-orbitrondev.svg)](https://coveralls.io/github/D3strukt0r/oauth2-orbitrondev)
[![Code Quality](https://img.shields.io/scrutinizer/g/d3strukt0r/oauth2-orbitrondev.svg)](https://scrutinizer-ci.com/g/d3strukt0r/oauth2-orbitrondev/)

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
* PHP 7.2
* HHVM

[OrbitronDev App](https://account.orbitrondev.org/p/developer-create-application) will also need to be set up, which will provide you with the `{app-id}` and `{app-secret}` required (see [Usage](#usage) below).

## Installation

To install, use composer:

```bash
composer require orbitrondev/oauth2-orbitrondev
```

## Usage

### Authorization Code Flow

```php
$provider = new OrbitronDev\OAuth2\Client\Provider\OrbitronDevProvider([
    'clientId'     => '{app-id}',     // The client ID assigned to you by the provider
    'clientSecret' => '{app-secret}', // The client password assigned to you by the provider
    'redirectUri'  => 'https://example.com/callback-url',
]);

if (!empty($_GET['error'])) {

    // Got an error, probably user denied access
    unset($_SESSION['oauth2state']);
    exit('Got error: '.htmlspecialchars($_GET['error_description']).' ('.htmlspecialchars($_GET['error']).')');

// If we don't have an authorization code then get one
} elseif (!isset($_GET['code'])) {

    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $provider->getAuthorizationUrl([
        'scope' => 'user:id user:email',
    ]);

    // Get the state generated for you and store it to the session.
    $_SESSION['oauth2state'] = $provider->getState();

    // Redirect the user to the authorization URL.
    header('Location: '.$authorizationUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    // State is invalid, possible CSRF attack in progress
    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {
    try {

        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code'],
        ]);

        // Use this to interact with an API on the users behalf
        echo $token->getToken();
    
        // Use this to get a new access token if the old one expires
        echo $token->getRefreshToken();
    
        // Exact timestamp when the access token will expire, and need refreshing
        echo $token->getExpires();
    
        // Optional: Now you have a token you can look up a users profile data

        // We got an access token, let's now get the owner details
        $ownerDetails = $provider->getResourceOwner($token);

        // Use these details to create a new profile
        printf('Hello %s!', $ownerDetails->getId());

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());
    }
}
```

### Refreshing a Token

Refresh tokens are only provided to applications which request offline access. You can specify offline access by setting the `accessType` option in your provider:

```php
$provider = new OrbitronDev\OAuth2\Client\Provider\OrbitronDevProvider([
    'clientId'     => '{app-id}',     // The client ID assigned to you by the provider
    'clientSecret' => '{app-secret}', // The client password assigned to you by the provider
    'redirectUri'  => 'https://example.com/callback-url',
]);
```

It is important to note that the refresh token is only returned on the first request after this it will be `null`. You should securely store the refresh token when it is returned:

```php
$accessToken = $provider->getAccessToken('authorization_code', [
    'code' => $code
]);

// persist the token in a database
$refreshToken = $accessToken->getRefreshToken();
```

If you ever need to get a new refresh token you can request one by forcing the approval prompt:

```php
$authorizationUrl = $provider->getAuthorizationUrl(['approval_prompt' => 'force']);
```

Now you have everything you need to refresh an access token using a refresh token:

```php
$provider = new OrbitronDev\OAuth2\Client\Provider\OrbitronDevProvider([
    'clientId'     => '{app-id}',     // The client ID assigned to you by the provider
    'clientSecret' => '{app-secret}', // The client password assigned to you by the provider
    'redirectUri'  => 'https://example.com/callback-url',
]);

$newAccessToken = $provider->getAccessToken('refresh_token', [
    'refresh_token' => $oldAccessToken->getRefreshToken()
]);
```

## Scopes

If needed, you can include an array of scopes when getting the authorization url. Example:

```php
$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => [
        'user:id user:email',
    ]
]);
header('Location: '.$authorizationUrl);
exit;
```

## Testing

```bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](https://github.com/OrbitronDev/oauth2-orbitrondev/blob/master/CONTRIBUTING.md) for details.


## Credits

- [Manuele Vaccari](https://github.com/D3strukt0r)
- [All Contributors](https://github.com/OrbitronDev/oauth2-orbitrondev/contributors)


## License

The MIT License (MIT). Please see [License File](https://github.com/OrbitronDev/oauth2-orbitrondev/blob/master/LICENSE.md) for more information.
