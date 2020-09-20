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
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * The Class which can be used with league/oauth2-client.
 */
class OpenIDResourceOwner implements ResourceOwnerInterface
{
    /**
     * Raw response.
     *
     * @var array
     */
    protected $response;

    /**
     * Creates new resource owner.
     *
     * @param array $response Data received from the server appendix the user
     */
    public function __construct(array $response = [])
    {
        $this->response = $response;
    }

    /**
     * Returns the identifier of the authorized resource owner.
     *
     * @return int|null returns the ID
     */
    public function getId(): ?int
    {
        return (int) $this->response['id'] ?: null;
    }

    /**
     * Returns the username.
     *
     * @return string|null returns the username
     */
    public function getUsername(): ?string
    {
        return $this->response['username'] ?: null;
    }

    /**
     * Returns the email.
     *
     * @return string|null returns the email
     */
    public function getEmail(): ?string
    {
        return $this->response['email'] ?: null;
    }

    /**
     * Returns the first name.
     *
     * @return string|null returns the first name
     */
    public function getFirstName(): ?string
    {
        return $this->response['name'] ?: null;
    }

    /**
     * Returns the surname.
     *
     * @return string|null returns the surname
     */
    public function getSurname(): ?string
    {
        return $this->response['surname'] ?: null;
    }

    /**
     * Returns the birthday.
     *
     * @return DateTime|null returns the birthday
     */
    public function getBirthday(): ?DateTime
    {
        return $this->response['birthday'] ? (new DateTime())->setTimestamp($this->response['birthday']) : null;
    }

    /**
     * Returns the currently active address.
     *
     * @return array|null return the currently active address
     */
    public function getActiveAddress(): ?array
    {
        if (null === $this->getAddresses()) {
            return null;
        }

        if (1 === count($this->getAddresses())) {
            foreach ($this->getAddresses() as $address) {
                return $address;
            }
        }

        if (null === $this->response['active_address']) {
            return null;
        }

        return $this->getAddresses()[$this->response['active_address']];
    }

    /**
     * Returns all the addresses.
     *
     * @return array|null return all the addresses
     */
    public function getAddresses(): ?array
    {
        return $this->response['addresses'] ?: null;
    }

    /**
     * Returns the subscription.
     *
     * @return string|null return the subscription
     */
    public function getSubscription(): ?string
    {
        return $this->response['subscription_type'] ?: null;
    }

    /**
     * Return all of the owner details available as an array.
     *
     * @return array return all the information as an array
     */
    public function toArray(): array
    {
        return $this->response;
    }
}
