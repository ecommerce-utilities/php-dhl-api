# Upgrade Guide

This document describes how an AI agent should migrate client projects to the current API of `ecommerce-utilities/dhl-api`.

It covers two scenarios:

1. Projects that already use this repository, but still use the old credential class schema from before commit `05eed6e`.
2. Projects that still use `dv-team/php-shippinglabel-de` and must be migrated to the DHL REST API implementation in this repository.

## 1. Upgrade projects that still use the old credential schema

### Old vs. new credential model

Before commit `05eed6e`, the public constructor model was effectively:

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

After the refactor, the public model is:

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

### Required code changes

Apply these changes mechanically across the consumer project:

1. Replace imports:
   - `EcommerceUtilities\DHL\Common\DHLApiCredentials` -> `EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials`
   - old `EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials` usages for API key/secret -> `EcommerceUtilities\DHL\Common\DHLOAuthCredentials`

2. Replace constructor calls:
   - Old OAuth credentials:
     ```php
     new DHLBusinessPortalCredentials($apiKey, $apiSecret)
     ```
     becomes
     ```php
     new DHLOAuthCredentials($apiKey, $apiSecret)
     ```
   - Old business portal credentials:
     ```php
     new DHLApiCredentials($isProductionEnv, $username, $password, $receiverId)
     ```
     becomes
     ```php
     new DHLBusinessPortalCredentials($isProductionEnv, $username, $password, $receiverId)
     ```

3. Update `DHLServices` construction:
   - First argument is now `DHLOAuthCredentials`
   - Second argument is now `DHLBusinessPortalCredentials`

4. Update any direct property or method access:
   - There is no `DHLApiCredentials` class anymore.
   - `DHLBusinessPortalCredentials` now exposes public readonly properties:
     - `isProductionEnv`
     - `username`
     - `password`
     - `receiverId`
   - `DHLOAuthCredentials` now exposes public readonly properties:
     - `key`
     - `secret`

5. Rename config variables in the consumer project:
   - Business portal login:
     - `*.business.username`
     - `*.business.password`
   - Developer portal API credentials:
     - `*.api.key`
     - `*.api.secret`
   - Do not use `appName` in the OAuth token request.

### Search-and-replace checklist for an agent

Search for these patterns and update them:

- `new DHLApiCredentials(`
- `use EcommerceUtilities\DHL\Common\DHLApiCredentials;`
- `new DHLBusinessPortalCredentials(` where the two values are developer portal key/secret
- `new DHLServices(` where the first two arguments still follow the old ordering semantics

The safe migration rule is:

- API key/secret always belong in `DHLOAuthCredentials`
- Business portal username/password always belong in `DHLBusinessPortalCredentials`
- `isProductionEnv` and `receiverId` always belong in `DHLBusinessPortalCredentials`

### Validation after migration

After applying the schema migration:

1. Run the project's token test or create a minimal token probe against `DHLOAuthTokenProvider`.
2. Verify that the OAuth request returns:
   - `Invalid client identifier` only when API key/secret are intentionally wrong
   - `Invalid user credentials` only when business portal credentials are intentionally wrong
3. Verify that the target environment is correct:
   - `isProductionEnv = true` uses `https://api-eu.dhl.com`
   - `isProductionEnv = false` uses `https://api-sandbox.dhl.com`

If the credentials work in production but not in sandbox, keep `isProductionEnv = true` for live-capable accounts.

## 2. Migrate projects from `dv-team/php-shippinglabel-de`

Projects still using `dv-team/php-shippinglabel-de` must stop calling `ShippingLabelAPIService` and instead create shipment labels through `DHLServices::getShipmentService()`.

### Remove old `dv-team/php-shippinglabel-de` infrastructure

Delete these concepts from the integration layer:

- `ShippingLabelEndpoint`
- `ShippingLabelAuthTokenService`
- `ShippingLabelAPIService`
- `ShippingLabelResponse`
- the shippinglabel.de OAuth client id/client secret

These are specific to the old `api.shippinglabel.de` integration and are not used by the DHL REST implementation.

### Class mapping

Map old classes to the new DHL shipment classes as follows:

| Old `dv-team/php-shippinglabel-de` | New `php-dhl-api` |
| --- | --- |
| `DvTeam\ShippingLabel\DHLShippingService` | `EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingService` |
| `ShippingLabelSenderAddress` | `DHLShipmentSenderAddress` |
| `ShippingLabelRequest` | `DHLShipmentRequest` |
| `ShippingLabelRecipientAddressPostal` | `DHLShipmentRecipientAddressPostal` |
| `ShippingLabelRecipientAddressDHLPackstation` | `DHLShipmentRecipientAddressPackstation` |
| `ShippingLabelRecipientAddressDHLPostfiliale` | `DHLShipmentRecipientAddressPostfiliale` |
| `DHLCashOnDeliveryService` | `EcommerceUtilities\DHL\Services\DHLShipmentService\DHLCashOnDeliveryService` |
| `DHLNamedPersonOnly` | `EcommerceUtilities\DHL\Services\DHLShipmentService\DHLNamedPersonOnly` |

