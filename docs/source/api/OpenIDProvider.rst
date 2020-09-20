**\\Generation2\\OAuth2\\Client\\Provider**

==============
OpenIDProvider
==============

.. php:namespace:: D3strukt0r\OAuth2\Client\Provider
.. php:class:: OpenIDProvider

    Internal use for translations.

    .. php:const:: ACCESS_TOKEN_RESOURCE_OWNER_ID

    .. php:attr:: protected $host

        string — Default host.

    .. php:method:: public getHost() -> string

        Gets host.

        :returns: string — xxx

    .. php:method:: public setHost($host) -> $this

        Sets host. Can be used for example when you testing the service-account in localhost.

        :param string $host: (Required) The domain for accessing the user data

        :returns: $this — xxx

    .. php:method:: public getBaseAuthorizationUrl() -> string

        Returns the base URL for authorizing a client.

        Eg. https://oauth.service.com/authorize

        :returns: string — xxx

    .. php:method:: public getBaseAccessTokenUrl($params) -> string

        Returns the base URL for requesting an access token.

        Eg. https://oauth.service.com/token

        :param array $params: (Required) Special parameters

        :returns: string — xxx

    .. php:method:: public getResourceOwnerDetailsUrl($token) -> string

        Returns the URL for requesting the resource owner's details.

        :param League\\OAuth2\\Client\\Token\\AccessToken $token: (Required) The received access token from the server

        :returns: string — xxx

    .. php:method:: protected getDefaultScopes() -> array

        Returns the default scopes used by this provider.

        This should only be the scopes that are required to request the details of the resource owner, rather than all
        the available scopes.

        :returns: array — xxx

    .. php:method:: protected getScopeSeparator() -> string

        Get the string used to separate scopes.

        :returns: string — xxx

    .. php:method:: protected checkResponse($response, $data) -> void

        Checks a provider response for errors.

        :param Psr\\Http\\Message\\ResponseInterface $response: (Required) The response from the server
        :param array|string $data: (Required) Parsed response data

        :throws: :php:exc:`League\\OAuth2\\Client\\Provider\\Exception\\IdentityProviderException`

        :returns: void — xxx

    .. php:method:: protected createResourceOwner($response, $token) -> integer|null

        Generates a resource owner object from a successful resource owner details request.

        :param array $response: (Required) Response data from server
        :param League\\OAuth2\\Client\\Token\\AccessToken $token: (Required) The used access token

        :returns: :php:class:`League\\OAuth2\\Client\\Provider\\ResourceOwnerInterface` — xxx
