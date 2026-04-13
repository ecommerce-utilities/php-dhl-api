<?php
namespace EcommerceUtilities\DHL\Services;

use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthTokenProvider;
use EcommerceUtilities\DHL\Common\DHLTools;
use EcommerceUtilities\DHL\Http\DHLHttpClient;
use EcommerceUtilities\DHL\Http\HttpClientException;
use EcommerceUtilities\DHL\Http\HttpResponse;
use EcommerceUtilities\DHL\Services\DHLRetoureService\DHLRetoureServiceResponse;
use Throwable;

class DHLRetoureService {
	public function __construct(
		private readonly DHLOAuthTokenProvider $oAuthTokenProvider,
		private readonly DHLOAuthCredentials $credentials,
		private readonly DHLHttpClient $client,
	) {}

	/**
	 * @return DHLRetoureServiceResponse
	 */
	public function getRetourePdf(string $name1, ?string $name2, ?string $name3, string $street, string $streetNumber, string $zip, string $city, string $countryId, ?string $voucherNr = null, ?string $shipmentReference = null): DHLRetoureServiceResponse {
		$body = DHLTools::jsonEncode([
			'receiverId' => $this->credentials->receiverId,
			'customerReference' => $voucherNr,
			'shipmentReference' => $shipmentReference,
			'shipper' => [
				'name1' => $name1,
				'name2' => $name2,
				'name3' => $name3,
				'addressStreet' => $street,
				'addressHouse' => $streetNumber,
				'postalCode' => $zip,
				'city' => $city,
				'country' => ['countryISOCode' => $countryId]
			],
			#'weightInGrams' => 1000,
			'returnDocumentType' => 'SHIPMENT_LABEL'
		]);

		$token = $this->oAuthTokenProvider->getToken();
		$headers = [
			'Authorization' => sprintf('Bearer %s', $token),
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
		];

		try {
			$response = $this->client->post('/parcel/de/shipping/returns/v1/orders?labelType=SHIPMENT_LABEL', $body, ['headers' => $headers]);
		} catch(HttpClientException $e) {
			throw $this->createApiExceptionFromResponse($e->response);
		}

		try {
			$data = DHLTools::jsonDecode($response->body, asObject: true);
			$status = is_object($data->sstatus ?? null)
				? $data->sstatus
				: (is_object($data->status ?? null) ? $data->status : null);
			$statusCode = (int) ($status->status ?? $status->statusCode ?? 0);
			if($statusCode >= 400) {
				$message = $status->detail ?? $status->title ?? 'DHL returns request failed';
				throw new DHLApiException((string) $message);
			}

			if(($data->code ?? 0) > 0) {
				throw new DHLApiException(sprintf("DHL-Error: %s (%d)", $data->detail ?? 'No details provided', $data->code ?? 0));
			}

			return new DHLRetoureServiceResponse(
				$this->extractTrackingNumber($data),
				$this->decodeLabelData($data),
				$data
			);
		} catch(DHLApiException $e) {
			throw $e;
		} catch(Throwable $e) {
			throw new DHLApiException($e->getMessage(), $e->getCode(), $e);
		}
	}

	private function extractTrackingNumber(object $data): string {
		$trackingNumber = $data->shipmentNo ?? $data->shipmentNumber ?? null;
		if(!is_string($trackingNumber) || $trackingNumber === '') {
			throw new DHLApiException('DHL response did not contain shipment data');
		}

		return $trackingNumber;
	}

	private function decodeLabelData(object $data): string {
		if(is_string($data->labelData ?? null)) {
			$decoded = base64_decode($data->labelData, true);
			if($decoded === false) {
				throw new DHLApiException('DHL response contained invalid label data');
			}

			return $decoded;
		}

		$labelB64 = is_object($data->label ?? null) ? ($data->label->b64 ?? null) : null;
		if(is_string($labelB64)) {
			$decoded = base64_decode($labelB64, true);
			if($decoded === false) {
				throw new DHLApiException('DHL response contained invalid label data');
			}

			return $decoded;
		}

		throw new DHLApiException('DHL response did not contain label data');
	}

	private function createApiExceptionFromResponse(HttpResponse $response): DHLApiException {
		$data = DHLTools::jsonDecode($response->body, asObject: true, default: (object) []);
		$message = $data->detail ?? $data->message ?? $data->title ?? $response->body;

		return new DHLApiException((string) $message);
	}
}
