<?php

namespace EcommerceUtilities\DHL\Services;

use DateTimeImmutable;
use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Common\DHLRequestValidationException;
use EcommerceUtilities\DHL\Common\DHLCountryCodes;
use EcommerceUtilities\DHL\Common\DHLOAuthTokenProvider;
use EcommerceUtilities\DHL\Common\DHLTools;
use EcommerceUtilities\DHL\Http\DHLHttpClient;
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
use Throwable;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentValidationResponse;

class DHLShipmentService {
	public function __construct(
		private readonly DHLOAuthTokenProvider $oAuthTokenProvider,
		private readonly DHLHttpClient $client,
	) {}

	public function createLabel(DHLShippingServiceConfiguration $shippingService, DHLShipmentRequest $request): DHLShipmentServiceResponse {
		['item' => $item, 'httpStatus' => $httpStatus] = $this->sendShipment($shippingService, $request, false);
		$trackingNumber = $item->shipmentNo ?? null;
		if(!is_string($trackingNumber) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{0,49}$/D', $trackingNumber)) {
			throw new DHLApiException('DHL response did not contain a valid shipment number', httpStatus: $httpStatus);
		}
		return new DHLShipmentServiceResponse(
			trackingNumber: $trackingNumber,
			labelData: $this->decodeDocument($item->label ?? null, 'label', $httpStatus),
			codLabelData: isset($item->codLabel) ? $this->decodeDocument($item->codLabel, 'codLabel', $httpStatus) : null,
			routingCode: is_string($item->routingCode ?? null) ? $item->routingCode : null,
			data: $item,
			warnings: $this->validationDetails($item->validationMessages ?? [], warningsOnly: true),
		);
	}

	public function validateShipment(DHLShippingServiceConfiguration $shippingService, DHLShipmentRequest $request): DHLShipmentValidationResponse {
		['item' => $item] = $this->sendShipment($shippingService, $request, true);
		return new DHLShipmentValidationResponse($item, $this->validationDetails($item->validationMessages ?? [], warningsOnly: true));
	}

