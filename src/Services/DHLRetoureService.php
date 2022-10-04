<?php
namespace EcommerceUtilities\DHL\Services;

use EcommerceUtilities\DHL\Common\DHLApiCredentials;
use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\Services\DHLRetoureService\DHLRetoureServiceResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

class DHLRetoureService {
	/** @var DHLApiCredentials */
	private $credentials;
	/** @var DHLBusinessPortalCredentials */
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
	 * @param string $name1
	 * @param string|null $name2
	 * @param string|null $name3
	 * @param string $street
	 * @param string $streetNumber
	 * @param string $zip
	 * @param string $city
	 * @param string $countryId
	 * @param string|null $voucherNr
	 * @param string|null$shipmentReference
	 * @return DHLRetoureServiceResponse
	 */
	public function getRetourePdf(string $name1, ?string $name2, ?string $name3, string $street, string $streetNumber, string $zip, string $city, string $countryId, ?string $voucherNr = null, ?string $shipmentReference = null): DHLRetoureServiceResponse {
		$request = $this->requestFactory->createRequest('POST', $this->credentials->isProductionEnv() ? 'https://cig.dhl.de/services/production/rest/returns/' : 'https://cig.dhl.de/services/sandbox/rest/returns/');

		$auth = static function ($username, $password) {
			$basicAuthCredentials = sprintf('%s:%s', $username, $password);
			return base64_encode($basicAuthCredentials);
		};

		$request = $request
			->withHeader('Authorization', sprintf('Basic %s', $auth($this->credentials->getUsername(), $this->credentials->getPassword())))
			->withHeader('DPDHL-User-Authentication-Token', $auth($this->businessPortalCredentials->getUsername(), $this->businessPortalCredentials->getPassword()))
			->withHeader('Accept', 'application/json')
			->withHeader('Content-Type', 'application/json');

		$data = json_encode([
			'receiverId' => $this->credentials->getReceiverId(),
			'customerReference' => $voucherNr,
			'shipmentReference' => $shipmentReference,
			'senderAddress' => [
				'name1' => $name1,
				'name2' => $name2,
				'name3' => $name3,
				'streetName' => $street,
				'houseNumber' => $streetNumber,
				'postCode' => $zip,
				'city' => $city,
				'country' => ['countryISOCode' => $countryId]
			],
			#'weightInGrams' => 1000,
			'returnDocumentType' => 'SHIPMENT_LABEL'
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

		$request = $request->withBody($this->streamFactory->createStream($data));
		$response = $this->client->sendRequest($request);

		$result = $response->getBody()->getContents();

		try {
			$data = json_decode($result, false, 512, JSON_THROW_ON_ERROR);

			return new DHLRetoureServiceResponse(
				$data->shipmentNumber,
				base64_decode($data->labelData),
				$data
			);
		} catch(Throwable $e) {
			throw new DHLApiException($e->getMessage(), $e->getCode(), $e);
		}
	}
}
