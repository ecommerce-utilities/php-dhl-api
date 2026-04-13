<?php

namespace Services;

use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthTokenProvider;
use EcommerceUtilities\DHL\Http\HttpClient;
use EcommerceUtilities\DHL\Services\DHLRetoureService;
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
class DHLRetoureServiceTest extends TestCase {
	#[Test]
	public function testGetRetourePdfParsesCurrentReturnsApiResponse(): void {
		$client = new RetoureQueueingHttpClient([
			new Response(200, ['Content-Type' => 'application/json'], json_encode([
				'access_token' => 'token-123',
				'token_type' => 'Bearer',
				'expires_in' => 1799,
			], JSON_THROW_ON_ERROR)),
			new Response(201, ['Content-Type' => 'application/json'], json_encode([
				'sstatus' => ['status' => 201, 'title' => 'Created'],
				'shipmentNo' => '999992901115',
				'label' => ['b64' => base64_encode('pdf-data')],
			], JSON_THROW_ON_ERROR)),
		]);

		$service = $this->createService($client, receiverId: 'deu', productionEnv: false);
		$response = $service->getRetourePdf(
			'Digital-Versand.de Online GmbH',
			null,
			null,
			'Aspenhaustraße',
			'17',
			'72770',
			'Reutlingen',
			'DE',
			'123446-B',
			null
		);

		self::assertSame('999992901115', $response->getTrackingNumber());
		self::assertSame('pdf-data', $response->getLabelData());
		self::assertCount(2, $client->requests);
		self::assertSame('https://api-sandbox.dhl.com/parcel/de/shipping/returns/v1/orders?labelType=SHIPMENT_LABEL', (string) $client->requests[1]->getUri());

		/** @var array<string, mixed> $body */
		$body = json_decode((string) $client->requests[1]->getBody(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('deu', $body['receiverId']);
		self::assertSame('SHIPMENT_LABEL', $body['returnDocumentType']);
	}

	#[Test]
	public function testGetRetourePdfSurfacesDhlErrorDetail(): void {
		$client = new RetoureQueueingHttpClient([
			new Response(200, ['Content-Type' => 'application/json'], json_encode([
				'access_token' => 'token-123',
				'token_type' => 'Bearer',
				'expires_in' => 1799,
			], JSON_THROW_ON_ERROR)),
			new Response(400, ['Content-Type' => 'application/json'], json_encode([
				'title' => 'Bad Request',
				'status' => 400,
				'detail' => 'Sorry, this recipient is not available.',
			], JSON_THROW_ON_ERROR)),
		]);

		$service = $this->createService($client, receiverId: 'RetourenWeb02', productionEnv: false);

		$this->expectException(DHLApiException::class);
		$this->expectExceptionMessage('Sorry, this recipient is not available.');

		$service->getRetourePdf(
			'Digital-Versand.de Online GmbH',
			null,
			null,
			'Aspenhaustraße',
			'17',
			'72770',
			'Reutlingen',
			'DE',
			'123446-B',
			null
		);
	}

	private function createService(ClientInterface $client, string $receiverId, bool $productionEnv): DHLRetoureService {
		$httpClient = new HttpClient(new RequestFactory(), $client, $productionEnv);
		$credentials = new DHLOAuthCredentials(
			businessPortalUsername: 'gkp-user',
			businessPortalPassword: 'gkp-password',
			key: 'api-key',
			secret: 'api-secret',
			isProductionEnv: $productionEnv,
			receiverId: $receiverId,
		);

		return new DHLRetoureService(
			new DHLOAuthTokenProvider($credentials, $httpClient),
			$credentials,
			$httpClient,
		);
	}
}

final class RetoureQueueingHttpClient implements ClientInterface {
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