	/** @return array{item: object, httpStatus: int} */
	private function sendShipment(DHLShippingServiceConfiguration $shippingService, DHLShipmentRequest $request, bool $validate): array {
		try {
			$shipment = [
				'product' => $shippingService->getProductKeyDomestic($request->recipientAddress->countryCode),
				'billingNumber' => $shippingService->getBillingNumberDomestic($request->recipientAddress->countryCode),
				'shipDate' => ($request->shipDate ?? new DateTimeImmutable())->format('Y-m-d'),
				'shipper' => $this->buildShipper($request),
				'consignee' => $this->buildConsignee($request),
				'details' => ['weight' => ['uom' => 'kg', 'value' => $request->weight]],
			];
			if($request->reference !== '') {
				$this->validateLength($request->reference, 8, 35, 'Sendungsreferenz');
				$shipment['refNo'] = $request->reference;
			}
			$services = $this->buildServices($request->services);
			if($services !== []) {
				$shipment['services'] = $services;
			}
			$body = DHLTools::jsonEncode(['profile' => $shippingService->getProfile(), 'shipments' => [$shipment]]);
			$query = $this->buildQueryParameters($shippingService);
			if($validate) {
				$query['validate'] = 'true';
			}
		} catch(DHLRequestValidationException $e) {
			throw $e;
		} catch(DHLApiException $e) {
			throw new DHLRequestValidationException($e->getMessage(), $e->details, $e);
		} catch(Throwable $e) {
			throw new DHLRequestValidationException('Die DHL-Anfrage konnte nicht vorbereitet werden.', previous: $e);
		}

		// Authentication must finish before the shipment POST; failures here cannot create a shipment.
		try {
			$token = $this->oAuthTokenProvider->getToken();
		} catch(Throwable $e) {
			throw new DHLApiException(
				$e instanceof DHLApiException ? $e->getMessage() : 'DHL authentication failed before shipment creation.',
				previous: $e,
				httpStatus: $e instanceof DHLApiException ? $e->httpStatus : null,
				details: $e instanceof DHLApiException ? $e->details : [],
				definiteRejection: true,
			);
		}
		try {
			$response = $this->client->post(
				path: '/parcel/de/shipping/v2/orders?' . http_build_query($query),
				body: $body,
				options: ['headers' => [
					'Accept' => 'application/json',
					'Accept-Language' => $shippingService->getAcceptLanguage(),
					'Authorization' => sprintf('Bearer %s', $token),
					'Content-Type' => 'application/json',
				]],
			);
		} catch(HttpClientException $e) {
			throw $this->createApiExceptionFromResponse($e->response, $validate, $e);
		} catch(Throwable $e) {
			throw new DHLApiException('The DHL shipment request did not complete. Check its result before retrying.', previous: $e, definiteRejection: $validate);
		}

		try {
			$data = DHLTools::jsonDecode($response->body, asObject: true);
		} catch(JsonException $e) {
			throw new DHLApiException('DHL response contained invalid JSON', previous: $e, httpStatus: $response->statusCode, definiteRejection: $validate);
		}
		$items = is_object($data) ? ($data->items ?? null) : null;
		$item = is_array($items) && count($items) === 1 ? ($items[0] ?? null) : null;
		if(!is_object($item)) {
			throw new DHLApiException('DHL response did not contain exactly one shipment result', httpStatus: $response->statusCode, definiteRejection: $validate);
		}
		$status = is_object($item->sstatus ?? null) ? $item->sstatus : null;
		$statusCode = $status->status ?? $status->statusCode ?? null;
		if(!is_int($statusCode) || $statusCode < 200 || $statusCode >= 300) {
			throw $this->createApiExceptionFromStatus($status, $item->validationMessages ?? [], $response->statusCode, $validate || (is_int($statusCode) && $this->isDefiniteRejection($statusCode)));
		}
		return ['item' => $item, 'httpStatus' => $response->statusCode];
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
			'name1' => $this->nameLine($request->senderAddress->company, 'Absendername'),
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

			$addition = trim($recipientAddress->addressAddition ?? '');
			$isDomestic = DHLCountryCodes::normalize($recipientAddress->countryCode) === 'DEU';
			if($addition !== '') {
				$this->validateLength($addition, 1, $isDomestic ? 50 : 60, 'Adresszusatz');
				if($isDomestic) {
					if(count($nameLines) < 3) {
						$nameLines[] = $addition;
					} elseif(mb_strlen($nameLines[2] . ' ' . $addition, 'UTF-8') <= 50) {
						$nameLines[2] .= ' ' . $addition;
					} else {
						throw new DHLRequestValidationException('Name und Adresszusatz passen nicht vollständig in die drei DHL-Namenszeilen. Bitte kürzen Sie die Angaben bewusst.');
					}
				}
			}
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
			if($addition !== '' && !$isDomestic) {
				$consignee['additionalAddressInformation1'] = $addition;
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
			$this->validatePickupCountry($recipientAddress->countryCode);
			$this->validatePostNumber($recipientAddress->customerNumber);
			return [
				'name' => $this->buildSingleLinePersonName($recipientAddress->firstname, $recipientAddress->lastname),
				'lockerID' => $this->parseNumericIdentifier($recipientAddress->packstationNumber, 'Packstationnummer', 100, 999),
				'postNumber' => $recipientAddress->customerNumber,
				'postalCode' => $recipientAddress->zip,
				'city' => $recipientAddress->city,
				'country' => DHLCountryCodes::normalize($recipientAddress->countryCode),
			];
		}

		if($recipientAddress instanceof DHLShipmentRecipientAddressPostfiliale) {
			$this->validatePickupCountry($recipientAddress->countryCode);
			if($recipientAddress->customerNumber !== '') {
				$this->validatePostNumber($recipientAddress->customerNumber);
			} elseif(trim($request->email ?? '') === '') {
				throw new DHLRequestValidationException('Für die Postfiliale ist eine Postnummer oder E-Mail-Adresse erforderlich.');
			}
			$consignee = [
				'name' => $this->buildSingleLinePersonName($recipientAddress->firstname, $recipientAddress->lastname),
				'retailID' => $this->parseNumericIdentifier($recipientAddress->postfilialNumber, 'Postfilialnummer', 401, 999),
				'postalCode' => $recipientAddress->zip,
				'city' => $recipientAddress->city,
				'country' => DHLCountryCodes::normalize($recipientAddress->countryCode),
			];

			if($recipientAddress->customerNumber !== '') {
				$consignee['postNumber'] = $recipientAddress->customerNumber;
			}
			if($request->email !== null && $request->email !== '') {
				$consignee['email'] = $request->email;
			}

			return $consignee;
		}

		throw new DHLRequestValidationException(sprintf('Unsupported recipient address type "%s"', get_debug_type($recipientAddress)));
	}

