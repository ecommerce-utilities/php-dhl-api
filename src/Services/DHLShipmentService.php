<?php

namespace EcommerceUtilities\DHL\Services;

use DateTimeImmutable;
use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Common\DHLCountryCodes;
use EcommerceUtilities\DHL\Common\DHLOAuthTokenProvider;
use EcommerceUtilities\DHL\Common\DHLTools;
use EcommerceUtilities\DHL\Http\HttpClient;
use EcommerceUtilities\DHL\Http\HttpClientException;
use EcommerceUtilities\DHL\Http\HttpResponse;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLCashOnDeliveryService;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLNamedPersonOnly;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRecipientAddressPackstation;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRecipientAddressPostfiliale;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRecipientAddressPostal;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRequest;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentServiceResponse;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentServiceProduct;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingServiceConfiguration;
use JsonException;

class DHLShipmentService {
	public function __construct(
		private readonly DHLOAuthTokenProvider $oAuthTokenProvider,
		private readonly HttpClient $client,
	) {}

	public function createLabel(DHLShippingServiceConfiguration $shippingService, DHLShipmentRequest $request): DHLShipmentServiceResponse {
		$shipment = [
			'product' => $shippingService->getProductKeyDomestic($request->recipientAddress->countryCode),
			'billingNumber' => $shippingService->getBillingNumberDomestic($request->recipientAddress->countryCode),
			'shipDate' => ($request->shipDate ?? new DateTimeImmutable())->format('Y-m-d'),
			'shipper' => $this->buildShipper($request),
			'consignee' => $this->buildConsignee($request),
			'details' => [
				'weight' => [
					'uom' => 'kg',
					'value' => $request->weight,
				],
			],
		];

		if($request->reference) {
			$shipment['refNo'] = $request->reference;
		}

		$services = $this->buildServices($request->services);
		if($services !== []) {
			$shipment['services'] = $services;
		}

		try {
			$response = $this->client->post(
				path: '/parcel/de/shipping/v2/orders?' . http_build_query($this->buildQueryParameters($shippingService)),
				body: DHLTools::jsonEncode(['profile' => $shippingService->getProfile(), 'shipments' => [$shipment]]),
				options: [
					'headers' => [
						'Accept' => 'application/json',
						'Accept-Language' => $shippingService->getAcceptLanguage(),
						'Authorization' => sprintf('Bearer %s', $this->oAuthTokenProvider->getToken()),
						'Content-Type' => 'application/json',
					],
				]
			);
		} catch(HttpClientException $e) {
			throw $this->createApiExceptionFromResponse($e->response);
		} catch(JsonException $e) {
			throw new DHLApiException($e->getMessage(), $e->getCode(), $e);
		}

		$data = DHLTools::jsonDecode($response->body, asObject: true, default: (object) []);
		$item = $data->items[0] ?? null;
		if(!is_object($item)) {
			throw new DHLApiException('DHL response did not contain shipment data');
		}

		$status = is_object($item->sstatus ?? null) ? $item->sstatus : null;
		$statusCode = (int) ($status->status ?? $status->statusCode ?? 0);
		if($statusCode >= 400) {
			throw $this->createApiExceptionFromStatus($status, $item->validationMessages ?? []);
		}

		return new DHLShipmentServiceResponse(
			trackingNumber: (string) ($item->shipmentNo ?? ''),
			labelData: $this->decodeDocument($item->label ?? null, 'label'),
			codLabelData: $this->decodeOptionalDocument($item->codLabel ?? null),
			routingCode: isset($item->routingCode) ? (string) $item->routingCode : null,
			data: $item
		);
	}

