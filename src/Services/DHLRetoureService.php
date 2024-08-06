<?php
namespace EcommerceUtilities\DHL\Services;

use EcommerceUtilities\DHL\Common\DHLApiCredentials;
use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\Common\DHLTools;
use EcommerceUtilities\DHL\Services\DHLRetoureService\DHLRetoureServiceResponse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

class DHLRetoureService {
	public function __construct(
		private readonly DHLBusinessPortalCredentials $businessPortalCredentials,
		private readonly DHLApiCredentials $credentials,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly StreamFactoryInterface $streamFactory,
		private readonly ClientInterface $client
	) {}

	/**
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

		$data = DHLTools::jsonEncode([
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
		]);

		$request = $request->withBody($this->streamFactory->createStream($data));
		$response = $this->client->sendRequest($request);

		$result = $response->getBody()->getContents();

		try {
			$data = DHLTools::jsonDecode($result);

			if(($data->code ?? 0) > 0) {
				throw new DHLApiException(sprintf("DHL-Error: %s (%d)", $data->detail ?? 'No details provided', $data->code ?? 0));
			}

			return new DHLRetoureServiceResponse(
				$data->shipmentNumber,
				base64_decode($data->labelData),
				$data
			);
		} catch(DHLApiException $e) {
			throw $e;
		} catch(Throwable $e) {
			throw new DHLApiException($e->getMessage(), $e->getCode(), $e);
		}
	}
}
