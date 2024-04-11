<?php
namespace EcommerceUtilities\DHL;

use EcommerceUtilities\DHL\Common\DHLApiCredentials;
use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\Services\DHLRetoureService;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class DHLServices {
	public function __construct(
		private readonly DHLBusinessPortalCredentials $businessPortalCredentials,
		private readonly DHLApiCredentials $credentials,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly StreamFactoryInterface $streamFactory,
		private readonly ClientInterface $client
	) {}

	/**
	 * @return DHLRetoureService
	 */
	public function getRetoureService(): DHLRetoureService {
		return new DHLRetoureService($this->businessPortalCredentials, $this->credentials, $this->requestFactory, $this->streamFactory, $this->client);
	}
}
