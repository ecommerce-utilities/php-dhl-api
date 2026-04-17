<?php

namespace EcommerceUtilities\DHL\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\Services\DHLParcelStatusService\ParcelStatus;
use EcommerceUtilities\DHL\Services\DHLParcelStatusService\ParcelStatusEvent;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;

class DHLParcelStatusService {
	public function __construct(
		private readonly DHLOAuthCredentials $credentials,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly ClientInterface $client
	) {}

	/**
	 * @link https://developer.dhl.com/api-reference/dhl-parcel-de-shipment-tracking-post-parcel-germany?lang=en&language_content_entity=en
	 *
	 * @param list<array{code: string, reference?: string}> $codes
	 * @param DateTimeInterface $minDate
	 * @param DateTimeInterface $maxDate
	 * @return list<ParcelStatus>
	 */
	public function getStatus(array $codes, DateTimeInterface $minDate, DateTimeInterface $maxDate): array {
		$cleanedCodes = [];
		foreach(array_values($codes) as $idx => $codeData) {
			$cleanedCodes[$codeData['code']] = [
				'index' => $idx,
				'reference' => $codeData['reference'] ?? null,
			];
		}

		$codeArray = array_keys($cleanedCodes);

		$chunks = array_chunk($codeArray, 15);
		$result = [];

		foreach($chunks as $chunk) {
			$xml = '<data request="get-status-for-public-user" language-code="en"></data>';

			$doc = new DOMDocument();
			$doc->loadXML($xml);

			if($doc->documentElement === null) {
				throw new RuntimeException('Error parsing XML');
			}

			$doc->documentElement->setAttribute('appname', $this->credentials->businessPortalUsername);
			$doc->documentElement->setAttribute('password', $this->credentials->businessPortalPassword);

			$node = $doc->createElement('data');

			$node->setAttribute('piece-code', implode(';', $chunk));
			$node->setAttribute('date-from', $minDate->format('Y-m-d'));
			$node->setAttribute('date-to', $maxDate->format('Y-m-d'));
			$doc->documentElement->appendChild($node);

			$xml = $doc->saveXML($doc->documentElement);
			if($xml === false) {
				throw new RuntimeException('Error saving XML');
			}

			sleep(1);

			$request = $this->requestFactory->createRequest('GET', $this->getTrackingUri($xml));
			// Parcel DE Tracking still requires API key/secret via Basic auth. OAuth bearer tokens are rejected by the endpoint.
			$request = $request->withHeader('Authorization', 'Basic ' . base64_encode($this->credentials->key . ':' . $this->credentials->secret));
			$request = $request->withHeader('DHL-API-Key', $this->credentials->key);
			$response = $this->client->sendRequest($request);
			$responseXml = $response->getBody()->getContents();
			if(trim($responseXml) === '' || !str_starts_with(ltrim($responseXml), '<')) {
				throw $this->createUnexpectedResponseException($responseXml);
			}

			$responseDoc = new DOMDocument();
			$responseDoc->loadXML($responseXml);
			$xp = new DOMXPath($responseDoc);

			$root = $responseDoc->documentElement;
			if($root instanceof DOMElement) {
				$rootCode = (int) ($root->getAttribute('code') ?: '0');
				if($rootCode === 100) {
					continue;
				}
				if($rootCode > 0) {
					$errorText = trim($root->getAttribute('error'));
					if($errorText !== '') {
						throw new RuntimeException("DHL API error: $errorText");
					}
				}
			}

			$errorList = $xp->query('//data[@name="error"]');
			if($errorList === false) {
				throw new RuntimeException('Error parsing XML');
			}

			foreach($errorList as $errorNode) {
				/** @var DOMElement $errorNode */
				$errorText = $errorNode->getAttribute('error');
				throw new RuntimeException("DHL API error: $errorText");
			}

			$nodeList = $xp->query(
				'/data/data[@name="piece-status-public-list"]/data[@name="piece-status-public"]'
				. ' | /data/data[@name="piece-shipment-list"]/data[@name="piece-shipment"]'
				. ' | /data/data[@name="pieceshipmentlist"]/data[@name="pieceshipment"]'
			);
			if($nodeList === false) {
				throw new RuntimeException('Error parsing XML');
			}

			/** @var DOMElement $node */
			foreach($nodeList as $node) {
				$result[] = $this->generateParcelStatusFromNode($xp, $node, $cleanedCodes);
			}
		}

		$getIndexFromCode = static fn(string $code) => $cleanedCodes[$code]['index'] ?? 0;
		usort($result, static fn(ParcelStatus $a, ParcelStatus $b) => $getIndexFromCode($a->pieceCode) - $getIndexFromCode($b->pieceCode));

		return $result;
	}

