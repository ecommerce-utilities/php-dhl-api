# Upgrade Guide

This document describes how to migrate client projects to the current shipment and returns API of `ecommerce-utilities/dhl-api` `0.3.x`.

It covers three upgrade paths:

1. `0.1.3` to `0.2.0`
2. `0.2.0` to `0.3.0` including `0.2.1` and `0.2.2`
3. Direct migration from `dv-team/php-shippinglabel-de` to the current `0.3.x` API

## 1. `0.1.3` to `0.2.0`

### What changed

`0.2.0` introduced a new credential split and a new shipment-label REST flow.

Public API changes:

- `EcommerceUtilities\DHL\Common\DHLApiCredentials` was removed.
- `EcommerceUtilities\DHL\Common\DHLOAuthCredentials` was introduced for the developer portal API key and secret.
- `EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials` became the container for:
  - `isProductionEnv`
  - business portal username
  - business portal password
  - optional `receiverId`
- `EcommerceUtilities\DHL\DHLServices` now expected:
  - first argument: `DHLOAuthCredentials`
  - second argument: `DHLBusinessPortalCredentials`
- `DHLShipmentService` plus the shipment DTOs were added for REST shipment-label creation.
- Shipment configuration now required billing numbers in addition to the DHL product keys.

### Before and after

Before `0.2.0`:

```php
$developerCredentials = new DHLBusinessPortalCredentials('<api key>', '<api secret>');
$businessCredentials = new DHLApiCredentials(
    true,
    '<business portal username>',
    '<business portal password>',
    '<receiver-id>'
);

$services = new DHLServices($developerCredentials, $businessCredentials, $requestFactory, $client);
```

After `0.2.0`:

```php
$oauthCredentials = new DHLOAuthCredentials('<api key>', '<api secret>');
$businessPortalCredentials = new DHLBusinessPortalCredentials(
    true,
    '<business portal username>',
    '<business portal password>',
    '<receiver-id>'
);

$services = new DHLServices($oauthCredentials, $businessPortalCredentials, $requestFactory, $client);
```

### Mechanical migration steps

Apply these changes mechanically across the consumer project:

1. Replace imports:
   - `EcommerceUtilities\DHL\Common\DHLApiCredentials` -> `EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials`
   - old `EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials` usages for API key and secret -> `EcommerceUtilities\DHL\Common\DHLOAuthCredentials`
2. Replace constructor calls:
   - `new DHLBusinessPortalCredentials($apiKey, $apiSecret)` -> `new DHLOAuthCredentials($apiKey, $apiSecret)`
   - `new DHLApiCredentials($isProductionEnv, $username, $password, $receiverId)` -> `new DHLBusinessPortalCredentials($isProductionEnv, $username, $password, $receiverId)`
3. Update `DHLServices` construction so the first argument is `DHLOAuthCredentials` and the second argument is `DHLBusinessPortalCredentials`.
4. Move configuration values into the correct object:
   - developer portal API key and secret belong in `DHLOAuthCredentials`
   - business portal username and password belong in `DHLBusinessPortalCredentials`
   - `isProductionEnv` and `receiverId` belong in `DHLBusinessPortalCredentials`
5. If the consumer also starts using the new shipment-label flow, add:
   - `DHLShipmentRequest`
   - `DHLShipmentSenderAddress`
   - the recipient address DTOs
   - `DHLShipmentServiceResponse`
   - billing numbers for national and international shipment products

### Validation after upgrading to `0.2.0`

1. Run a token probe against `DHLOAuthTokenProvider`.
2. Verify that intentionally wrong API key and secret produce `Invalid client identifier`.
3. Verify that intentionally wrong business portal credentials produce `Invalid user credentials`.
4. Verify that `isProductionEnv = true` targets `https://api-eu.dhl.com`.
5. Verify that `isProductionEnv = false` targets `https://api-sandbox.dhl.com`.

## 2. `0.2.0` to `0.3.0`

This section includes all public changes from `0.2.1`, `0.2.2`, and `0.3.0`.

### What changed in `0.2.1`

- `EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingService` was renamed to `EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingServiceConfiguration`.