	/**
	 * @return array<string, string>
	 */
	private function buildQueryParameters(DHLShippingServiceConfiguration $shippingService): array {
		$query = [
			'includeDocs' => 'include',
			'docFormat' => $shippingService->getDocumentFormat(),
			'printFormat' => $shippingService->getPrintFormat(),
		];

		if($shippingService->mustEncode()) {
			$query['mustEncode'] = 'true';
		}

		return $query;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildShipper(DHLShipmentRequest $request): array {
		return [
			'name1' => $this->trimNameLine($request->senderAddress->company),
			'addressStreet' => $request->senderAddress->street,
			'addressHouse' => $request->senderAddress->houseNumber,
			'postalCode' => $request->senderAddress->zip,
			'city' => $request->senderAddress->city,
			'country' => DHLCountryCodes::normalize($request->senderAddress->countryCode),
			'email' => $request->senderAddress->mail,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildConsignee(DHLShipmentRequest $request): array {
		$recipientAddress = $request->recipientAddress;
		if($recipientAddress instanceof DHLShipmentRecipientAddressPostal) {
			$nameLines = $this->buildPostalNameLines(
				$recipientAddress->company,
				$recipientAddress->firstname,
				$recipientAddress->lastname,
			);

			$consignee = [
				'name1' => $nameLines[0],
				'addressStreet' => $recipientAddress->street,
				'addressHouse' => $recipientAddress->houseNumber,
				'postalCode' => $recipientAddress->zip,
				'city' => $recipientAddress->city,
				'country' => DHLCountryCodes::normalize($recipientAddress->countryCode),
			];

			if(isset($nameLines[1])) {
				$consignee['name2'] = $nameLines[1];
			}
			if(isset($nameLines[2])) {
				$consignee['name3'] = $nameLines[2];
			}
			if($recipientAddress->addressAddition !== null && $recipientAddress->addressAddition !== '') {
				$consignee['additionalAddressInformation1'] = $recipientAddress->addressAddition;
			}
			if($recipientAddress->state !== null && $recipientAddress->state !== '') {
				$consignee['state'] = $recipientAddress->state;
			}
			if($request->email !== null && $request->email !== '') {
				$consignee['email'] = $request->email;
			}

			$normalizedCountry = DHLCountryCodes::normalize($recipientAddress->countryCode);
			if($request->phone !== null && $request->phone !== '' && $normalizedCountry !== 'DEU') {
				$consignee['phone'] = $request->phone;
			}

			return $consignee;
		}

		if($recipientAddress instanceof DHLShipmentRecipientAddressPackstation) {
			return [
				'name' => $this->buildSingleLinePersonName($recipientAddress->firstname, $recipientAddress->lastname),
				'lockerID' => $this->parseNumericIdentifier($recipientAddress->packstationNumber, 'packstationNumber'),
				'postNumber' => $recipientAddress->customerNumber,
				'postalCode' => $recipientAddress->zip,
				'city' => $recipientAddress->city,
				'country' => DHLCountryCodes::normalize($recipientAddress->countryCode),
			];
		}

		if($recipientAddress instanceof DHLShipmentRecipientAddressPostfiliale) {
			$consignee = [
				'name' => $this->buildSingleLinePersonName($recipientAddress->firstname, $recipientAddress->lastname),
				'retailID' => $this->parseNumericIdentifier($recipientAddress->postfilialNumber, 'postfilialNumber'),
				'postNumber' => $recipientAddress->customerNumber,
				'postalCode' => $recipientAddress->zip,
				'city' => $recipientAddress->city,
				'country' => DHLCountryCodes::normalize($recipientAddress->countryCode),
			];

			if($request->email !== null && $request->email !== '') {
				$consignee['email'] = $request->email;
			}

			return $consignee;
		}

		throw new DHLApiException(sprintf('Unsupported recipient address type "%s"', get_debug_type($recipientAddress)));
	}

	/**
	 * @param DHLShipmentServiceProduct[] $services
	 * @return array<string, mixed>
	 */
	private function buildServices(array $services): array {
		$result = [];
		foreach($services as $service) {
			if($service instanceof DHLCashOnDeliveryService) {
				$result['cashOnDelivery'] = [
					'amount' => [
						'currency' => 'EUR',
						'value' => $service->amount,
					],
					'bankAccount' => [
						'accountHolder' => $service->accountOwner,
						'bankName' => $service->bankName,
						'iban' => $service->bankIban,
						'bic' => $service->bankBic,
					],
					'transferNote1' => $service->reference,
					'transferNote2' => $service->reference2,
				];
				continue;
			}

			if($service instanceof DHLNamedPersonOnly) {
				$result['namedPersonOnly'] = true;
				continue;
			}

			throw new DHLApiException(sprintf('Unsupported shipment service "%s"', get_debug_type($service)));
		}

		return $result;
	}

	/**
	 * @return list<string>
	 */
	private function buildPostalNameLines(string $company, ?string $firstname, ?string $lastname): array {
		$lines = [];
		$company = trim($company);
		if($company !== '') {
			$lines[] = $this->trimNameLine($company);
		}

		$combinedName = trim(implode(' ', array_filter([$firstname, $lastname], static fn(?string $value) => $value !== null && $value !== '')));
		if($combinedName !== '') {
			if($company === '' || mb_strlen($combinedName, 'UTF-8') <= 50) {
				$lines[] = $this->trimNameLine($combinedName);
			} else {
				if($firstname !== null && $firstname !== '') {
					$lines[] = $this->trimNameLine($firstname);
				}
				if($lastname !== null && $lastname !== '') {
					$lines[] = $this->trimNameLine($lastname);
				}
			}
		}

		$lines = array_values(array_filter($lines, static fn(string $line) => $line !== ''));
		if($lines === []) {
			throw new DHLApiException('Recipient name must not be empty');
		}

		return array_slice($lines, 0, 3);
	}

	private function buildSingleLinePersonName(?string $firstname, ?string $lastname): string {
		$fullName = trim(implode(' ', array_filter([$firstname, $lastname], static fn(?string $value) => $value !== null && $value !== '')));
		if($fullName === '') {
			throw new DHLApiException('Recipient full name must not be empty');
		}

		return $this->trimNameLine($fullName);
	}

	private function trimNameLine(string $value): string {
		return mb_substr(trim($value), 0, 50, 'UTF-8');
	}

	private function parseNumericIdentifier(string $value, string $fieldName): int {
		if(!ctype_digit($value)) {
			throw new DHLApiException(sprintf('%s must contain digits only', $fieldName));
		}

		return (int) $value;
	}

	private function decodeDocument(mixed $document, string $documentName): string {
		if(!is_object($document) || !is_string($document->b64 ?? null) || $document->b64 === '') {
			throw new DHLApiException(sprintf('DHL response did not contain %s data', $documentName));
		}

		$decoded = base64_decode($document->b64, true);
		if($decoded === false) {
			throw new DHLApiException(sprintf('DHL response contained invalid base64 %s data', $documentName));
		}

		return $decoded;
	}

	private function decodeOptionalDocument(mixed $document): ?string {
		if(!is_object($document) || !is_string($document->b64 ?? null) || $document->b64 === '') {
			return null;
		}

		$decoded = base64_decode($document->b64, true);
		if($decoded === false) {
			throw new DHLApiException('DHL response contained invalid base64 document data');
		}

		return $decoded;
	}

	private function createApiExceptionFromResponse(HttpResponse $response): DHLApiException {
		$data = DHLTools::jsonDecode($response->body, asObject: true, default: (object) []);

		$status = null;
		if(is_object($data->status ?? null)) {
			$status = $data->status;
		} elseif(is_object($data->items[0]->sstatus ?? null)) {
			$status = $data->items[0]->sstatus;
		}

		if($status !== null) {
			return $this->createApiExceptionFromStatus($status, $data->items[0]->validationMessages ?? []);
		}

		$message = $data->detail
			?? $data->message
			?? $data->title
			?? "DHL API request failed with HTTP status {$response->statusCode}";

		return new DHLApiException((string) $message);
	}

	/**
	 * @param array<int, mixed>|mixed $validationMessages
	 */
	private function createApiExceptionFromStatus(?object $status, mixed $validationMessages = []): DHLApiException {
		$message = $status?->detail ?? $status?->title ?? 'DHL shipment request failed';
		$details = [];

		if(is_array($validationMessages)) {
			foreach($validationMessages as $validationMessage) {
				if(!is_object($validationMessage)) {
					continue;
				}

				$property = is_string($validationMessage->property ?? null) ? $validationMessage->property : 'shipment';
				$detail = is_string($validationMessage->validationMessage ?? null) ? $validationMessage->validationMessage : null;
				if($detail !== null && $detail !== '') {
					$details[] = sprintf('%s: %s', $property, $detail);
				}
			}
		}

		if($details !== []) {
			$message .= ' (' . implode('; ', $details) . ')';
		}

		return new DHLApiException($message);
	}
}
