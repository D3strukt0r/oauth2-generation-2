<?php

namespace OrbitronDev\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use OrbitronDev\OAuth2\Client\Provider\OrbitronDevProvider;
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
                ]
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

        $this->assertSame($userData['id'], $user->getId());
        $this->assertSame($userData['id'], $user->toArray()['id']);
        $this->assertSame($userData['username'], $user->getUsername());
        $this->assertSame($userData['username'], $user->toArray()['username']);
        $this->assertSame($userData['email'], $user->getEmail());
        $this->assertSame($userData['email'], $user->toArray()['email']);
        $this->assertSame($userData['name'], $user->getFirstName());
        $this->assertSame($userData['name'], $user->toArray()['name']);
        $this->assertSame($userData['surname'], $user->getSurname());
        $this->assertSame($userData['surname'], $user->toArray()['surname']);
        $this->assertSame($userData['birthday'], $user->getBirthday()->getTimestamp());
        $this->assertSame($userData['birthday'], $user->toArray()['birthday']);
        $this->assertSame($userData['addresses'][$userData['active_address']], $user->getActiveAddress());
        $this->assertSame($userData['addresses'][$userData['active_address']], $user->toArray()['addresses'][$userData['active_address']]);
        $this->assertSame($userData['addresses'], $user->getAddresses());
        $this->assertSame($userData['addresses'], $user->toArray()['addresses']);
        $this->assertSame($userData['subscription_type'], $user->getSubscription());
        $this->assertSame($userData['subscription_type'], $user->toArray()['subscription_type']);
    }

    /**
     * @expectedException \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     **/
    public function testExceptionThrownWhenErrorObjectReceived()
    {
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
    }

    /**
     * @expectedException \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     **/
    public function testExceptionThrownWhenAuthErrorObjectReceived()
    {
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
    }

    public function testGetAuthenticatedRequest()
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
    }
}