<?php
namespace EcommerceUtilities\DHL;

use EcommerceUtilities\DHL\Common\DHLApiCredentials;
use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\Services\DHLRetoureService;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class DHLServices {
	/** @var DHLApiCredentials */
	private $credentials;
	/** @var DHLApiCredentials */
	private $businessPortalCredentials;
	/** @var RequestFactoryInterface */
	private $requestFactory;
	/** @var StreamFactoryInterface */
	private $streamFactory;
	/** @var ClientInterface */
	private $client;

	/**
	 * @param DHLBusinessPortalCredentials $businessPortalCredentials
	 * @param DHLApiCredentials $credentials
	 * @param RequestFactoryInterface $requestFactory
	 * @param StreamFactoryInterface $streamFactory
	 * @param ClientInterface $client
	 */
	public function __construct(DHLBusinessPortalCredentials $businessPortalCredentials, DHLApiCredentials $credentials, RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory, ClientInterface $client) {
		$this->businessPortalCredentials = $businessPortalCredentials;
		$this->credentials = $credentials;
		$this->requestFactory = $requestFactory;
		$this->streamFactory = $streamFactory;
		$this->client = $client;
	}

	/**
	 * @return DHLRetoureService
	 */
	public function getRetoureService(): DHLRetoureService {
		return new DHLRetoureService($this->businessPortalCredentials, $this->credentials, $this->requestFactory, $this->streamFactory, $this->client);
	}
}
