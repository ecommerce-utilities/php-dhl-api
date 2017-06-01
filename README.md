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
use EcommerceUtilities\DHL\DHLServices;

require 'vendor/autoload.php';

$cred = new DHLApiCredentials(<api:username>, <api:password>, <api:portalId>, <api:warehouseName>);
$services = new DHLServices($cred);
$response = $services->getRetoureService()->getRetourePdf('Max', 'Mustermann', 'Musterstr.', 123, 12345, 'Berlin', '123446-B');
echo $response->getTrackingNumber();
echo $response->getPdf();
```
