<?php

namespace Services;

use EcommerceUtilities\DHL\DHLServices;
use EcommerceUtilities\DHL\Common\DHLBusinessPortalCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLCashOnDeliveryService;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLNamedPersonOnly;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRecipientAddressPackstation;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRecipientAddressPostfiliale;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRecipientAddressPostal;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentRequest;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShipmentSenderAddress;
use EcommerceUtilities\DHL\Services\DHLShipmentService\DHLShippingService;
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
class DHLShipmentServiceTest extends TestCase {
	#[Test]
	public function testCreateDomesticLabelWithCashOnDeliveryAndNamedPersonOnly(): void {
		$client = new QueueingHttpClient([
			new Response(200, ['Content-Type' => 'application/json'], json_encode([
				'access_token' => 'token-123',
				'token_type' => 'Bearer',
				'expires_in' => 1799,
			], JSON_THROW_ON_ERROR)),
			new Response(200, ['Content-Type' => 'application/json'], json_encode([
				'status' => ['status' => 200, 'title' => 'OK'],
				'items' => [[
					'shipmentNo' => '00340434161094096225',
					'routingCode' => '40327653113+99000943058020',
					'sstatus' => ['status' => 200, 'title' => 'OK'],
					'label' => [
						'b64' => base64_encode('pdf-data'),
						'fileFormat' => 'PDF',
					],
					'codLabel' => [
						'b64' => base64_encode('cod-pdf-data'),
						'fileFormat' => 'PDF',
					],
				]],
			], JSON_THROW_ON_ERROR)),
		]);

		$services = $this->createDhlServices($client, productionEnv: false);
		$response = $services->getShipmentService()->createLabel(
			new DHLShippingService(
				myCountryId: 'DE',
				productKeyNational: 'V01PAK',
				productKeyInternational: 'V53WPAK',
				billingNumberNational: '33333333330102',
				billingNumberInternational: '33333333335301',
			),
			new DHLShipmentRequest(
				reference: 'ABC12345',
				senderAddress: new DHLShipmentSenderAddress(
					company: 'Musterfirma GmbH',
					street: 'Musterstraße',
					houseNumber: '123',
					zip: '12345',
					city: 'Musterstadt',
					countryCode: 'DE',
					mail: 'shipment-at-example.org',
				),
				recipientAddress: new DHLShipmentRecipientAddressPostal(
					company: 'Musterfirma GmbH',
					firstname: 'Max',
					lastname: 'Mustermann',
					street: 'Musterstraße',
					houseNumber: '123',
					addressAddition: '2. OG',
					zip: '12345',
					city: 'Musterstadt',
					state: null,
					countryCode: 'DE',
				),
				email: 'max.mustermann@example.com',
				phone: '+49 171 123456',
				weight: 1.25,
				services: [
					new DHLCashOnDeliveryService(
						amount: 19.95,
						accountOwner: '',
						bankIban: 'DE01234567800001234567',
						bankBic: 'DEUTDEFFXXX',
						bankName: 'Deutsche Bank',
						reference: 'ORDER-123',
						reference2: 'INV-456',
					),
					new DHLNamedPersonOnly(),
				],
			),
		);

		self::assertSame('00340434161094096225', $response->getTrackingNumber());
		self::assertSame('pdf-data', $response->getLabelData());
		self::assertSame('cod-pdf-data', $response->getCodLabelData());
		self::assertSame('40327653113+99000943058020', $response->getRoutingCode());

		self::assertCount(2, $client->requests);

		$authRequest = $client->requests[0];
		self::assertSame('POST', $authRequest->getMethod());
		self::assertSame('https://api-sandbox.dhl.com/parcel/de/account/auth/ropc/v1/token', (string) $authRequest->getUri());
		parse_str((string) $authRequest->getBody(), $authBody);
		self::assertSame([
			'grant_type' => 'password',
			'username' => 'gkp-user',
			'password' => 'gkp-password',
			'client_id' => 'api-key',
			'client_secret' => 'api-secret',
		], $authBody);

		$shipmentRequest = $client->requests[1];
		self::assertSame('POST', $shipmentRequest->getMethod());
		self::assertSame('Bearer token-123', $shipmentRequest->getHeaderLine('Authorization'));
		self::assertSame(
			'https://api-sandbox.dhl.com/parcel/de/shipping/v2/orders?includeDocs=include&docFormat=PDF&printFormat=910-300-600',
			(string) $shipmentRequest->getUri()
		);

		/** @var array{profile: string, shipments: list<array<string, mixed>>} $body */
		$body = json_decode((string) $shipmentRequest->getBody(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('STANDARD_GRUPPENPROFIL', $body['profile']);
		self::assertSame('V01PAK', $body['shipments'][0]['product']);
		self::assertSame('33333333330102', $body['shipments'][0]['billingNumber']);
		self::assertSame('DEU', $body['shipments'][0]['shipper']['country']);
		self::assertSame('DEU', $body['shipments'][0]['consignee']['country']);
		self::assertSame('Max Mustermann', $body['shipments'][0]['consignee']['name2']);
		self::assertSame(1.25, $body['shipments'][0]['details']['weight']['value']);
		self::assertArrayHasKey('cashOnDelivery', $body['shipments'][0]['services']);
		self::assertTrue($body['shipments'][0]['services']['namedPersonOnly']);
		self::assertArrayNotHasKey('phone', $body['shipments'][0]['consignee']);
	}

	#[Test]
	public function testCreatePackstationLabel(): void {
		$client = new QueueingHttpClient([
			new Response(200, ['Content-Type' => 'application/json'], json_encode([
				'access_token' => 'token-123',
				'token_type' => 'Bearer',
				'expires_in' => 1799,
			], JSON_THROW_ON_ERROR)),
			new Response(200, ['Content-Type' => 'application/json'], json_encode([
				'status' => ['status' => 200, 'title' => 'OK'],
				'items' => [[
					'shipmentNo' => '00340434161094096225',
					'sstatus' => ['status' => 200, 'title' => 'OK'],
					'label' => ['b64' => base64_encode('packstation-pdf')],
				]],
			], JSON_THROW_ON_ERROR)),
		]);

		$services = $this->createDhlServices($client, productionEnv: false);
		$services->getShipmentService()->createLabel(
			new DHLShippingService(
				myCountryId: 'DE',
				productKeyNational: 'V01PAK',
				productKeyInternational: 'V53WPAK',
				billingNumberNational: '33333333330102',
				billingNumberInternational: '33333333335301',
			),
			new DHLShipmentRequest(
				reference: 'ABC12345',
				senderAddress: new DHLShipmentSenderAddress(
					company: 'Musterfirma GmbH',
					street: 'Musterstraße',
					houseNumber: '123',
					zip: '12345',
					city: 'Musterstadt',
					countryCode: 'DE',
					mail: 'shipment-at-example.org',
				),
				recipientAddress: new DHLShipmentRecipientAddressPackstation(
					firstname: 'Max',
					lastname: 'Mustermann',
					customerNumber: '1182271787',
					packstationNumber: '260',
					zip: '22303',
					city: 'Hamburg',
					state: null,
					countryCode: 'DE',
				),
				email: null,
				phone: null,
				weight: 1.0,
			),
		);

		/** @var array{profile: string, shipments: list<array<string, mixed>>} $body */
		$body = json_decode((string) $client->requests[1]->getBody(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame([
			'name' => 'Max Mustermann',
			'lockerID' => 260,
			'postNumber' => '1182271787',
			'postalCode' => '22303',
			'city' => 'Hamburg',
			'country' => 'DEU',
		], $body['shipments'][0]['consignee']);
	}

	#[Test]
	public function testCreatePostfilialeLabel(): void {
		$client = new QueueingHttpClient([
			new Response(200, ['Content-Type' => 'application/json'], json_encode([
				'access_token' => 'token-123',
				'token_type' => 'Bearer',
				'expires_in' => 1799,
			], JSON_THROW_ON_ERROR)),
			new Response(200, ['Content-Type' => 'application/json'], json_encode([
				'status' => ['status' => 200, 'title' => 'OK'],
				'items' => [[
					'shipmentNo' => '00340434161094096225',
					'sstatus' => ['status' => 200, 'title' => 'OK'],
					'label' => ['b64' => base64_encode('filiale-pdf')],
				]],
			], JSON_THROW_ON_ERROR)),
		]);

		$services = $this->createDhlServices($client, productionEnv: true);
		$services->getShipmentService()->createLabel(
			new DHLShippingService(
				myCountryId: 'DE',
				productKeyNational: 'V01PAK',
				productKeyInternational: 'V53WPAK',
				billingNumberNational: '33333333330102',
				billingNumberInternational: '33333333335301',
				acceptLanguage: 'de-DE',
			),
			new DHLShipmentRequest(
				reference: 'ABC12345',
				senderAddress: new DHLShipmentSenderAddress(
					company: 'Musterfirma GmbH',
					street: 'Musterstraße',
					houseNumber: '123',
					zip: '12345',
					city: 'Musterstadt',
					countryCode: 'DE',
					mail: 'shipment-at-example.org',
				),
				recipientAddress: new DHLShipmentRecipientAddressPostfiliale(
					firstname: 'Max',
					lastname: 'Mustermann',
					customerNumber: '1182271787',
					postfilialNumber: '518',
					zip: '22303',
					city: 'Hamburg',
					state: null,
					countryCode: 'DE',
				),
				email: 'max.mustermann@example.com',
				phone: null,
				weight: 1.0,
			),
		);

		self::assertSame('https://api-eu.dhl.com/parcel/de/account/auth/ropc/v1/token', (string) $client->requests[0]->getUri());
		self::assertSame('de-DE', $client->requests[1]->getHeaderLine('Accept-Language'));

		/** @var array{profile: string, shipments: list<array<string, mixed>>} $body */
		$body = json_decode((string) $client->requests[1]->getBody(), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame([
			'name' => 'Max Mustermann',
			'retailID' => 518,
			'postNumber' => '1182271787',
			'postalCode' => '22303',
			'city' => 'Hamburg',
			'country' => 'DEU',
			'email' => 'max.mustermann@example.com',
		], $body['shipments'][0]['consignee']);
	}

	private function createDhlServices(ClientInterface $client, bool $productionEnv): DHLServices {
		return new DHLServices(
			new DHLOAuthCredentials('api-key', 'api-secret'),
			new DHLBusinessPortalCredentials($productionEnv, 'gkp-user', 'gkp-password'),
			new RequestFactory(),
			$client,
		);
	}
}

final class QueueingHttpClient implements ClientInterface {
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