	/**
	 * @param DOMXPath $xp
	 * @param DOMElement $node
	 * @param array<string, array{reference: null|string}> $cleanedCodes
	 * @return ParcelStatus
	 */
	private function generateParcelStatusFromNode(DOMXPath $xp, DOMNode $node, array $cleanedCodes): ParcelStatus {
		$data = [];

		/** @var DOMAttr $attr */
		foreach(($node->attributes ?? []) as $attr) {
			$data[$attr->name] = $attr->value;
		}

		$events = [];
		$eventList = $xp->query(
			'./data[@name="piece-event"]'
			. ' | ./data[@name="pieceevent"]'
			. ' | ./data[@name="piece-event-list"]/data[@name="piece-event"]'
			. ' | ./data[@name="pieceeventlist"]/data[@name="pieceevent"]',
			$node
		);
		if($eventList === false) {
			throw new RuntimeException('Error parsing XML');
		}

		foreach($eventList as $eventNode) {
			$events[] = $this->generateParcelStatusEventsFromNode($eventNode);
		}


		$trackingCode = $data['piece-code'] ?? '';

		return new ParcelStatus(
			reference: $cleanedCodes[$trackingCode]['reference'] ?? null,
			name: $data['name'] ?? '',
			pieceIdentifier: $data['piece-identifier'] ?? '',
			buildTime: new DateTimeImmutable($data['_build-time'] ?? 'now'),
			pieceId: $data['piece-id'] ?? '',
			leitcode: $data['leitcode'] ?? null,
			pslzNr: $data['pslz-nr'] ?? '',
			orderPreferredDeliveryDay: ($data['order-preferred-delivery-day'] ?? 'false') === 'true',
			searchedPieceCode: explode(';', $data['searched-piece-code'] ?? ''),
			pieceStatus: (int) ($data['piece-status'] ?? '0'),
			identifierType: (int) ($data['identifier-type'] ?? '0'),
			recipientName: $data['recipient-name'] ?? null,
			recipientId: $data['recipient-id'] ?? null,
				recipientIdText: $data['recipient-id-text'] ?? null,
				panRecipientName: $data['pan-recipient-name'] ?? null,
				streetName: $data['street-name'] ?? null,
				houseNumber: $data['house-number'] ?? null,
				cityName: $data['city-name'] ?? null,
				lastEventTimestamp: new DateTimeImmutable($data['last-event-timestamp'] ?? $data['status-timestamp'] ?? 'now'),
				shipmentType: $data['shipment-type'] ?? null,
				statusNext: $data['status-next'] ?? null,
				status: $data['status'] ?? $data['piece-status-desc'] ?? '',
			errorStatus: (int) ($data['error-status'] ?? '0'),
			deliveryEventFlag: (int) ($data['delivery-event-flag'] ?? '0'),
			upu: $data['upu'] ?? null,
			internationalFlag: (int) ($data['international-flag'] ?? '0'),
			pieceCode: $trackingCode,
			matchcode: $data['matchcode'] ?? null,
			domesticId: $data['domestic-id'] ?? null,
			airwayBillNumber: $data['airway-bill-number'] ?? null,
			ice: $data['ice'] ?? '',
			ric: $data['ric'] ?? '',
			division: $data['division'] ?? '',
			destCountry: $data['dest-country'] ?? '',
			originCountry: $data['origin-country'] ?? '',
			productCode: $data['product-code'] ?? '',
			productName: $data['product-name'] ?? '',
			searchedRefNr: $data['searched-ref-nr'] ?? null,
			standardEventCode: $data['standard-event-code'] ?? '',
			panRecipientStreet: $data['pan-recipient-street'] ?? null,
			panRecipientCity: $data['pan-recipient-city'] ?? null,
			eventCountry: $data['event-country'] ?? null,
			eventLocation: $data['event-location'] ?? null,
			preferredDeliveryDay: $data['preferred-delivery-day'] ?? null,
			preferredDeliveryTimeframeFrom: $data['preferred-delivery-timeframe-from'] ?? null,
			preferredDeliveryTimeframeTo: $data['preferred-delivery-timeframe-to'] ?? null,
			preferredTimeframeRefusedText: $data['preferred-timeframe-refused-text'] ?? null,
			shipmentLength: (float) ($data['shipment-length'] ?? '0.0'),
			shipmentWidth: (float) ($data['shipment-width'] ?? '0.0'),
			shipmentHeight: (float) ($data['shipment-height'] ?? '0.0'),
			shipmentWeight: (float) ($data['shipment-weight'] ?? '0.0'),
			events: $events
		);
	}

	private function generateParcelStatusEventsFromNode(DOMNode $node): ParcelStatusEvent {
		$eventData = [];

		/** @var object{name: string, value: string}[] $nodeAttributes */
		$nodeAttributes = $node->attributes ?? [];
		foreach($nodeAttributes as $attr) {
			$eventData[$attr->name] = $attr->value;
		}
		return new ParcelStatusEvent(
			timestamp: new DateTimeImmutable($eventData['event-timestamp'] ?? 'now'),
			status: ($eventData['event-status'] ?? ''),
			text: ($eventData['event-text'] ?? ''),
			ice: ($eventData['ice'] ?? ''),
			ric: ($eventData['ric'] ?? ''),
			location: ($eventData['event-location'] ?? ''),
			country: ($eventData['event-country'] ?? ''),
			standardEventCode: ($eventData['standard-event-code'] ?? ''),
			returning: ($eventData['ruecksendung'] ?? '') !== 'false',
		);
	}

	private function getTrackingUri(string $xml): string {
		$baseUri = $this->credentials->isProductionEnv
			? 'https://api-eu.dhl.com'
			: 'https://api-sandbox.dhl.com';

		return $baseUri . '/parcel/de/tracking/v0/shipments?xml=' . $xml;
	}

	private function createUnexpectedResponseException(string $responseBody): RuntimeException {
		$data = json_decode($responseBody, true);
		if(is_array($data)) {
			$message = trim((string) ($data['detail'] ?? $data['message'] ?? $data['title'] ?? ''));
			if($message !== '') {
				return new RuntimeException("DHL tracking API error: $message");
			}
		}

		return new RuntimeException("Unexpected DHL tracking response: $responseBody");
	}
}
