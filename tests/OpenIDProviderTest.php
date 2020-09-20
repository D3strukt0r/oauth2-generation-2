<?php

/**
 * OpenID OAuth2 client
 *
 * @package   OAuth2-OpenID
 * @author    Manuele Vaccari <manuele.vaccari@gmail.com>
 * @copyright Copyright (c) 2017-2020 Manuele Vaccari <manuele.vaccari@gmail.com>
 * @license   https://github.com/D3strukt0r/oauth2-openid/blob/master/LICENSE.txt GNU General Public License v3.0
 * @link      https://github.com/D3strukt0r/oauth2-openid
 */

namespace D3strukt0r\OAuth2\Client\Provider;

use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Tool\QueryBuilderTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class OpenIDProviderTest.
 *
 * @requires PHPUnit >= 8
 *
 * @covers   \D3strukt0r\OAuth2\Client\Provider\OpenIDProvider
 *
 * @internal
 */
final class OpenIDProviderTest extends TestCase
{
    use QueryBuilderTrait;

    /**
     * @var OpenIDProvider The main object
     */
    private $provider;

    protected function setUp(): void
    {
        $this->provider = new OpenIDProvider(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'none',
            ]
        );
    }

    protected function tearDown(): void
    {
        $this->provider = null;
    }

    public function testSetHostInConfig(): void
    {
        $host = uniqid();
        $provider = new OpenIDProvider(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'none',
                'host' => $host,
            ]
        );
        $this->assertSame($host, $provider->getHost());
    }

    public function testSetHostAfterConfig(): void
    {
        $host = uniqid();
        $this->provider->setHost($host);
        $this->assertSame($host, $this->provider->getHost());
    }

    public function testAuthorizationUrl(): void
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

    public function testScopes(): void
    {
        $scopeSeparator = ' ';
        $options = ['scope' => [uniqid(), uniqid()]];
        $query = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url = $this->provider->getAuthorizationUrl($options);
        $encodedScope = $this->buildQueryString($query);
        $this->assertStringContainsString($encodedScope, $url);
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertSame('/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertSame('/oauth/token', $uri['path']);
    }

    public function testGetAccessToken(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn(
            '{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id ' .
            'user:username user:email user:name user:surname user:birthday user:activeAddresses user:addresses ' .
            'user:subscription","refresh_token":"mock_refresh_token"}'
        )
        ;
        $response->method('getHeader')->willReturn(['content-type' => 'json']);
        $response->method('getStatusCode')->willReturn(200);
        $expires = time() + 3600;

        $client = $this->createStub(ClientInterface::class);
        $client->expects($this->once())->method('send')->willReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertSame('mock_access_token', $token->getToken());
        $this->assertGreaterThanOrEqual($expires, $token->getExpires());
        $this->assertSame('mock_refresh_token', $token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testExceptionThrownWhenErrorObjectReceived(): void
    {
        $message = uniqid();
        $status = rand(400, 600);
        $postResponse = $this->createStub(ResponseInterface::class);
        $postResponse
            ->method('getBody')
            ->willReturn(
                '{"error":"' . $status . '","error_description":"' . $message . '"}'
            )
        ;
        $postResponse->method('getHeader')->willReturn(['content-type' => 'json']);
        $postResponse->method('getReasonPhrase')->willReturn('mock_reason_phrase');
        $postResponse->method('getStatusCode')->willReturn($status);

        $client = $this->createStub(ClientInterface::class);
        $client->expects($this->once())->method('send')->willReturn($postResponse);
        $this->provider->setHttpClient($client);

        $this->expectException(IdentityProviderException::class);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testExceptionThrownWhenAuthErrorObjectReceived(): void
    {
        $message = uniqid();
        $status = rand(1, 399);
        $postResponse = $this->createStub(ResponseInterface::class);
        $postResponse
            ->method('getBody')
            ->willReturn('{"error":"' . $status . '","error_description":"' . $message . '"}')
        ;
        $postResponse->method('getHeader')->willReturn(['content-type' => 'json']);
        $postResponse->method('getReasonPhrase')->willReturn('mock_reason_phrase');
        $postResponse->method('getStatusCode')->willReturn($status);

        $client = $this->createStub(ClientInterface::class);
        $client->expects($this->once())->method('send')->willReturn($postResponse);
        $this->provider->setHttpClient($client);

        $this->expectException(IdentityProviderException::class);
        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testResourceOwnerInstance(): void
    {
        $postResponseStub = $this->createStub(ResponseInterface::class);
        $postResponseStub->method('getHeader')->willReturn(['content-type' => 'json']);
        $postResponseStub->method('getStatusCode')->willReturn(200);
        $postResponseStub
            ->method('getBody')
            ->willReturn(
                '{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id"' .
                ',"refresh_token":"mock_refresh_token"}'
            )
        ;

        $userData = [
            'id' => rand(1000, 9999),
        ];
        $userResponseStub = $this->createStub(ResponseInterface::class);
        $userResponseStub->method('getHeader')->willReturn(['content-type' => 'json']);
        $userResponseStub->method('getStatusCode')->willReturn(200);
        $userResponseStub->method('getBody')->willReturn(json_encode($userData));

        $clientStub = $this->createStub(ClientInterface::class);
        $clientStub
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturn($postResponseStub, $userResponseStub)
        ;
        $this->provider->setHttpClient($clientStub);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var OpenIDResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);
        $this->assertInstanceOf(OpenIDResourceOwner::class, $user);
    }

//    public function testGetAuthenticatedRequest(): void
//    {
//        $postResponseStub = $this->createStub(ResponseInterface::class);
//        $postResponseStub->method('getHeader')->willReturn(['content-type' => 'json']);
//        $postResponseStub->method('getStatusCode')->willReturn(200);
//        $postResponseStub
//            ->method('getBody')
//            ->willReturn(
//                '{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id"' .
//                ',"refresh_token":"mock_refresh_token"}'
//            )
//        ;
//
//        $clientStub = $this->createStub(ClientInterface::class);
//        $clientStub->expects($this->once())->method('send')->willReturn($postResponseStub);
//        $this->provider->setHttpClient($clientStub);
//
//        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
//        $method = 'GET';
//        $url = 'https://openid.manuele-vaccari.ch/v1/users/self/feed';
//        $authenticatedRequest = $this->provider->getAuthenticatedRequest($method, $url, $token);
//        $this->assertInstanceOf(RequestInterface::class, $authenticatedRequest);
//        $this->assertSame($method, $authenticatedRequest->getMethod());
//        $this->assertContains('access_token=mock_access_token', $authenticatedRequest->getUri()->getQuery());
//    }
}