Mechanical change:

- Replace the import and constructor name.
- The constructor arguments stayed the same.

### What changed in `0.2.2`

- `DHLShipmentService` no longer serializes an empty shipment reference as `refNo`.

Impact:

- Usually no code change is required.
- Only update the consumer if it explicitly depended on sending an empty `refNo` field to DHL.

### What changed in `0.3.0`

`0.3.0` removes the facade-style setup and moves everything to direct constructor injection.

Public API changes:

- `EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials` was removed.
- `EcommerceUtilities\DHL\DHLServices` was removed.
- `EcommerceUtilities\DHL\Http\HttpClient` was renamed to `EcommerceUtilities\DHL\Http\DHLHttpClient`.
- `DHLOAuthCredentials` now contains all credential and environment fields:
  - business portal username
  - business portal password
  - developer portal API key
  - developer portal API secret
  - `isProductionEnv`
  - optional `receiverId`
- `DHLOAuthTokenProvider` now accepts only:
  - `DHLOAuthCredentials`
  - `DHLHttpClient`
- `DHLShipmentService` and `DHLRetoureService` must now be instantiated directly.
- Returns handling was updated to the current DHL returns response shape. Client code should use `getTrackingNumber()` and `getLabelData()` instead of relying on raw legacy keys like `shipmentNumber` or `labelData`.
- Error handling now surfaces DHL response detail messages more directly.

Compatibility note:

- `0.3.0` still aliases the old `HttpClient` class name via `src/shims.php`.
- New and migrated code should still switch to `DHLHttpClient`.

### Before and after for shipment labels

Before `0.3.0`:

```php
$oauthCredentials = new DHLOAuthCredentials('<api key>', '<api secret>');
$businessPortalCredentials = new DHLBusinessPortalCredentials(
    false,
    '<business portal username>',
    '<business portal password>'
);

$services = new DHLServices($oauthCredentials, $businessPortalCredentials, new RequestFactory(), new Client());
$shipmentService = $services->getShipmentService();

$shippingService = new DHLShippingService(
    myCountryId: 'DE',
    productKeyNational: 'V01PAK',
    productKeyInternational: 'V53WPAK',
    billingNumberNational: '<billing number national>',
    billingNumberInternational: '<billing number international>'
);
```

After `0.3.0`:

```php
$credentials = new DHLOAuthCredentials(
    businessPortalUsername: '<business portal username or sandbox user>',
    businessPortalPassword: '<business portal password or sandbox password>',
    key: '<api key>',
    secret: '<api secret>',
    isProductionEnv: false,
);

$httpClient = new DHLHttpClient(new RequestFactory(), new Client(), false);
$tokenProvider = new DHLOAuthTokenProvider($credentials, $httpClient);
$shipmentService = new DHLShipmentService($tokenProvider, $httpClient);

$shippingService = new DHLShippingServiceConfiguration(
    myCountryId: 'DE',
    productKeyNational: 'V01PAK',
    productKeyInternational: 'V53WPAK',
    billingNumberNational: '<billing number national>',
    billingNumberInternational: '<billing number international>'
);
```

### Before and after for return labels

Before `0.3.0`:

```php
$oauthCredentials = new DHLOAuthCredentials('<api key>', '<api secret>');
$businessPortalCredentials = new DHLBusinessPortalCredentials(
    false,
    '<business portal username>',
    '<business portal password>',
    'deu'
);

$services = new DHLServices($oauthCredentials, $businessPortalCredentials, new RequestFactory(), new Client());
$retoureService = $services->getRetoureService();
```

After `0.3.0`:

```php
$credentials = new DHLOAuthCredentials(
    businessPortalUsername: '<business portal username or sandbox user>',
    businessPortalPassword: '<business portal password or sandbox password>',
    key: '<api key>',
    secret: '<api secret>',
    isProductionEnv: false,
    receiverId: 'deu'
);

$httpClient = new DHLHttpClient(new RequestFactory(), new Client(), false);
$tokenProvider = new DHLOAuthTokenProvider($credentials, $httpClient);
$retoureService = new DHLRetoureService($tokenProvider, $credentials, $httpClient);
```