### Credential migration

Old `dv-team/php-shippinglabel-de` used:

```php
$endpoint = new ShippingLabelEndpoint();
$authTokenService = new ShippingLabelAuthTokenService(clientId: '<client-id>', clientSecret: '<client-secret>', endpoint: $endpoint);
$apiService = new ShippingLabelAPIService(endpoint: $endpoint, authTokenService: $authTokenService);
```

Replace that with:

```php
$oauthCredentials = new DHLOAuthCredentials('<developer api key>', '<developer api secret>');
$businessPortalCredentials = new DHLBusinessPortalCredentials(
    true,
    '<business portal username>',
    '<business portal password>'
);

$services = new DHLServices($oauthCredentials, $businessPortalCredentials, new RequestFactory(), new Client());
$shipmentService = $services->getShipmentService();
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

New:

```php
$service = new \EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingService(
    myCountryId: 'DE',
    productKeyNational: 'V01PAK',
    productKeyInternational: 'V53WPAK',
    billingNumberNational: '<billing number for V01PAK>',
    billingNumberInternational: '<billing number for V53WPAK>'
);
```

Important difference:

- The new DHL shipment service requires billing numbers.
- Old `dv-team/php-shippinglabel-de` only selected the product code.
- The new service can also be configured with `profile`, `printFormat`, `documentFormat`, `acceptLanguage`, and `mustEncode`.

### Request migration

Old request construction:

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

New request construction is structurally similar:

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

The migration is mostly namespace and class replacement, plus the switch from the old service infrastructure to `DHLServices`.

### Response migration

Old:

```php
$response = $apiService->createLabel(shippingService: $service, request: $request);
file_put_contents('label.pdf', $response->getBinaryString());
$trackingNumber = $response->shippingCode;
```

New:

```php
$response = $shipmentService->createLabel($service, $request);
file_put_contents('label.pdf', $response->getLabelData());
$trackingNumber = $response->getTrackingNumber();
```

Additional capabilities in the new response:

- `getCodLabelData()` for COD documents
- `getRoutingCode()`
- `getData()` with the raw DHL response item

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

### Address migration notes

The new shipment service supports the same three recipient categories that were relevant in `dv-team/php-shippinglabel-de`:

- normal postal address
- Packstation
- Postfiliale

The new service normalizes country codes to the DHL-required alpha-3 format internally. Existing alpha-2 country codes such as `DE`, `AT`, `NL`, `GB` can remain unchanged in most client projects.

### Agent procedure for migrating a `dv-team/php-shippinglabel-de` consumer

An AI agent should apply the migration in this order:

1. Remove imports from `DvTeam\ShippingLabel\...`
2. Add imports from `EcommerceUtilities\DHL\Common\...` and `EcommerceUtilities\DHL\Services\DHLShipmentService\...`
3. Replace the old endpoint/auth/api service setup with:
   - `DHLOAuthCredentials`
   - `DHLBusinessPortalCredentials`
   - `DHLServices`
   - `$services->getShipmentService()`
4. Replace address DTOs and request DTOs with the new shipment DTOs
5. Extend `DHLShippingService` construction with national and international billing numbers
6. Replace response handling:
   - `getBinaryString()` -> `getLabelData()`
   - `shippingCode` -> `getTrackingNumber()`
7. Replace old exception handling that was specific to `ShippingLabelException` or `ShippingAddressValidationException` with handling for `EcommerceUtilities\DHL\Common\DHLApiException`
8. Run a real token test first, then a shipment label test

### Minimal before/after example

Old `dv-team/php-shippinglabel-de` style:

```php
$service = new DHLShippingService('DE', 'V01PAK', 'V53WPAK');
$endpoint = new ShippingLabelEndpoint();
$authTokenService = new ShippingLabelAuthTokenService('<client-id>', '<client-secret>', $endpoint);
$apiService = new ShippingLabelAPIService($endpoint, $authTokenService);
```

New DHL REST style:

```php
$oauthCredentials = new DHLOAuthCredentials('<api key>', '<api secret>');
$businessPortalCredentials = new DHLBusinessPortalCredentials(true, '<username>', '<password>');
$services = new DHLServices($oauthCredentials, $businessPortalCredentials, new RequestFactory(), new Client());
$shipmentService = $services->getShipmentService();

$service = new DHLShippingService(
    myCountryId: 'DE',
    productKeyNational: 'V01PAK',
    productKeyInternational: 'V53WPAK',
    billingNumberNational: '<billing number national>',
    billingNumberInternational: '<billing number international>'
);
```

## Final validation checklist

After either migration path, verify all of the following:

- No remaining imports of `DHLApiCredentials`
- No remaining imports from `DvTeam\ShippingLabel\...`
- All developer portal credentials use `DHLOAuthCredentials`
- All business portal credentials use `DHLBusinessPortalCredentials`
- Shipment integrations call `DHLServices::getShipmentService()`
- Billing numbers are present for shipment label creation
- Generated labels are written via `getLabelData()`
- Tracking numbers are read via `getTrackingNumber()`
