<?php

/**
 * Generation 2 OAuth2 client
 *
 * @package   OAuth2-Generation2
 * @author    Manuele Vaccari <manuele.vaccari@gmail.com>
 * @copyright Copyright (c) 2017-2020 Manuele Vaccari <manuele.vaccari@gmail.com>
 * @license   https://github.com/D3strukt0r/oauth2-generation-2/blob/master/LICENSE.txt GNU General Public License v3.0
 * @link      https://github.com/D3strukt0r/oauth2-generation-2
 */

namespace D3strukt0r\OAuth2\Client\Provider;

use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \D3strukt0r\OAuth2\Client\Provider\Generation2Provider
 */
final class Generation2ProviderTest extends TestCase
{
    use QueryBuilderTrait;
    use MockeryPHPUnitIntegration;

    /** @var Generation2Provider */
    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new Generation2Provider(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'none',
            ]
        );
    }

    public function testSetHostInConfig(): void
    {
        $host = uniqid();
        $provider = new Generation2Provider(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'none',
                'host' => $host,
            ]
        );
        static::assertSame($host, $provider->getHost());
    }

    public function testSetHostAfterConfig(): void
    {
        $host = uniqid();
        $this->provider->setHost($host);
        static::assertSame($host, $this->provider->getHost());
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        static::assertArrayHasKey('client_id', $query);
        static::assertArrayHasKey('redirect_uri', $query);
        static::assertArrayHasKey('state', $query);
        static::assertArrayHasKey('scope', $query);
        static::assertArrayHasKey('response_type', $query);
        static::assertArrayHasKey('approval_prompt', $query);
        static::assertNotNull($this->provider->getState());
    }

    public function testScopes(): void
    {
        $scopeSeparator = ' ';
        $options = ['scope' => [uniqid(), uniqid()]];
        $query = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url = $this->provider->getAuthorizationUrl($options);
        $encodedScope = $this->buildQueryString($query);
        static::assertStringContainsString($encodedScope, $url);
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        static::assertSame('/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        static::assertSame('/oauth/token', $uri['path']);
    }

    public function testGetAccessToken(): void
    {
        $response = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn(
            '{"access_token":"mock_access_token",'.
            '"expires_in":3600,"token_type":"Bearer","scope":"user:id user:username user:email user:name user:surname '.
            'user:birthday user:activeAddresses user:addresses user:subscription",'.
            '"refresh_token":"mock_refresh_token"}'
        )
        ;
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $expires = time() + 3600;

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        static::assertSame('mock_access_token', $token->getToken());
        static::assertGreaterThanOrEqual($expires, $token->getExpires());
        static::assertSame('mock_refresh_token', $token->getRefreshToken());
        static::assertNull($token->getResourceOwnerId());
    }

    public function testUserData(): void
    {
        $userData = [
            'id' => rand(1000, 9999),
            'username' => uniqid(),
            'email' => uniqid(),
            'name' => uniqid(),
            'surname' => uniqid(),
            'birthday' => (new \DateTime())->getTimestamp(),
            'active_address' => '1',
            'addresses' => [
                '1' => [
                    'street' => uniqid(),
                    'house_number' => uniqid(),
                    'zip_code' => uniqid(),
                    'city' => uniqid(),
                    'country' => uniqid(),
                ],
            ],
            'subscription_type' => uniqid(),
        ];

        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn(
            '{"access_token":"mock_access_token",'.
            '"expires_in":3600,"token_type":"Bearer","scope":"user:id user:username user:email user:name user:surname '.
            'user:birthday user:activeAddresses user:addresses user:subscription",'.
            '"refresh_token":"mock_refresh_token"}'
        )
        ;
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn(json_encode($userData));
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(2)->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var Generation2ResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Test user id
        static::assertSame($userData['id'], $user->getId());
        static::assertSame($userData['id'], $user->toArray()['id']);

        // Test username
        static::assertSame($userData['username'], $user->getUsername());
        static::assertSame($userData['username'], $user->toArray()['username']);

        // Test email
        static::assertSame($userData['email'], $user->getEmail());
        static::assertSame($userData['email'], $user->toArray()['email']);

        // Test name
        static::assertSame($userData['name'], $user->getFirstName());
        static::assertSame($userData['name'], $user->toArray()['name']);

        // Test surname
        static::assertSame($userData['surname'], $user->getSurname());
        static::assertSame($userData['surname'], $user->toArray()['surname']);

        // Test birthday
        static::assertSame($userData['birthday'], $user->getBirthday()->getTimestamp());
        static::assertSame($userData['birthday'], $user->toArray()['birthday']);

        // Test default address (with 1 address)
        static::assertSame($userData['addresses'][$userData['active_address']], $user->getActiveAddress());
        static::assertSame(
            $userData['addresses'][$userData['active_address']],
            $user->toArray()['addresses'][$userData['active_address']]
        );

        // Test address list
        static::assertSame($userData['addresses'], $user->getAddresses());
        static::assertSame($userData['addresses'], $user->toArray()['addresses']);

        // Test subscription type
        static::assertSame($userData['subscription_type'], $user->getSubscription());
        static::assertSame($userData['subscription_type'], $user->toArray()['subscription_type']);
    }

    public function testNoActiveAddress(): void
    {
        $userData = [
            'id' => rand(1000, 9999),
            'active_address' => null,
            'addresses' => [
                '1' => [
                    'street' => uniqid(),
                    'house_number' => uniqid(),
                    'zip_code' => uniqid(),
                    'city' => uniqid(),
                    'country' => uniqid(),
                ],
                '2' => [
                    'street' => uniqid(),
                    'house_number' => uniqid(),
                    'zip_code' => uniqid(),
                    'city' => uniqid(),
                    'country' => uniqid(),
                ],
            ],
        ];

        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn(
            '{"access_token":"mock_access_token",'.
            '"expires_in":3600,"token_type":"Bearer","scope":"user:id user:activeAddresses user:addresses",'.
            '"refresh_token":"mock_refresh_token"}'
        )
        ;
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn(json_encode($userData));
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(2)->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var Generation2ResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Check with default
        static::assertNull($user->getActiveAddress());
        static::assertNull($user->toArray()['active_address']);
    }

    public function testAddressOn0Addresses(): void
    {
        $userData = [
            'id' => rand(1000, 9999),
            'active_address' => null,
            'addresses' => null,
        ];

        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn(
            '{"access_token":"mock_access_token",'.
            '"expires_in":3600,"token_type":"Bearer","scope":"user:id user:activeAddresses user:addresses",'.
            '"refresh_token":"mock_refresh_token"}'
        )
        ;
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn(json_encode($userData));
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(2)->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var Generation2ResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Test addresses directly
        static::assertNull($user->getAddresses());
        static::assertNull($user->toArray()['addresses']);

        // Check with default
        static::assertNull($user->getActiveAddress());
        static::assertNull($user->toArray()['active_address']);
    }

    public function testAddressOnMultiple(): void
    {
        $userData = [
            'id' => rand(1000, 9999),
            'active_address' => '2',
            'addresses' => [
                '1' => [
                    'street' => uniqid(),
                    'house_number' => uniqid(),
                    'zip_code' => uniqid(),
                    'city' => uniqid(),
                    'country' => uniqid(),
                ],
                '2' => [
                    'street' => uniqid(),
                    'house_number' => uniqid(),
                    'zip_code' => uniqid(),
                    'city' => uniqid(),
                    'country' => uniqid(),
                ],
            ],
        ];

        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn(
            '{"access_token":"mock_access_token",'.
            '"expires_in":3600,"token_type":"Bearer","scope":"user:id user:activeAddresses user:addresses",'.
            '"refresh_token":"mock_refresh_token"}'
        )
        ;
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn(200);

        $userResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn(json_encode($userData));
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(2)->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var Generation2ResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Test default address on multiple
        static::assertSame($userData['addresses']['2'], $user->getActiveAddress());
        static::assertSame(
            $userData['addresses']['2'],
            $user->toArray()['addresses'][$user->toArray()['active_address']]
        );
    }

//    public function testExceptionThrownWhenErrorObjectReceived(): void
//    {
//        $this->expectException(IdentityProviderException::class);
//
//        $message = uniqid();
//        $status = rand(400, 600);
//        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
//        $postResponse->shouldReceive('getBody')->andReturn('{"error":"'.$status.'","error_description":"'.$message.'"}');
//        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
//        $postResponse->shouldReceive('getReasonPhrase');
//        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
//
//        $client = Mockery::mock('GuzzleHttp\ClientInterface');
//        $client->shouldReceive('send')->times(1)->andReturn($postResponse);
//        $this->provider->setHttpClient($client);
//
//        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
//    }

//    public function testExceptionThrownWhenAuthErrorObjectReceived(): void
//    {
//        $this->expectException(IdentityProviderException::class);
//
//        $message = uniqid();
//        $status = rand(400, 600);
//        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
//        $postResponse->shouldReceive('getBody')->andReturn('{"error":"'.$status.'","error_description":"'.$message.'"}');
//        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
//        $postResponse->shouldReceive('getReasonPhrase');
//        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
//
//        $client = Mockery::mock('GuzzleHttp\ClientInterface');
//        $client->shouldReceive('send')->times(1)->andReturn($postResponse);
//        $this->provider->setHttpClient($client);
//
//        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
//    }

//    public function testGetAuthenticatedRequest(): void
//    {
//        $method = 'GET';
//        $url = 'https://api.instagram.com/v1/users/self/feed';
//        $accessTokenResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
//        $accessTokenResponse->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token",'.
//            '"expires_in":3600,"token_type":"Bearer","scope":"user:id user:username user:email user:name '.
//            'user:surname user:birthday user:activeAddresses user:addresses user:subscription",'.
//            '"refresh_token":"mock_refresh_token"}');
//        $accessTokenResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
//        $accessTokenResponse->shouldReceive('getStatusCode')->andReturn(200);
//
//        $client = Mockery::mock('GuzzleHttp\ClientInterface');
//        $client->shouldReceive('send')->times(1)->andReturn($accessTokenResponse);
//        $this->provider->setHttpClient($client);
//
//        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
//        $authenticatedRequest = $this->provider->getAuthenticatedRequest($method, $url, $token);
//        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $authenticatedRequest);
//        $this->assertSame($method, $authenticatedRequest->getMethod());
//        $this->assertContains('access_token=mock_access_token', $authenticatedRequest->getUri()->getQuery());
//    }
}
