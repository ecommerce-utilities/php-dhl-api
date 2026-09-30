# DHL-API

Use at your own risk - parents are responsible for their children!

[![Latest Stable Version](https://poser.pugx.org/ecommerce-utilities/dhl-api/v/stable)](https://packagist.org/packages/ecommerce-utilities/dhl-api)
[![License](https://poser.pugx.org/ecommerce-utilities/dhl-api/license)](https://packagist.org/packages/ecommerce-utilities/dhl-api)

## Composer

`composer require ecommerce-utilities/dhl-api *`

## Shipment Tracking Unified Push

The push API uses the API key from the DHL Developer Portal. It does not use the
OAuth token for the DHL business customer portal.

```PHP
use EcommerceUtilities\DHL\Services\DHLPushSubscriptionService;
use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;

$pushService = new DHLPushSubscriptionService(
	'<api key from developer.dhl.com>',
	new RequestFactory(),
	new Client(),
);

$pushService->createShipmentSubscription(
	'https://shop.example/dhl-tracking-push/<webhook secret>',
	'parcel-de',
	['<tracking number 1>', '<tracking number 2>'],
);
```

The validation message sent to the webhook contains the subscription ID and its
hook secret. Use both to activate the subscription:

```PHP
$pushService->activateSubscription('<subscription id>', '<DHL hook secret>');
```

### Batch subscriptions and daily quota

DHL documents **1 to 10 tracking numbers per subscription request** in
`SubscriptionShipmentIds.shipmentIDs` of its
[Unified Push OpenAPI specification v1.2.5](https://developer.dhl.com/sites/default/files/2026-08/push%20v1.2.5_12.yaml)
(checked 2026-09-26). The maximum is stated in the property's description, not as
a machine-readable `maxItems` constraint. The library exposes this limit as
`DHLPushSubscriptionService::MAX_SHIPMENT_IDS_PER_REQUEST`.

`createShipmentSubscription()` still sends exactly one request and returns one
subscription response. More than ten IDs, missing required input or invalid IDs
now raise `DHLRequestValidationException` (a `DHLApiException`) locally, before any
API call. For larger lists, iterate the new batch method:

```PHP
$trackingNumbers = [/* pending tracking numbers */];
foreach($pushService->createShipmentSubscriptions(
	'https://office.example/dhl-tracking-push/<webhook secret>',
	'parcel-de',
	$trackingNumbers,
) as $batch) {
	// Persist this response together with every shipment ID in the batch
	// before requesting the next batch. One subscription covers the whole batch.
	$shipmentIds = $batch['shipmentIds'];
	$response = $batch['response'];
	// $response['self'] identifies the subscription to activate via its webhook secret.
}
```

The generator sends requests only while it is iterated, with ten IDs per request
except for a smaller final batch. It validates the complete input before the
first request, preserves ID order and leading zeros, and makes no requests for
an empty list. Errors stop iteration without retrying; already yielded results
remain available to the caller. Persist each result inside the loop rather than
collecting all results with `iterator_to_array()`. An interrupted/failed HTTP
request may already have created a subscription: reconcile it before retrying
and never replay the complete list blindly.

Each batch still requires its own activation call. For `N` shipments, budget at
least `2 * ceil(N / 10)` outgoing API calls (creation plus activation). A quota of
500 calls/day therefore allows **at most 2,500 new shipments/day** with full
batches, before additional lookups, deletions or retries. At 8,000 shipments/day,
at least 1,600 calls are needed. Incoming webhook messages do not consume this
outgoing-call quota. See the [DHL access form](https://developer.dhl.com/form/access-request-shipment-tracking)
and [activation workflow](https://developer.dhl.com/api-reference/shipment-tracking-unified-push).

The batch method does not collect numbers across separate calls, enforce the
daily quota or activate subscriptions automatically. Consumers such as
ShopOffice must aggregate pending shipments, map all batch members to the shared
subscription ID, activate each subscription only once, and reserve quota for
activation and retries. For durable queues that must reserve a batch before the
HTTP request, use `array_chunk($trackingNumbers, DHLPushSubscriptionService::MAX_SHIPMENT_IDS_PER_REQUEST)`
and call `createShipmentSubscription()` for each reserved batch.

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
