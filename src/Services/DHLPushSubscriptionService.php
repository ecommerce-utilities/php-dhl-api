<?php

namespace EcommerceUtilities\DHL\Services;

use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Common\DHLTools;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class DHLPushSubscriptionService {
	private const BASE_URI = 'https://api-eu.dhl.com/tracking/push/v1';

	/** @var list<string> */
	public const ALL_EVENTS = ['Transit', 'Delivered', 'Pre-Transit', 'Failure', 'Unknown'];

	public function __construct(
		private readonly string $apiKey,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly ClientInterface $client,
	) {}

	/**
	 * @param list<string> $shipmentIds
	 * @param list<string> $events
	 * @return array<mixed>
	 */
	public function createShipmentSubscription(
		string $webhookUri,
		string $service,
		array $shipmentIds,
		array $events = self::ALL_EVENTS,
	): array {
		if($webhookUri === '' || $service === '' || $shipmentIds === [] || $events === []) {
			throw new DHLApiException('Webhook URI, service, shipment IDs and events must not be empty');
		}

		return $this->sendJsonRequest(
			'POST',
			'/subscription',
			[
				'hook' => ['uri' => $webhookUri],
				'format' => 'Default',
				'type' => 'Shipment',
				'service' => $service,
				'events' => array_values($events),
				'shipmentIDs' => array_values($shipmentIds),
			],
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function activateSubscription(string $subscriptionId, string $hookSecret): array {
		if($hookSecret === '') {
			throw new DHLApiException('The DHL hook secret must not be empty');
		}

		return $this->sendJsonRequest(
			'POST',
			$this->getSubscriptionPath($subscriptionId),
			null,
			['DHL-API-Hook-Secret' => $hookSecret],
		);
	}

	/**
	 * @return array<mixed>
	 */
	public function getSubscriptions(): array {
		return $this->sendJsonRequest('GET', '/subscriptions');
	}

	/**
	 * @return array<mixed>
	 */
	public function getSubscription(string $subscriptionId): array {
		return $this->sendJsonRequest('GET', $this->getSubscriptionPath($subscriptionId));
	}

	public function deleteSubscription(string $subscriptionId): void {
		$this->sendJsonRequest('DELETE', $this->getSubscriptionPath($subscriptionId));
	}

	/**
	 * @param null|array<mixed> $body
	 * @param array<string, string> $headers
	 * @return array<mixed>
	 */
	private function sendJsonRequest(string $method, string $path, ?array $body = null, array $headers = []): array {
		$request = $this->requestFactory->createRequest($method, self::BASE_URI . $path);
		$request = $request
			->withHeader('DHL-API-Key', $this->apiKey)
			->withHeader('Accept', 'application/json');

		foreach($headers as $header => $value) {
			$request = $request->withHeader($header, $value);
		}

		if($body !== null) {
			$request = $request->withHeader('Content-Type', 'application/json');
			$request->getBody()->write(DHLTools::jsonEncode($body));
		}

		$response = $this->client->sendRequest($request);
		$this->throwForErrorResponse($response);

		$responseBody = trim($response->getBody()->getContents());
		if($responseBody === '') {
			return [];
		}

		$data = DHLTools::jsonDecode($responseBody);
		if(!is_array($data)) {
			throw new DHLApiException('Unexpected DHL push subscription response');
		}

		return $data;
	}

	private function getSubscriptionPath(string $subscriptionId): string {
		if($subscriptionId === '') {
			throw new DHLApiException('The DHL subscription ID must not be empty');
		}

		return '/subscription/' . rawurlencode($subscriptionId);
	}

	private function throwForErrorResponse(ResponseInterface $response): void {
		if($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
			return;
		}

		$responseBody = $response->getBody()->getContents();
		$data = DHLTools::jsonDecode($responseBody, default: []);
		$data = is_array($data) ? $data : [];
		$message = $data['detail']
			?? $data['message']
			?? $data['title']
			?? "DHL push subscription request failed with HTTP status {$response->getStatusCode()}";

		throw new DHLApiException((string) $message);
	}
}
