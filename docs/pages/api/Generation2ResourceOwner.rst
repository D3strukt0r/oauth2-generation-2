**\\Generation2\\OAuth2\\Client\\Provider**

========================
Generation2ResourceOwner
========================

.. php:namespace:: D3strukt0r\OAuth2\Client\Provider
.. php:class:: Generation2ResourceOwner

    The class ServerConnection is used to create a connection to a server.

    .. php:attr:: protected $response

        array — Raw response.

    .. php:method:: public __construct([$response])

        Creates new resource owner.

        :param array $response: (Optional) Data received from the server about the user

    .. php:method:: public getId() -> integer|null

        Returns the identifier of the authorized resource owner.

        :returns: integer|null — xxx

    .. php:method:: public getUsername() -> string|null

        Return the username.

        :returns: string|null — xxx

    .. php:method:: public getEmail() -> string|null

        Return the email.

        :returns: string|null — xxx

    .. php:method:: public getFirstName() -> string|null

        Return the first name.

        :returns: string|null — xxx

    .. php:method:: public getSurname() -> string|null

        Return the surname.

        :returns: string|null — xxx

    .. php:method:: public getBirthday() -> DateTime|null

        Return the birthday.

        :returns: DateTime|null — xxx

    .. php:method:: public getActiveAddress() -> array|null

        Returns the active address.

        :returns: array|null — xxx

    .. php:method:: public getAddresses() -> array|null

        Returns the addresses.

        :returns: array|null — xxx

    .. php:method:: public getSubscription() -> string|null

        Return the subscription.

        :returns: string|null — xxx

    .. php:method:: public toArray() -> array

        Return all of the owner details available as an array.

        :returns: array — xxx

