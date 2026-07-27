<?php

namespace Services;

use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Services\DHLPushSubscriptionService;
use GuzzleHttp\Psr7\Response;
use Http\Factory\Guzzle\RequestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

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

	private function createService(ClientInterface $client): DHLPushSubscriptionService {
		return new DHLPushSubscriptionService('api-key', new RequestFactory(), $client);
	}
}

final class PushSubscriptionQueueingHttpClient implements ClientInterface {
	/** @var list<RequestInterface> */
	public array $requests = [];

	/** @param list<ResponseInterface> $responses */
	public function __construct(
		private array $responses,
	) {}

	public function sendRequest(RequestInterface $request): ResponseInterface {
		$this->requests[] = $request;
		if($this->responses === []) {
			throw new RuntimeException('No queued response available');
		}

		$response = array_shift($this->responses);
		if(!$response instanceof ResponseInterface) {
			throw new RuntimeException('Invalid queued response');
		}

		return $response;
	}
}
