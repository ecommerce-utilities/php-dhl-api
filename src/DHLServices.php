<?php
namespace EcommerceUtilities\DHL;

use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthTokenProvider;
use EcommerceUtilities\DHL\Http\HttpClient;
use EcommerceUtilities\DHL\Services\DHLParcelStatusService;
use EcommerceUtilities\DHL\Services\DHLRetoureService;
use EcommerceUtilities\DHL\Services\DHLShipmentService;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class DHLServices {
	public function __construct(
		private readonly DHLOAuthCredentials $businessPortalCredentials,
		private readonly DHLBusinessPortalCredentials $credentials,
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

	public function getShipmentService(): DHLShipmentService {
		$environmentBaseUri = $this->credentials->isProductionEnv
			? 'https://api-eu.dhl.com/parcel/de'
			: 'https://api-sandbox.dhl.com/parcel/de';

		$authClient = new HttpClient($this->requestFactory, $this->client, $environmentBaseUri . '/account/auth/ropc/v1');
		$shipmentClient = new HttpClient($this->requestFactory, $this->client, $environmentBaseUri . '/shipping/v2');

		$tokenProvider = new DHLOAuthTokenProvider(
			oauthCredentials: $this->businessPortalCredentials,
			credentials: $this->credentials,
			client: $authClient
		);

		return new DHLShipmentService($tokenProvider, $shipmentClient);
	}
}
