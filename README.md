# DHL-API

Use at your own risk - parents are responsible for their children!

[![Latest Stable Version](https://poser.pugx.org/ecommerce-utilities/dhl-api/v/stable)](https://packagist.org/packages/ecommerce-utilities/dhl-api)
[![License](https://poser.pugx.org/ecommerce-utilities/dhl-api/license)](https://packagist.org/packages/ecommerce-utilities/dhl-api)

## Composer

`composer require ecommerce-utilities/dhl-api *`

## Example:

```PHP
<?php
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthTokenProvider;
use EcommerceUtilities\DHL\Http\DHLHttpClient;
use EcommerceUtilities\DHL\Services\DHLRetoureService;
use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;

require 'vendor/autoload.php';

$isProductionEnv = false;

$credentials = new DHLOAuthCredentials(
	businessPortalUsername: '<username of the DHL business customer portal or sandbox user>',
	businessPortalPassword: '<password of the DHL business customer portal or sandbox password>',
	key: '<api key from developer.dhl.com>',
	secret: '<api secret from developer.dhl.com>',
	isProductionEnv: $isProductionEnv,
	receiverId: $isProductionEnv ? '<production receiver-id>' : 'deu'
);

$httpClient = new DHLHttpClient(new RequestFactory(), new Client(), $isProductionEnv);
$tokenProvider = new DHLOAuthTokenProvider($credentials, $httpClient);
$retoureService = new DHLRetoureService($tokenProvider, $credentials, $httpClient);

$response = $retoureService->getRetourePdf(
	'Max',         // $name1
	'Mustermann',  // $name2
	null,          // $name3
	'Musterstr.',  // $street
	123,           // $streetNumber
	72770,         // $zip
	'Reutlingen',  // $city
	'DE',          // $countryId
	'123446-B',    // $voucherNr
	null           // $shipmentReference
);

printf("%s\n", $response->getTrackingNumber());
file_put_contents('label.pdf', $response->getLabelData());
```

## Shipment labels via REST API

```PHP
<?php

use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthTokenProvider;
use EcommerceUtilities\DHL\Http\DHLHttpClient;
use EcommerceUtilities\DHL\Services\DHLShipmentService;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLNamedPersonOnly;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRecipientAddressPostal;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRequest;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentSenderAddress;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingServiceConfiguration;
use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;

require 'vendor/autoload.php';

$isProductionEnv = false;

$credentials = new DHLOAuthCredentials(
	businessPortalUsername: '<username of the DHL business customer portal or sandbox user>',
	businessPortalPassword: '<password of the DHL business customer portal or sandbox password>',
	key: '<api key from developer.dhl.com>',
	secret: '<api secret from developer.dhl.com>',
	isProductionEnv: $isProductionEnv
);

$httpClient = new DHLHttpClient(new RequestFactory(), new Client(), $isProductionEnv);
$tokenProvider = new DHLOAuthTokenProvider($credentials, $httpClient);
$shipmentService = new DHLShipmentService($tokenProvider, $httpClient);

$shippingService = new DHLShippingServiceConfiguration(
	myCountryId: 'DE',
	productKeyNational: 'V01PAK',
	productKeyInternational: 'V53WPAK',
	billingNumberNational: '<billing number for V01PAK>',
	billingNumberInternational: '<billing number for V53WPAK>',
);

$request = new DHLShipmentRequest(
	reference: 'ABC12345',
	senderAddress: new DHLShipmentSenderAddress(
		company: 'Meine Firma',
		street: 'Musterstr.',
		houseNumber: '123',
		zip: '12345',
		city: 'Berlin',
		countryCode: 'DE',
		mail: 'shipment@example.org',
	),
	recipientAddress: new DHLShipmentRecipientAddressPostal(
		company: 'Musterfirma',
		firstname: 'Max',
		lastname: 'Mustermann',
		street: 'Musterstraße',
		houseNumber: '1',
		addressAddition: null,
		zip: '10115',
		city: 'Berlin',
		state: null,
		countryCode: 'DE',
	),
	email: 'max.mustermann@example.com',
	phone: null,
	weight: 1.0,
	services: [
		new DHLNamedPersonOnly(),
	],
);

$response = $shipmentService->createLabel($shippingService, $request);
file_put_contents('label.pdf', $response->getLabelData());
```

Supported outbound shipment features:

* Postal recipient addresses
* DHL Packstation addresses
* DHL Postfiliale addresses
* Cash on delivery (`DHLCashOnDeliveryService`)
* Named person only (`DHLNamedPersonOnly`)

`DHLOAuthCredentials` now contains the business customer portal login, the developer portal API key/secret, the environment flag, and optionally the `receiverId` for returns use cases. The current setup flow is:

1. Create `DHLOAuthCredentials`
2. Create `DHLHttpClient`
3. Create `DHLOAuthTokenProvider`
4. Inject those dependencies into `DHLRetoureService` or `DHLShipmentService`

For DHL Returns in sandbox, use the sandbox user plus `receiverId = 'deu'`. The developer portal `appName` is not part of the OAuth token request.