	/**
	 * @param DHLShipmentServiceProduct[] $services
	 * @return array<string, mixed>
	 */
	private function buildServices(array $services): array {
		$result = [];
		foreach($services as $service) {
			if($service instanceof DHLCashOnDeliveryService) {
				$this->validateLength($service->reference, 0, 35, 'Nachnahme-Verwendungszweck 1');
				$this->validateLength($service->reference2, 0, 35, 'Nachnahme-Verwendungszweck 2');
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

			throw new DHLRequestValidationException(sprintf('Unsupported shipment service "%s"', get_debug_type($service)));
		}

		return $result;
	}

	/** @return list<string> */
	private function buildPostalNameLines(string $company, ?string $firstname, ?string $lastname): array {
		$lines = [];
		$company = trim($company);
		$firstname = trim($firstname ?? '');
		$lastname = trim($lastname ?? '');
		if($company !== '') {
			$lines[] = $this->nameLine($company, 'Firma');
		}
		$personParts = array_values(array_filter([$firstname, $lastname], static fn(string $value): bool => $value !== ''));
		if($personParts !== []) {
			$fullName = implode(' ', $personParts);
			if(mb_strlen($fullName, 'UTF-8') <= 50) {
				$lines[] = $fullName;
			} else {
				foreach($personParts as $part) {
					$lines[] = $this->nameLine($part, 'Vor- oder Nachname');
				}
			}
		}
		if($lines === []) {
			throw new DHLRequestValidationException('Der Empfängername darf nicht leer sein.');
		}
		return $lines;
	}

	private function buildSingleLinePersonName(?string $firstname, ?string $lastname): string {
		$fullName = trim(implode(' ', array_filter([trim($firstname ?? ''), trim($lastname ?? '')], static fn(string $value): bool => $value !== '')));
		return $this->nameLine($fullName, 'Vollständiger Name für Packstation oder Postfiliale');
	}

	private function nameLine(string $value, string $field): string {
		$value = trim($value);
		$this->validateLength($value, 1, 50, $field);
		return $value;
	}

	private function validateLength(string $value, int $min, int $max, string $field): void {
		$length = mb_strlen($value, 'UTF-8');
		if($length < $min || $length > $max) {
			throw new DHLRequestValidationException(sprintf('%s muss %d bis %d Zeichen enthalten; die Angabe wird nicht automatisch gekürzt.', $field, $min, $max));
		}
	}

	private function validatePickupCountry(string $country): void {
		if(DHLCountryCodes::normalize($country) !== 'DEU') {
			throw new DHLRequestValidationException('Packstation und Postfiliale sind nur für Deutschland verfügbar.');
		}
	}

	private function validatePostNumber(string $value): void {
		if(!preg_match('/^[0-9]{6,10}$/D', $value)) {
			throw new DHLRequestValidationException('Die Postnummer muss 6 bis 10 Ziffern enthalten.');
		}
	}

	private function parseNumericIdentifier(string $value, string $fieldName, int $min, int $max): int {
		if(!preg_match('/^[0-9]{3}$/D', $value) || (int) $value < $min || (int) $value > $max) {
			throw new DHLRequestValidationException(sprintf('%s muss zwischen %d und %d liegen.', $fieldName, $min, $max));
		}
		return (int) $value;
	}

	private function decodeDocument(mixed $document, string $documentName, int $httpStatus): string {
		if(!is_object($document) || !is_string($document->b64 ?? null) || $document->b64 === '') {
			throw new DHLApiException(sprintf('DHL response did not contain %s data', $documentName), httpStatus: $httpStatus);
		}
		$decoded = base64_decode($document->b64, true);
		if($decoded === false || $decoded === '') {
			throw new DHLApiException(sprintf('DHL response contained invalid base64 %s data', $documentName), httpStatus: $httpStatus);
		}
		return $decoded;
	}

	private function isDefiniteRejection(int $status): bool {
		return $status >= 400 && $status < 500 && !in_array($status, [408, 409], true);
	}

	private function createApiExceptionFromResponse(HttpResponse $response, bool $validate, Throwable $previous): DHLApiException {
		$decoded = DHLTools::jsonDecode($response->body, asObject: true, default: (object) []);
		$data = is_object($decoded) ? $decoded : (object) [];
		$items = $data->items ?? null;
		$item = is_array($items) && is_object($items[0] ?? null) ? $items[0] : null;
		$itemStatus = $item->sstatus ?? null;
		$status = is_object($itemStatus) ? $itemStatus : (is_object($data->status ?? null) ? $data->status : null);
		$details = $this->validationDetails($item->validationMessages ?? []);
		$message = $status->detail ?? $status->title ?? $data->detail ?? $data->message ?? $data->title ?? null;
		return new DHLApiException(
			is_string($message) && $message !== '' ? $message : sprintf('DHL API request failed with HTTP status %d', $response->statusCode),
			previous: $previous,
			httpStatus: $response->statusCode,
			details: $details,
			definiteRejection: $validate || $this->isDefiniteRejection($response->statusCode),
		);
	}

	private function createApiExceptionFromStatus(?object $status, mixed $validationMessages, int $httpStatus, bool $definiteRejection): DHLApiException {
		$message = $status->detail ?? $status->title ?? null;
		return new DHLApiException(
			is_string($message) && $message !== '' ? $message : 'DHL response did not contain a successful shipment status',
			httpStatus: $httpStatus,
			details: $this->validationDetails($validationMessages),
			definiteRejection: $definiteRejection,
		);
	}

	/** @return list<string> */
	private function validationDetails(mixed $messages, bool $warningsOnly = false): array {
		$details = [];
		foreach(is_array($messages) ? $messages : [] as $message) {
			if(!is_object($message) || !is_string($message->validationMessage ?? null) || trim($message->validationMessage) === '') {
				continue;
			}
			if($warningsOnly && (!is_string($message->validationState ?? null) || strcasecmp($message->validationState, 'Warning') !== 0)) {
				continue;
			}
			$property = is_string($message->property ?? null) && $message->property !== '' ? $message->property . ': ' : '';
			$detail = $property . $message->validationMessage;
			if(!in_array($detail, $details, true)) {
				$details[] = $detail;
			}
		}
		return $details;
	}
}
