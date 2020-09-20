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

use DateTime;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Class OpenIDResourceOwnerTest.
 *
 * @requires PHPUnit >= 8
 *
 * @covers   \D3strukt0r\OAuth2\Client\Provider\OpenIDResourceOwner
 *
 * @internal
 */
final class OpenIDResourceOwnerTest extends TestCase
{
    /**
     * @var OpenIDProvider The main object
     */
    private $provider;

    /**
     * @var Stub|ResponseInterface The response after asking for information
     */
    private $postResponseStub;

    /**
     * @var Stub|ResponseInterface The response containing the information
     */
    private $userResponseStub;

    protected function setUp(): void
    {
        $this->provider = new OpenIDProvider(
            [
                'clientId' => 'mock_client_id',
                'clientSecret' => 'mock_secret',
                'redirectUri' => 'none',
            ]
        );

        $this->postResponseStub = $this->createStub(ResponseInterface::class);
        $this->postResponseStub->method('getHeader')->willReturn(['content-type' => 'json']);
        $this->postResponseStub->method('getStatusCode')->willReturn(200);

        $this->userResponseStub = $this->createStub(ResponseInterface::class);
        $this->userResponseStub->method('getHeader')->willReturn(['content-type' => 'json']);
        $this->userResponseStub->method('getStatusCode')->willReturn(200);

        $clientStub = $this->createStub(ClientInterface::class);
        $clientStub
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturn($this->postResponseStub, $this->userResponseStub)
        ;
        $this->provider->setHttpClient($clientStub);
    }

    protected function tearDown(): void
    {
        $this->provider = null;
        $this->postResponseStub = null;
        $this->userResponseStub = null;
    }

    public function testUserData(): void
    {
        $this->postResponseStub
            ->method('getBody')
            ->willReturn(
                '{"access_token":"mock_access_token","expires_in":3600,"token_type":"Bearer","scope":"user:id ' .
                'user:username user:email user:name user:surname user:birthday user:activeAddresses user:addresses ' .
                'user:subscription","refresh_token":"mock_refresh_token"}'
            )
        ;

        $userData = [
            'id' => rand(1000, 9999),
            'username' => uniqid(),
            'email' => uniqid(),
            'name' => uniqid(),
            'surname' => uniqid(),
            'birthday' => (new DateTime())->getTimestamp(),
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
        $this->userResponseStub->method('getBody')->willReturn(json_encode($userData));

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var OpenIDResourceOwner $user */
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
        $this->assertSame(
            $userData['addresses'][$userData['active_address']],
            $user->toArray()['addresses'][$userData['active_address']]
        );

        // Test address list
        $this->assertSame($userData['addresses'], $user->getAddresses());
        $this->assertSame($userData['addresses'], $user->toArray()['addresses']);

        // Test subscription type
        $this->assertSame($userData['subscription_type'], $user->getSubscription());
        $this->assertSame($userData['subscription_type'], $user->toArray()['subscription_type']);
    }

    public function testNoActiveAddress(): void
    {
        $this->postResponseStub
            ->method('getBody')
            ->willReturn(
                '{"access_token":"mock_access_token",' .
                '"expires_in":3600,"token_type":"Bearer","scope":"user:id user:activeAddresses user:addresses",' .
                '"refresh_token":"mock_refresh_token"}'
            )
        ;

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
        $this->userResponseStub->method('getBody')->willReturn(json_encode($userData));

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var OpenIDResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Check with default
        $this->assertNull($user->getActiveAddress());
        $this->assertNull($user->toArray()['active_address']);
    }

    public function testAddressOn0Addresses(): void
    {
        $this->postResponseStub
            ->method('getBody')
            ->willReturn(
                '{"access_token":"mock_access_token",' .
                '"expires_in":3600,"token_type":"Bearer","scope":"user:id user:activeAddresses user:addresses",' .
                '"refresh_token":"mock_refresh_token"}'
            )
        ;

        $userData = [
            'id' => rand(1000, 9999),
            'active_address' => null,
            'addresses' => null,
        ];
        $this->userResponseStub->method('getBody')->willReturn(json_encode($userData));

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var OpenIDResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Test addresses directly
        $this->assertNull($user->getAddresses());
        $this->assertNull($user->toArray()['addresses']);

        // Check with default
        $this->assertNull($user->getActiveAddress());
        $this->assertNull($user->toArray()['active_address']);
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

        $this->postResponseStub
            ->method('getBody')
            ->willReturn(
                '{"access_token":"mock_access_token",' .
                '"expires_in":3600,"token_type":"Bearer","scope":"user:id user:activeAddresses user:addresses",' .
                '"refresh_token":"mock_refresh_token"}'
            )
        ;

        $this->userResponseStub->method('getBody')->willReturn(json_encode($userData));

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        /** @var OpenIDResourceOwner $user */
        $user = $this->provider->getResourceOwner($token);

        // Test default address on multiple
        $this->assertSame($userData['addresses']['2'], $user->getActiveAddress());
        $this->assertSame(
            $userData['addresses']['2'],
            $user->toArray()['addresses'][$user->toArray()['active_address']]
        );
    }
}