### Search-and-replace checklist for `0.2.0` consumers

Search for these patterns and update them:

- `use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;`
- `new DHLBusinessPortalCredentials(`
- `new DHLOAuthCredentials(` where only API key and secret are passed
- `use EcommerceUtilities\DHL\DHLServices;`
- `new DHLServices(`
- `use EcommerceUtilities\DHL\Http\HttpClient;`
- `new HttpClient(`
- `use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingService;`
- `new DHLShippingService(`

The safe migration rule for `0.3.0` is:

- put all credential, environment, and optional return receiver values into `DHLOAuthCredentials`
- create one `DHLHttpClient`
- create one `DHLOAuthTokenProvider`
- inject those dependencies into `DHLShipmentService` or `DHLRetoureService`

### Validation after upgrading to `0.3.0`

1. Verify there are no remaining imports of `DHLBusinessPortalCredentials` or `DHLServices`.
2. Verify shipment configuration uses `DHLShippingServiceConfiguration`.
3. Verify `new DHLHttpClient(..., true)` targets `https://api-eu.dhl.com`.
4. Verify `new DHLHttpClient(..., false)` targets `https://api-sandbox.dhl.com`.
5. Verify returns in sandbox use `receiverId = 'deu'`.
6. Verify generated labels are still consumed through `getLabelData()` and tracking numbers through `getTrackingNumber()`.

## 3. Migrate from `dv-team/php-shippinglabel-de` to current `0.3.x`

Projects still using `dv-team/php-shippinglabel-de` should migrate directly to the current constructor-injected DHL REST API.

### Remove old `dv-team/php-shippinglabel-de` infrastructure

Delete these concepts from the integration layer:

- `ShippingLabelEndpoint`
- `ShippingLabelAuthTokenService`
- `ShippingLabelAPIService`
- `ShippingLabelResponse`
- the old `api.shippinglabel.de` OAuth client id and client secret

These objects are specific to the old `api.shippinglabel.de` integration and are not used by the current DHL REST implementation.

### Class mapping

| Old `dv-team/php-shippinglabel-de` | Current `php-dhl-api` |
| --- | --- |
| `DvTeam\ShippingLabel\DHLShippingService` | `EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingServiceConfiguration` |
| `ShippingLabelSenderAddress` | `DHLShipmentSenderAddress` |
| `ShippingLabelRequest` | `DHLShipmentRequest` |
| `ShippingLabelRecipientAddressPostal` | `DHLShipmentRecipientAddressPostal` |
| `ShippingLabelRecipientAddressDHLPackstation` | `DHLShipmentRecipientAddressPackstation` |
| `ShippingLabelRecipientAddressDHLPostfiliale` | `DHLShipmentRecipientAddressPostfiliale` |
| `DHLCashOnDeliveryService` | `EcommerceUtilities\DHL\Services\DHLShipmentService\DHLCashOnDeliveryService` |
| `DHLNamedPersonOnly` | `EcommerceUtilities\DHL\Services\DHLShipmentService\DHLNamedPersonOnly` |

### Credential and service construction

Old setup:

```php
$endpoint = new ShippingLabelEndpoint();
$authTokenService = new ShippingLabelAuthTokenService(
    clientId: '<client-id>',
    clientSecret: '<client-secret>',
    endpoint: $endpoint
);
$apiService = new ShippingLabelAPIService(
    endpoint: $endpoint,
    authTokenService: $authTokenService
);
```

Current setup:

```php
$credentials = new DHLOAuthCredentials(
    businessPortalUsername: '<business portal username>',
    businessPortalPassword: '<business portal password>',
    key: '<developer api key>',
    secret: '<developer api secret>',
    isProductionEnv: true,
);

$httpClient = new DHLHttpClient(new RequestFactory(), new Client(), true);
$tokenProvider = new DHLOAuthTokenProvider($credentials, $httpClient);
$shipmentService = new DHLShipmentService($tokenProvider, $httpClient);
```

### Shipping service migration

Old:

