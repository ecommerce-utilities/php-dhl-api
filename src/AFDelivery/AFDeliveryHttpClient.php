<?php

namespace EcommerceUtilities\DHL\AFDelivery;

use EcommerceUtilities\DHL\Http\DHLHttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class AFDeliveryHttpClient extends DHLHttpClient {
	public function __construct(
		RequestFactoryInterface $requestFactory,
		ClientInterface $client
	) {
		parent::__construct(
			requestFactory: $requestFactory,
			client: $client,
			baseUri: 'https://afdelivery.postdirekt.de/afdelivery/'
		);
	}
}
