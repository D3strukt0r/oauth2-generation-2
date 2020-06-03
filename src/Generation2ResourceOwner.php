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

use DateTime;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * The Class which can be used with league/oauth2-client.
 */
class Generation2ResourceOwner implements ResourceOwnerInterface
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
     * @param array $response Data received from the server about the user
     */
    public function __construct(array $response = [])
    {
        $this->response = $response;
    }

    /**
     * Returns the identifier of the authorized resource owner.
     *
     * @return int|null
     */
    public function getId()
    {
        return (int) $this->response['id'] ?: null;
    }

    /**
     * Return the username.
     *
     * @return string|null
     */
    public function getUsername()
    {
        return $this->response['username'] ?: null;
    }

    /**
     * Return the email.
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->response['email'] ?: null;
    }

    /**
     * Return the first name.
     *
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->response['name'] ?: null;
    }

    /**
     * Return the surname.
     *
     * @return string|null
     */
    public function getSurname()
    {
        return $this->response['surname'] ?: null;
    }

    /**
     * Return the birthday.
     *
     * @return DateTime|null
     */
    public function getBirthday()
    {
        return $this->response['birthday'] ? (new DateTime())->setTimestamp($this->response['birthday']) : null;
    }

    /**
     * Return the surname.
     *
     * @return array|null
     */
    public function getActiveAddress()
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
     * Return the surname.
     *
     * @return array|null
     */
    public function getAddresses()
    {
        return $this->response['addresses'] ?: null;
    }

    /**
     * Return the subscription.
     *
     * @return string|null
     */
    public function getSubscription()
    {
        return $this->response['subscription_type'] ?: null;
    }

    /**
     * Return all of the owner details available as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }
}
