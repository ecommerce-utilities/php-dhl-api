<?php
namespace EcommerceUtilities\DHL;

use EcommerceUtilities\DHL\Common\DHLApiCredentials;
use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\Http\HttpClient;
use EcommerceUtilities\DHL\Services\DHLParcelStatusService;
use EcommerceUtilities\DHL\Services\DHLRetoureService;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class DHLServices {
	public function __construct(
		private readonly DHLBusinessPortalCredentials $businessPortalCredentials,
		private readonly DHLApiCredentials $credentials,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly ClientInterface $client
	) {}

	/**
	 * @return DHLRetoureService
	 */
	public function getRetoureService(): DHLRetoureService {
		$client = new HttpClient($this->requestFactory, $this->client, 'https://cig.dhl.de');
		return new DHLRetoureService($this->businessPortalCredentials, $this->credentials, $client);
	}

	public function getParcelStatusService(): DHLParcelStatusService {
		return new DHLParcelStatusService($this->credentials, $this->requestFactory, $this->client);
	}
}