```php
$service = new \DvTeam\ShippingLabel\DHLShippingService(
    myCountryId: 'DE',
    productKeyNational: 'V01PAK',
    productKeyInternational: 'V53WPAK'
);
```

Current:

```php
$service = new \EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingServiceConfiguration(
    myCountryId: 'DE',
    productKeyNational: 'V01PAK',
    productKeyInternational: 'V53WPAK',
    billingNumberNational: '<billing number national>',
    billingNumberInternational: '<billing number international>'
);
```

Important difference:

- current shipment-label creation requires billing numbers
- the old `dv-team/php-shippinglabel-de` integration selected only the product code
- the current configuration object also supports `profile`, `printFormat`, `documentFormat`, `acceptLanguage`, and `mustEncode`

### Request migration

Old:

```php
$request = new ShippingLabelRequest(
    reference: 'ABC123',
    senderAddress: $senderAddress,
    recipientAddress: $recipientAddress,
    email: null,
    phone: null,
    weight: 1.0,
    services: [],
);
```

Current:

```php
$request = new DHLShipmentRequest(
    reference: 'ABC123',
    senderAddress: $senderAddress,
    recipientAddress: $recipientAddress,
    email: null,
    phone: null,
    weight: 1.0,
    services: [],
);
```

### Response migration

Old:

```php
$response = $apiService->createLabel(shippingService: $service, request: $request);
file_put_contents('label.pdf', $response->getBinaryString());
$trackingNumber = $response->shippingCode;
```

Current:

```php
$response = $shipmentService->createLabel($service, $request);
file_put_contents('label.pdf', $response->getLabelData());
$trackingNumber = $response->getTrackingNumber();
```

Additional capabilities in the current response:

- `getCodLabelData()`
- `getRoutingCode()`
- `getData()`

### Behavior mapping for old extra services

If the old integration used only these extra services:

- `Nachnahme`
- `Zustellung nur an die genannte Person`

map them directly:

```php
new DHLCashOnDeliveryService(
    amount: 19.95,
    accountOwner: '...',
    bankIban: '...',
    bankBic: '...',
    bankName: '...',
    reference: '...',
    reference2: '...'
)

new DHLNamedPersonOnly()
```

No automatic migration should be attempted for any service that is not already implemented in `src/Services/DHLShipmentService.php`.

### Agent procedure for migrating a `dv-team/php-shippinglabel-de` consumer

An AI agent should apply the migration in this order:

1. Remove imports from `DvTeam\ShippingLabel\...`
2. Add imports from `EcommerceUtilities\DHL\Common\...`, `EcommerceUtilities\DHL\Http\...`, and `EcommerceUtilities\DHL\Services\DHLShipmentService\...`
3. Replace the old endpoint and auth infrastructure with:
   - `DHLOAuthCredentials`
   - `DHLHttpClient`
   - `DHLOAuthTokenProvider`
   - `DHLShipmentService`
4. Replace the old service configuration with `DHLShippingServiceConfiguration`
5. Replace address and request DTOs with the current shipment DTOs
6. Add national and international billing numbers
7. Replace response handling:
   - `getBinaryString()` -> `getLabelData()`
   - `shippingCode` -> `getTrackingNumber()`
8. Replace old exception handling that was specific to `ShippingLabelException` or `ShippingAddressValidationException` with handling for `EcommerceUtilities\DHL\Common\DHLApiException`
9. Run a token test first, then a real shipment-label test

## Final validation checklist

After any migration path, verify all of the following:

- no remaining imports of `DHLApiCredentials`
- no remaining imports of `DHLBusinessPortalCredentials`
- no remaining imports of `DHLServices`
- no remaining imports of legacy `HttpClient` in consumer code
- no remaining imports from `DvTeam\ShippingLabel\...`
- all credentials and environment fields are passed through `DHLOAuthCredentials`
- shipment integrations use `DHLShippingServiceConfiguration`
- shipment and returns integrations construct `DHLHttpClient` and `DHLOAuthTokenProvider` directly
- billing numbers are present for shipment-label creation
- generated labels are written via `getLabelData()`
- tracking numbers are read via `getTrackingNumber()`
- sandbox returns use `receiverId = 'deu'`
