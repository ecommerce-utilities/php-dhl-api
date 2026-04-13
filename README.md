# DHL-API

Use at your own risk - parents are responsible for their children!

[![Latest Stable Version](https://poser.pugx.org/ecommerce-utilities/dhl-api/v/stable)](https://packagist.org/packages/ecommerce-utilities/dhl-api)
[![License](https://poser.pugx.org/ecommerce-utilities/dhl-api/license)](https://packagist.org/packages/ecommerce-utilities/dhl-api)

## Composer

`composer require ecommerce-utilities/dhl-api *`

## Example:

```PHP
<?php
use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\DHLServices;
use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;

require 'vendor/autoload.php';

$oauthCredentials = new DHLOAuthCredentials('<api key from developer.dhl.com>', '<api secret from developer.dhl.com>');
$businessPortalCredentials = new DHLBusinessPortalCredentials(
	true,
	'<username of www.dhl.de/de/geschaeftskunden>',
	'<password of www.dhl.de/de/geschaeftskunden>',
	'<receiver-id>'
);

$services = new DHLServices($oauthCredentials, $businessPortalCredentials, new RequestFactory(), new Client());
$response = $services->getRetoureService()->getRetourePdf(
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

use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\DHLServices;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLNamedPersonOnly;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRecipientAddressPostal;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRequest;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentSenderAddress;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingService;
use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;

require 'vendor/autoload.php';

$oauthCredentials = new DHLOAuthCredentials('<api key from developer.dhl.com>', '<api secret from developer.dhl.com>');
$businessPortalCredentials = new DHLBusinessPortalCredentials(
	false,
	'<username of the DHL business customer portal>',
	'<password of the DHL business customer portal>'
);

$services = new DHLServices($oauthCredentials, $businessPortalCredentials, new RequestFactory(), new Client());

$shippingService = new DHLShippingService(
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

$response = $services->getShipmentService()->createLabel($shippingService, $request);
file_put_contents('label.pdf', $response->getLabelData());
```

Supported outbound shipment features:

* Postal recipient addresses
* DHL Packstation addresses
* DHL Postfiliale addresses
* Cash on delivery (`DHLCashOnDeliveryService`)
* Named person only (`DHLNamedPersonOnly`)

`DHLOAuthCredentials` contains the developer portal API key/secret. `DHLBusinessPortalCredentials` contains the business customer portal login plus the environment flag and, for retoure use cases, the `receiverId`. The developer portal `appName` is not part of the OAuth token request.
