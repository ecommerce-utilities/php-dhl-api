# DHL-API

Use at your own risk - parents are responsible for their children!

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/067ce5ef-2d7f-47ac-b5f9-e75e68007b52/mini.png)](https://insight.sensiolabs.com/projects/067ce5ef-2d7f-47ac-b5f9-e75e68007b52)
[![Latest Stable Version](https://poser.pugx.org/ecommerce-utilities/dhl-api/v/stable)](https://packagist.org/packages/ecommerce-utilities/dhl-api)
[![License](https://poser.pugx.org/ecommerce-utilities/dhl-api/license)](https://packagist.org/packages/ecommerce-utilities/dhl-api)

## Composer

`composer require ecommerce-utilities/dhl-api *`

## Example:

```PHP
<?php
use EcommerceUtilities\DHL\Common\DHLApiCredentials;
use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\DHLServices;
use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Message\StreamFactory\GuzzleStreamFactory;

require 'vendor/autoload.php';

$businessCred = new DHLBusinessPortalCredentials('<appId from entwickler.dhl.de>', '<Token from entwickler.dhl.de>');
$cred = new DHLApiCredentials(true, '<username of www.dhl.de/de/geschaeftskunden>', '<password of www.dhl.de/de/geschaeftskunden>', '<receiver-id>');

$services = new DHLServices($businessCred, $cred, new RequestFactory(), new GuzzleStreamFactory(), new Client());
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
