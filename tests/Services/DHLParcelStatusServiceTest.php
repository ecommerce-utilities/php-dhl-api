<?php

namespace Services;

use DateTimeImmutable;
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\Services\DHLParcelStatusService;
use GuzzleHttp\Psr7\Response;
use Http\Factory\Guzzle\RequestFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

#[CoversNothing]
class DHLParcelStatusServiceTest extends TestCase {
	#[Test]
	public function testGetStatusUsesRequiredBasicAuthFlowFromOauthCredentials(): void {
		$client = new ParcelStatusQueueingHttpClient([
			new Response(200, ['Content-Type' => 'application/xml'], <<<XML
<data>
  <data name="piece-shipment-list">
    <data
      name="piece-shipment"
      piece-code="549941469494"
      piece-id="piece-1"
      piece-status-desc="Delivered"
      standard-event-code="D-DELIVERED"
      ice="PARCV"
      ric="R"
      division="D"
      dest-country="DE"
      origin-country="DE"
      product-code="V01PAK"
      product-name="DHL Paket"
    />
  </data>
</data>
XML),
		]);

		$service = new DHLParcelStatusService(
			credentials: new DHLOAuthCredentials(
				businessPortalUsername: 'gkp-user',
				businessPortalPassword: 'gkp-password',
				key: 'api-key',
				secret: 'api-secret',
				isProductionEnv: false,
			),
			requestFactory: new RequestFactory(),
			client: $client,
		);

		$result = $service->getStatus(
			[['code' => '549941469494', 'reference' => 'ref-1']],
			new DateTimeImmutable('-1 day'),
			new DateTimeImmutable(),
		);

		self::assertCount(1, $result);
		self::assertSame('ref-1', $result[0]->reference);
		self::assertSame('549941469494', $result[0]->pieceCode);
		self::assertCount(1, $client->requests);
		self::assertStringStartsWith(
			'https://api-sandbox.dhl.com/parcel/de/tracking/v0/shipments?xml=',
			(string) $client->requests[0]->getUri()
		);
		self::assertSame(
			'Basic ' . base64_encode('api-key:api-secret'),
			$client->requests[0]->getHeaderLine('Authorization')
		);
		self::assertSame('api-key', $client->requests[0]->getHeaderLine('DHL-API-Key'));
		self::assertStringContainsString('appname="gkp-user"', urldecode((string) $client->requests[0]->getUri()));
		self::assertStringContainsString('password="gkp-password"', urldecode((string) $client->requests[0]->getUri()));
	}

	#[Test]
	public function testGetStatusSurfacesJsonAuthenticationErrors(): void {
		$client = new ParcelStatusQueueingHttpClient([
			new Response(401, ['Content-Type' => 'application/problem+json'], json_encode([
				'status' => 401,
				'title' => 'Unauthorized',
				'detail' => 'Invalid credentials: Please use Basic Auth and possibly ZT-Kennung in the request.',
			], JSON_THROW_ON_ERROR)),
		]);

		$service = new DHLParcelStatusService(
			credentials: new DHLOAuthCredentials(
				businessPortalUsername: 'gkp-user',
				businessPortalPassword: 'gkp-password',
				key: 'api-key',
				secret: 'api-secret',
				isProductionEnv: false,
			),
			requestFactory: new RequestFactory(),
			client: $client,
		);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('DHL tracking API error: Invalid credentials: Please use Basic Auth and possibly ZT-Kennung in the request.');

		$service->getStatus(
			[['code' => '549941469494']],
			new DateTimeImmutable('-1 day'),
			new DateTimeImmutable(),
		);
	}
}

final class ParcelStatusQueueingHttpClient implements ClientInterface {
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
