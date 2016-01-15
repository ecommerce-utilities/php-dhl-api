# DHL-API

Use at your own risk - parents are responsible for their children!

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
