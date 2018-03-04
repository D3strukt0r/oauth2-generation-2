<?php

namespace OrbitronDev\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Tool\QueryBuilderTrait;
use Mockery;
use OrbitronDev\OAuth2\Client\Provider\OrbitronDevProvider;

class OrbitronDevTest extends \PHPUnit_Framework_TestCase
{
    use QueryBuilderTrait;

    /** @var \OrbitronDev\OAuth2\Client\Provider\OrbitronDevProvider */
    protected $provider;

    protected function setUp()
    {
        $this->provider = new OrbitronDevProvider([
            'clientId'     => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri'  => 'none',
        ]);
    }

    public function testSetHostInConfig()
    {
        $host = uniqid();
        $provider = new OrbitronDevProvider([
            'clientId'     => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri'  => 'none',
            'host'         => $host,
        ]);
        $this->assertEquals($host, $provider->getHost());
    }

    public function testSetHostAfterConfig()
    {
        $host = uniqid();
        $this->provider->setHost($host);
        $this->assertEquals($host, $this->provider->getHost());
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

        $this->assertEquals('/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl()
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertEquals('/oauth/token', $uri['path']);
    }

    public function testGetAccessToken()
    {
        $response = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn('{"access_token":"mock_access_token","user": {"id": "123","username": "snoopdogg","full_name": "Snoop Dogg","profile_picture": "..."}}');
        $response->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')->times(1)->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertEquals('123', $token->getResourceOwnerId());
    }

    public function testUserData()
    {
        $userId = rand(1000, 9999);
        $name = uniqid();
        $nickname = uniqid();
        $picture = uniqid();
        $description = uniqid();

        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"access_token": "mock_access_token","user": {"id": "1574083","username": "snoopdogg","full_name": "Snoop Dogg","profile_picture": "..."}}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $userResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $userResponse->shouldReceive('getBody')->andReturn('{"data": {"id": "'.$userId.'", "username": "'.$nickname.'", "full_name": "'.$name.'", "bio": "'.$description.'", "profile_picture": "'.$picture.'"}}');
        $userResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);

        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(2)
            ->andReturn($postResponse, $userResponse);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var \OrbitronDev\OAuth2\Client\Provider\OrbitronDevResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($userId, $user->getId());
        $this->assertEquals($userId, $user->toArray()['id']);
        $this->assertEquals($name, $user->getUsername());
        $this->assertEquals($name, $user->toArray()['username']);
        $this->assertEquals($nickname, $user->getEmail());
        $this->assertEquals($nickname, $user->toArray()['email']);
        $this->assertEquals($picture, $user->getFirstName());
        $this->assertEquals($picture, $user->toArray()['name']);
        $this->assertEquals($description, $user->getSurname());
        $this->assertEquals($description, $user->toArray()['surname']);
        $this->assertEquals($description, $user->getBirthday()->getTimestamp());
        $this->assertEquals($description, $user->toArray()['birthday']);
        $this->assertEquals($description, $user->getSubscription());
        $this->assertEquals($description, $user->toArray()['subscription_type']);
    }

    /**
     * @expectedException \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     **/
    public function testExceptionThrownWhenErrorObjectReceived()
    {
        $message = uniqid();
        $status = rand(400, 600);
        $postResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $postResponse->shouldReceive('getBody')->andReturn('{"meta": {"error_type": "OAuthException","code": '.$status.',"error_message": "'.$message.'"}}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getReasonPhrase');
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
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
        $postResponse->shouldReceive('getBody')->andReturn('{"error_type": "OAuthException","code": '.$status.',"error_message": "'.$message.'"}');
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getReasonPhrase');
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);
        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($postResponse);
        $this->provider->setHttpClient($client);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testGetAuthenticatedRequest()
    {
        $method = 'GET';
        $url = 'https://api.instagram.com/v1/users/self/feed';
        $accessTokenResponse = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $accessTokenResponse->shouldReceive('getBody')->andReturn('{"access_token": "mock_access_token","user": {"id": "1574083","username": "snoopdogg","full_name": "Snoop Dogg","profile_picture": "..."}}');
        $accessTokenResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $client = Mockery::mock('GuzzleHttp\ClientInterface');
        $client->shouldReceive('send')
            ->times(1)
            ->andReturn($accessTokenResponse);
        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $authenticatedRequest = $this->provider->getAuthenticatedRequest($method, $url, $token);
        $this->assertInstanceOf('Psr\Http\Message\RequestInterface', $authenticatedRequest);
        $this->assertEquals($method, $authenticatedRequest->getMethod());
        $this->assertContains('access_token=mock_access_token', $authenticatedRequest->getUri()->getQuery());
    }
}
