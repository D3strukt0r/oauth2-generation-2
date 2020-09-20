# OpenID provider for OAuth 2.0 Client

This package provides OpenID OAuth 2.0 support for the PHP League's 
[OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

Project

[![License](https://img.shields.io/github/license/D3strukt0r/oauth2-openid)][license]
[![Version](https://img.shields.io/packagist/v/d3strukt0r/oauth2-openid?label=latest%20release)][packagist]
[![Version (including pre-releases)](https://img.shields.io/packagist/v/D3strukt0r/oauth2-openid?include_prereleases&label=latest%20pre-release)][packagist]
[![Downloads on Packagist](https://img.shields.io/packagist/dt/d3strukt0r/oauth2-openid)][packagist]
[![Required PHP version](https://img.shields.io/packagist/php-v/d3strukt0r/oauth2-openid)][packagist]

master-branch (alias stable, latest)

[![GH Action CI/CD](https://github.com/D3strukt0r/oauth2-openid/workflows/CI/CD/badge.svg?branch=master)][gh-action]
[![Coveralls](https://img.shields.io/coveralls/github/D3strukt0r/oauth2-openid/master)][coveralls]
[![Scrutinizer build status](https://img.shields.io/scrutinizer/build/g/D3strukt0r/oauth2-openid/master?label=scrutinizer%20build)][scrutinizer]
[![Scrutinizer code quality](https://img.shields.io/scrutinizer/quality/g/D3strukt0r/oauth2-openid/master?label=scrutinizer%20code%20quality)][scrutinizer]
[![Codacy grade](https://img.shields.io/codacy/grade/663387eedbbe4732bca55ad70d906c69/master?label=codacy%20code%20quality)][codacy]
[![Docs build status](https://img.shields.io/readthedocs/oauth2-openid/stable)][rtfd]

<!--
develop-branch (alias nightly)

[![GH Action CI/CD](https://github.com/D3strukt0r/oauth2-openid/workflows/CI/CD/badge.svg?branch=develop)][gh-action]
[![Coveralls](https://img.shields.io/coveralls/github/D3strukt0r/oauth2-openid/develop)][coveralls]
[![Scrutinizer build status](https://img.shields.io/scrutinizer/build/g/D3strukt0r/oauth2-openid/develop?label=scrutinizer%20build)][scrutinizer]
[![Scrutinizer code quality](https://img.shields.io/scrutinizer/quality/g/D3strukt0r/oauth2-openid/develop?label=scrutinizer%20code%20quality)][scrutinizer]
[![Codacy grade](https://img.shields.io/codacy/grade/663387eedbbe4732bca55ad70d906c69/develop?label=codacy%20code%20quality)][codacy]
[![Docs build status](https://img.shields.io/readthedocs/oauth2-openid/latest)][rtfd]
-->

This package is compliant with [PSR-1][PSR-1], [PSR-2][PSR-2] and [PSR-4][PSR-4]. If you notice compliance oversights, please send
a patch via pull request.

## Getting Started

### Prerequisites

The following versions of PHP are supported.

-   PHP 7.1
-   PHP 7.2
-   PHP 7.3
-   PHP 7.4
-   HHVM

[OpenID App](https://openid.manuele-vaccari.ch/p/developer-create-application) will also need to be set up, which
will provide you with the `{app-id}` and `{app-secret}` required (see [Usage](#usage) below).

### Installing

To install, use composer:

```shell
composer require d3strukt0r/oauth2-openid
```

### Usage

#### Authorization Code Flow

```php
$provider = new Generation2\OAuth2\Client\Provider\Generation2Provider([
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

#### Refreshing a Token

Refresh tokens are only provided to applications which request offline access. You can specify offline access by setting
the `accessType` option in your provider:

```php
$provider = new Generation2\OAuth2\Client\Provider\Generation2Provider([
    'clientId'     => '{app-id}',     // The client ID assigned to you by the provider
    'clientSecret' => '{app-secret}', // The client password assigned to you by the provider
    'redirectUri'  => 'https://example.com/callback-url',
]);
```

It is important to note that the refresh token is only returned on the first request after this it will be `null`. You
should securely store the refresh token when it is returned:

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
$provider = new Generation2\OAuth2\Client\Provider\Generation2Provider([
    'clientId'     => '{app-id}',     // The client ID assigned to you by the provider
    'clientSecret' => '{app-secret}', // The client password assigned to you by the provider
    'redirectUri'  => 'https://example.com/callback-url',
]);

$newAccessToken = $provider->getAccessToken('refresh_token', [
    'refresh_token' => $oldAccessToken->getRefreshToken()
]);
```

### Scopes

If needed, you can include an array of scopes when getting the authorization url. Example:

```php
$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => [
        'user:id user:email',
    ]
]);
header('Location: ' . $authorizationUrl);
exit;
```

## Running the tests

```shell
./vendor/bin/phpunit
```

## Built With

-   [PHP](https://www.php.net/) - Programming Language
-   [Composer](https://getcomposer.org/) - Dependency Management
-   [PHPUnit](https://phpunit.de/) - Testing the code
-   [Github Actions](https://github.com/features/actions) - Automatic CI (Testing)
-   [Read the docs](https://readthedocs.org) - Documentation

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this repository](https://github.com/D3strukt0r/votifier-client-php/tags).

## Authors

-   **Manuele Vaccari** - [D3strukt0r](https://github.com/D3strukt0r) - _Initial work_

See also the list of [contributors](https://github.com/D3strukt0r/oauth2-openid/contributors) who participated in this project.

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE.txt](LICENSE.txt) file for details

## Acknowledgments

-   Hat tip to anyone whose code was used
-   Inspiration
-   etc

[license]: https://github.com/D3strukt0r/oauth2-openid/blob/master/LICENSE.txt
[packagist]: https://packagist.org/packages/d3strukt0r/oauth2-openid
[gh-action]: https://github.com/D3strukt0r/oauth2-openid/actions
[coveralls]: https://coveralls.io/github/D3strukt0r/oauth2-openid
[scrutinizer]: https://scrutinizer-ci.com/g/D3strukt0r/oauth2-openid/
[codacy]: https://www.codacy.com/manual/D3strukt0r/oauth2-openid
[rtfd]: https://readthedocs.org/projects/oauth2-openid/

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md
