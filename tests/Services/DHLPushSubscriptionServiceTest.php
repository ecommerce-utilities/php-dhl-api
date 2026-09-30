<?php

namespace Services;

use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Common\DHLRequestValidationException;
use EcommerceUtilities\DHL\Services\DHLPushSubscriptionService;
use GuzzleHttp\Psr7\Response;
use Http\Factory\Guzzle\RequestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

#[CoversClass(DHLPushSubscriptionService::class)]
class DHLPushSubscriptionServiceTest extends TestCase {
	#[Test]
	public function testCreatesShipmentSubscriptionUsingUnifiedPushApi(): void {
		$client = new PushSubscriptionQueueingHttpClient([
			new Response(202, ['Content-Type' => 'application/json'], '{"subscriptionId":"subscription-1"}'),
		]);
		$service = $this->createService($client);

		$result = $service->createShipmentSubscription(
			'https://shop.example/dhl-tracking-push/secret',
			'post-de',
			['00340434161094000001'],
		);

		self::assertSame(['subscriptionId' => 'subscription-1'], $result);
		self::assertCount(1, $client->requests);
		$request = $client->requests[0];
		self::assertSame('POST', $request->getMethod());
		self::assertSame('https://api-eu.dhl.com/tracking/push/v1/subscription', (string) $request->getUri());
		self::assertSame('api-key', $request->getHeaderLine('DHL-API-Key'));

		/** @var array<string, mixed> $body */
		$body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('Shipment', $body['type']);
		self::assertSame('post-de', $body['service']);
		self::assertSame(['00340434161094000001'], $body['shipmentIDs']);
		self::assertSame(DHLPushSubscriptionService::ALL_EVENTS, $body['events']);
		self::assertSame(
			'https://shop.example/dhl-tracking-push/secret',
			$body['hook']['uri'],
		);
	}

	#[Test]
	public function testActivatesAndMaintainsSubscription(): void {
		$client = new PushSubscriptionQueueingHttpClient([
			new Response(204),
			new Response(200, [], '{"id":"subscription-1"}'),
			new Response(200, [], '[{"id":"subscription-1"}]'),
			new Response(204),
		]);
		$service = $this->createService($client);

		self::assertSame([], $service->activateSubscription('subscription-1', 'hook-secret'));
		self::assertSame(['id' => 'subscription-1'], $service->getSubscription('subscription-1'));
		self::assertSame([['id' => 'subscription-1']], $service->getSubscriptions());
		$service->deleteSubscription('subscription-1');

		self::assertSame('hook-secret', $client->requests[0]->getHeaderLine('DHL-API-Hook-Secret'));
		self::assertSame(
			'https://api-eu.dhl.com/tracking/push/v1/subscription/subscription-1',
			(string) $client->requests[1]->getUri(),
		);
		self::assertSame(
			'https://api-eu.dhl.com/tracking/push/v1/subscriptions',
			(string) $client->requests[2]->getUri(),
		);
		self::assertSame('DELETE', $client->requests[3]->getMethod());
	}

	#[Test]
	public function testSurfacesDhlErrorDetail(): void {
		$client = new PushSubscriptionQueueingHttpClient([
			new Response(400, ['Content-Type' => 'application/json'], '{"detail":"Unknown service"}'),
		]);
		$service = $this->createService($client);

		$this->expectException(DHLApiException::class);
		$this->expectExceptionMessage('Unknown service');

		$service->getSubscriptions();
	}

	#[Test]
	public function testAcceptsTenShipmentIdsInOneRequest(): void {
		$client = new PushSubscriptionQueueingHttpClient([new Response(201, [], '{"self":"subscription-1"}')]);
		$shipmentIds = $this->createShipmentIds(10);

		$result = $this->createService($client)->createShipmentSubscription('https://shop.example/push', 'parcel-de', $shipmentIds);

		self::assertSame(10, DHLPushSubscriptionService::MAX_SHIPMENT_IDS_PER_REQUEST);
		self::assertSame(['self' => 'subscription-1'], $result);
		self::assertCount(1, $client->requests);
		self::assertSame($shipmentIds, $this->requestBody($client->requests[0])['shipmentIDs']);
	}

