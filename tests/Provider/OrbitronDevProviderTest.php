<?php

/**
 * Votifier PHP Client
 *
 * @package   OAuth2-OrbitronDev
 *
 * @author    Manuele Vaccari <manuele.vaccari@gmail.com>
 * @copyright Copyright (c) 2017-2018 Manuele Vaccari <manuele.vaccari@gmail.com>
 * @license   https://github.com/D3strukt0r/oauth2-orbitrondev/blob/master/LICENSE.md MIT License
 *
 * @link      https://github.com/D3strukt0r/oauth2-orbitrondev
 */

namespace OrbitronDev\OAuth2\Client\Provider;

//use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class OrbitronDevProviderTest extends TestCase
{
    use QueryBuilderTrait;
    use MockeryPHPUnitIntegration;

    /** @var \OrbitronDev\OAuth2\Client\Provider\OrbitronDevProvider */
    protected $provider;

    protected function setUp()
    {
        $this->provider = new OrbitronDevProvider([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
        ]);
    }

    public function testSetHostInConfig()
    {
        $host = uniqid();
        $provider = new OrbitronDevProvider([
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
            'host' => $host,
        ]);
        $this->assertSame($host, $provider->getHost());
    }

    public function testSetHostAfterConfig()
    {
        $host = uniqid();
        $this->provider->setHost($host);
        $this->assertSame($host, $this->provider->getHost());
    }

    public function testAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }

    public function testScopes()
    {
        $scopeSeparator = ' ';
        $options = ['scope' => [uniqid(), uniqid()]];
        $query = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url = $this->provider->getAuthorizationUrl($options);
        $encodedScope = $this->buildQueryString($query);
        $this->assertContains($encodedScope, $url);
    }

    public function testGetAuthorizationUrl()
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertSame('/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertSame('/oauth/token', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $response = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id user:username user:email user:name user:surname user:birthday user:activeaddresses user:addresses user:subscription","refresh_token":"mock_refresh_token"}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $expires = time() + 3600;

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertSame('mock_access_token', $token->getToken());
        $this->assertGreaterThanOrEqual($expires, $token->getExpires());
        $this->assertSame('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testUserData()
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
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id user:username user:email user:name user:surname user:birthday user:activeaddresses user:addresses user:subscription","refresh_token":"mock_refresh_token"}');
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
        /** @var \OrbitronDev\OAuth2\Client\Provider\OrbitronDevResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Test user id
        $this->assertSame($userData['id'], $user->getId());
        $this->assertSame($userData['id'], $user->toArray()['id']);

        // Test username
        $this->assertSame($userData['username'], $user->getUsername());
        $this->assertSame($userData['username'], $user->toArray()['username']);

        // Test email
        $this->assertSame($userData['email'], $user->getEmail());
        $this->assertSame($userData['email'], $user->toArray()['email']);

        // Test name
        $this->assertSame($userData['name'], $user->getFirstName());
        $this->assertSame($userData['name'], $user->toArray()['name']);

        // Test surname
        $this->assertSame($userData['surname'], $user->getSurname());
        $this->assertSame($userData['surname'], $user->toArray()['surname']);

        // Test birthday
        $this->assertSame($userData['birthday'], $user->getBirthday()->getTimestamp());
        $this->assertSame($userData['birthday'], $user->toArray()['birthday']);

        // Test default address (with 1 address)
        $this->assertSame($userData['addresses'][$userData['active_address']], $user->getActiveAddress());
        $this->assertSame($userData['addresses'][$userData['active_address']], $user->toArray()['addresses'][$userData['active_address']]);

        // Test address list
        $this->assertSame($userData['addresses'], $user->getAddresses());
        $this->assertSame($userData['addresses'], $user->toArray()['addresses']);

        // Test subscription type
        $this->assertSame($userData['subscription_type'], $user->getSubscription());
        $this->assertSame($userData['subscription_type'], $user->toArray()['subscription_type']);
    }

    public function testNoActiveAddress()
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
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id user:activeaddresses user:addresses","refresh_token":"mock_refresh_token"}');
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
        /** @var \OrbitronDev\OAuth2\Client\Provider\OrbitronDevResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Check with default
        $this->assertNull($user->getActiveAddress());
        $this->assertNull($user->toArray()['active_address']);
    }

    public function testAddressOn0Addresses()
    {
        $userData = [
            'id' => rand(1000, 9999),
            'active_address' => null,
            'addresses' => null,
        ];

        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id user:activeaddresses user:addresses","refresh_token":"mock_refresh_token"}');
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
        /** @var \OrbitronDev\OAuth2\Client\Provider\OrbitronDevResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Test addresses directly
        $this->assertNull($user->getAddresses());
        $this->assertNull($user->toArray()['addresses']);

        // Check with default
        $this->assertNull($user->getActiveAddress());
        $this->assertNull($user->toArray()['active_address']);
    }

    public function testAddressOnMultiple()
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
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id user:activeaddresses user:addresses","refresh_token":"mock_refresh_token"}');
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
        /** @var \OrbitronDev\OAuth2\Client\Provider\OrbitronDevResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Test default address on multiple
        $this->assertSame($userData['addresses']['2'], $user->getActiveAddress());
        $this->assertSame($userData['addresses']['2'], $user->toArray()['addresses'][$user->toArray()['active_address']]);
    }

    /*public function testExceptionThrownWhenErrorObjectReceived()
    {
        $this->expectException(IdentityProviderException::class);

        $message = uniqid();
        $status = rand(400, 600);
        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"error":"'.$status.'","error_description":"'.$message.'"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getReasonPhrase');
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($postResponse);
        $this->provider->setHttpClient($client);

        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }*/

    /*public function testExceptionThrownWhenAuthErrorObjectReceived()
    {
        $this->expectException(IdentityProviderException::class);

        $message = uniqid();
        $status = rand(400, 600);
        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"error":"'.$status.'","error_description":"'.$message.'"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getReasonPhrase');
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($postResponse);
        $this->provider->setHttpClient($client);

        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }*/

    /*public function testGetAuthenticatedRequest()
    {
        $method = 'GET';
        $url = 'https://api.instagram.com/v1/users/self/feed';
        $accessTokenResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $accessTokenResponse->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id user:username user:email user:name user:surname user:birthday user:activeaddresses user:addresses user:subscription","refresh_token":"mock_refresh_token"}');
        $accessTokenResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $accessTokenResponse->shouldReceive('getStatusCode')->andReturn(200);

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($accessTokenResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $authenticatedRequest = $this->provider->getAuthenticatedRequest($method, $url, $token);
        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $authenticatedRequest);
        $this->assertSame($method, $authenticatedRequest->getMethod());
        $this->assertContains('access_token=mock_access_token', $authenticatedRequest->getUri()->getQuery());
    }*/
}