	#[Test]
	public function testRejectsElevenShipmentIdsWithoutSendingARequest(): void {
		$client = new PushSubscriptionQueueingHttpClient([]);
		try {
			$this->createService($client)->createShipmentSubscription('https://shop.example/push', 'parcel-de', $this->createShipmentIds(11));
			self::fail('Expected local validation failure');
		} catch(DHLRequestValidationException $error) {
			self::assertStringContainsString('at most 10 shipment IDs', $error->getMessage());
			self::assertTrue($error->definiteRejection);
		}
		self::assertSame([], $client->requests);
	}

	#[Test]
	#[DataProvider('batchSizes')]
	public function testBatchesShipmentIdsIntoMaximumSizedRequests(int $count, array $expectedBatchSizes): void {
		$responses = [];
		foreach($expectedBatchSizes as $index => $size) {
			$responses[] = new Response(201, [], json_encode(['self' => 'subscription-' . $index], JSON_THROW_ON_ERROR));
		}
		$client = new PushSubscriptionQueueingHttpClient($responses);
		$shipmentIds = $this->createShipmentIds($count);
		$results = $this->createService($client)->createShipmentSubscriptions(
			'https://shop.example/push', 'parcel-de', $shipmentIds, ['Transit'],
		);
		self::assertSame([], $client->requests);

		$allSubmittedIds = [];
		foreach($results as $index => $result) {
			self::assertCount($index + 1, $client->requests);
			self::assertCount($expectedBatchSizes[$index], $result['shipmentIds']);
			self::assertSame(['self' => 'subscription-' . $index], $result['response']);
			$request = $client->requests[$index];
			$body = $this->requestBody($request);
			self::assertSame('POST', $request->getMethod());
			self::assertSame('https://api-eu.dhl.com/tracking/push/v1/subscription', (string) $request->getUri());
			self::assertSame('api-key', $request->getHeaderLine('DHL-API-Key'));
			self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
			self::assertSame('Shipment', $body['type']);
			self::assertSame('Default', $body['format']);
			self::assertSame('parcel-de', $body['service']);
			self::assertSame(['uri' => 'https://shop.example/push'], $body['hook']);
			self::assertSame(['Transit'], $body['events']);
			self::assertSame($result['shipmentIds'], $body['shipmentIDs']);
			$allSubmittedIds = array_merge($allSubmittedIds, $result['shipmentIds']);
		}
		self::assertCount(count($expectedBatchSizes), $client->requests);
		self::assertSame($shipmentIds, $allSubmittedIds);
	}

	/** @return iterable<string, array{int, list<int>}> */
	public static function batchSizes(): iterable {
		yield 'empty list' => [0, []];
		yield 'one shipment' => [1, [1]];
		yield 'exact limit' => [10, [10]];
		yield 'one over limit' => [11, [10, 1]];
		yield 'exact multiple' => [20, [10, 10]];
		yield 'partial final batch' => [25, [10, 10, 5]];
	}

	#[Test]
	public function testStoppingIterationDoesNotSendRemainingBatches(): void {
		$client = new PushSubscriptionQueueingHttpClient([new Response(201, [], '{"self":"subscription-1"}')]);
		foreach($this->createService($client)->createShipmentSubscriptions('https://shop.example/push', 'parcel-de', $this->createShipmentIds(25)) as $result) {
			self::assertCount(10, $result['shipmentIds']);
			break;
		}
		self::assertCount(1, $client->requests);
	}

	#[Test]
	public function testRejectsInvalidIdsInLaterBatchesBeforeSendingAnyRequest(): void {
		foreach(['', '   ', 123456, null] as $invalidId) {
			$client = new PushSubscriptionQueueingHttpClient([]);
			$shipmentIds = [...$this->createShipmentIds(10), $invalidId];
			try {
				iterator_to_array($this->createService($client)->createShipmentSubscriptions('https://shop.example/push', 'parcel-de', $shipmentIds));
				self::fail('Expected local validation failure');
			} catch(DHLRequestValidationException $error) {
				self::assertSame('Shipment IDs must be non-empty strings', $error->getMessage());
				self::assertTrue($error->definiteRejection);
			}
			self::assertSame([], $client->requests);
		}
	}

	#[Test]
	public function testRejectsMissingRequiredInputWithoutSendingRequests(): void {
		foreach([
			['', 'parcel-de', ['0001'], ['Transit']],
			['https://shop.example/push', '', ['0001'], ['Transit']],
			['https://shop.example/push', 'parcel-de', ['0001'], []],
		] as [$webhookUri, $provider, $shipmentIds, $events]) {
			$client = new PushSubscriptionQueueingHttpClient([]);
			$service = $this->createService($client);
			foreach(['createShipmentSubscription', 'createShipmentSubscriptions'] as $method) {
				try {
					$result = $service->$method($webhookUri, $provider, $shipmentIds, $events);
					if($result instanceof \Generator) {
						iterator_to_array($result);
					}
					self::fail('Expected local validation failure');
				} catch(DHLRequestValidationException $error) {
					self::assertTrue($error->definiteRejection);
				}
			}
			self::assertSame([], $client->requests);
		}
	}

	#[Test]
	public function testKeepsCompletedResultsAndStopsWithoutRetryingAfterAnError(): void {
		foreach([
			new Response(429, [], '{"detail":"Daily quota exceeded"}'),
			new RuntimeException('Synthetic transport failure'),
		] as $failure) {
			$client = new PushSubscriptionQueueingHttpClient([
				new Response(201, [], '{"self":"subscription-1"}'),
				$failure,
				new Response(201, [], '{"self":"subscription-3"}'),
			]);
			$completed = [];
			try {
				foreach($this->createService($client)->createShipmentSubscriptions('https://shop.example/push', 'parcel-de', $this->createShipmentIds(25)) as $result) {
					$completed[] = $result;
				}
				self::fail('Expected request failure');
			} catch(RuntimeException $error) {
				self::assertSame(
					$failure instanceof ResponseInterface ? 'Daily quota exceeded' : 'Synthetic transport failure',
					$error->getMessage(),
				);
			}
			self::assertCount(2, $client->requests);
			self::assertSame([
				['shipmentIds' => $this->createShipmentIds(10), 'response' => ['self' => 'subscription-1']],
			], $completed);
		}
	}

	/** @return list<string> */
	private function createShipmentIds(int $count): array {
		$ids = [];
		for($index = 1; $index <= $count; $index++) {
			$ids[] = sprintf('00340434161094%06d', $index);
		}
		return $ids;
	}

	/** @return array<string, mixed> */
	private function requestBody(RequestInterface $request): array {
		return json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
	}

	private function createService(ClientInterface $client): DHLPushSubscriptionService {
		return new DHLPushSubscriptionService('api-key', new RequestFactory(), $client);
	}
}

final class PushSubscriptionQueueingHttpClient implements ClientInterface {
	/** @var list<RequestInterface> */
	public array $requests = [];

	/** @param list<ResponseInterface|Throwable> $responses */
	public function __construct(
		private array $responses,
	) {}

	public function sendRequest(RequestInterface $request): ResponseInterface {
		$this->requests[] = $request;
		if($this->responses === []) {
			throw new RuntimeException('No queued response available');
		}

		$response = array_shift($this->responses);
		if($response instanceof Throwable) {
			throw $response;
		}
		if(!$response instanceof ResponseInterface) {
			throw new RuntimeException('Invalid queued response');
		}

		return $response;
	}
}
