<?php

namespace EcommerceUtilities\DHL\Tests;

use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Common\DHLOAuthCredentials;
use EcommerceUtilities\DHL\Common\DHLOAuthTokenProvider;
use EcommerceUtilities\DHL\Common\DHLRequestValidationException;
use EcommerceUtilities\DHL\Http\DHLHttpClient;
use EcommerceUtilities\DHL\Services\DHLShipmentService;
use EcommerceUtilities\DHL\Services\DHLShipmentService\{DHLCashOnDeliveryService, DHLNamedPersonOnly,
	DHLShipmentRecipientAddress, DHLShipmentRecipientAddressPostal, DHLShipmentRecipientAddressPackstation,
	DHLShipmentRecipientAddressPostfiliale, DHLShipmentRequest, DHLShipmentSenderAddress, DHLShippingServiceConfiguration};
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DHLShipmentSafetyTest extends TestCase {
	private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";

	public function testPostalNamesPreserveMaximumUnicodeFields(): void {
		foreach(['', str_repeat('Ü', 50)] as $company) {
			$history = [];
			$this->api([$this->auth(), $this->success()], $history)->createLabel($this->config(), $this->request($this->postal($company, str_repeat('Ä', 50), str_repeat('Ö', 50))));
			$names = array_intersect_key($this->body($history)['shipments'][0]['consignee'], array_flip(['name1','name2','name3']));
			self::assertSame(array_values(array_filter([$company, str_repeat('Ä', 50), str_repeat('Ö', 50)])), array_values($names));
		}
	}

	public function testDomesticAddressAdditionUsesVisibleNameLine(): void {
		$history = [];
		$this->api([$this->auth(), $this->success()], $history)->createLabel($this->config(), $this->request($this->postal('Firma GmbH', 'Max', 'Muster', 'c/o Werkstatt')));
		$address = $this->body($history)['shipments'][0]['consignee'];
		self::assertSame('Firma GmbH', $address['name1']);
		self::assertSame('Max Muster', $address['name2']);
		self::assertSame('c/o Werkstatt', $address['name3']);
		self::assertArrayNotHasKey('additionalAddressInformation1', $address);
	}

	public function testAddressAdditionCanUseRemainingSpaceWithoutLosingNames(): void {
		$history = [];
		$this->api([$this->auth(), $this->success()], $history)->createLabel($this->config(), $this->request($this->postal('Firma', str_repeat('A', 30), str_repeat('B', 30), 'c/o Müller')));
		self::assertSame(str_repeat('B', 30) . ' c/o Müller', $this->body($history)['shipments'][0]['consignee']['name3']);
		$this->assertLocalFailure($this->request($this->postal('Firma', str_repeat('A', 50), str_repeat('B', 50), 'c/o Müller')), 'drei DHL-Namenszeilen');
	}

	public function testInternationalAdditionKeepsItsSeparateSixtyCharacterField(): void {
		$history = [];
		$this->api([$this->auth(), $this->success()], $history)->createLabel($this->config(), $this->request($this->postal('', 'Max', 'Muster', str_repeat('Ä', 60), 'AT')));
		self::assertSame(str_repeat('Ä', 60), $this->body($history)['shipments'][0]['consignee']['additionalAddressInformation1']);
	}

	public function testOverlongPostalAndPickupNamesAreRejectedLocally(): void {
		$this->assertLocalFailure($this->request($this->postal('', str_repeat('Ä', 51), 'Muster')), 'Vor- oder Nachname');
		foreach([
			new DHLShipmentRecipientAddressPackstation(str_repeat('Ä', 50), str_repeat('Ö', 50), '123456789', '123', '12345', 'Teststadt', null, 'DE'),
			new DHLShipmentRecipientAddressPostfiliale(str_repeat('Ä', 50), str_repeat('Ö', 50), '123456789', '401', '12345', 'Teststadt', null, 'DE'),
		] as $address) {
			$this->assertLocalFailure($this->request($address), 'Vollständiger Name');
		}
	}

	public function testOptionalReferenceAndExactAllowedLengths(): void {
		foreach(['', '00000000', str_repeat('Ä', 35)] as $reference) {
			$history = [];
			$this->api([$this->auth(), $this->success()], $history)->createLabel($this->config(), $this->request(reference: $reference));
			$shipment = $this->body($history)['shipments'][0];
			if($reference === '') {
				self::assertArrayNotHasKey('refNo', $shipment);
			} else {
				self::assertSame($reference, $shipment['refNo']);
			}
		}
		foreach(['0', '1234567', str_repeat('A', 36)] as $reference) {
			$this->assertLocalFailure($this->request(reference: $reference), 'Sendungsreferenz');
		}
	}

	public function testPickupNumbersAndCountryFollowDhlSchema(): void {
		foreach(['100', '999'] as $id) {
			$history = [];
			$address = new DHLShipmentRecipientAddressPackstation('Max', 'Muster', '123456', $id, '12345', 'Teststadt', null, 'DE');
			$this->api([$this->auth(), $this->success()], $history)->createLabel($this->config(), $this->request($address));
			self::assertSame((int) $id, $this->body($history)['shipments'][0]['consignee']['lockerID']);
		}
		foreach([
			['099','123456','DE'], ['1000','123456','DE'], ['123','12345','DE'], ['123','12345678901','DE'], ['123','123456','AT'],
		] as [$id, $postNumber, $country]) {
			$address = new DHLShipmentRecipientAddressPackstation('Max','Muster',$postNumber,$id,'12345','Teststadt',null,$country);
			$this->assertLocalFailure($this->request($address));
		}
		$this->assertLocalFailure($this->request(new DHLShipmentRecipientAddressPostfiliale('Max','Muster','123456','400','12345','Teststadt',null,'DE')), 'Postfilialnummer');
		foreach(['401','999'] as $id) {
			$history = [];
			$address = new DHLShipmentRecipientAddressPostfiliale('Max','Muster','',$id,'12345','Teststadt',null,'DE');
			$this->api([$this->auth(), $this->success()], $history)->createLabel($this->config(), $this->request($address, email: 'customer@example.test'));
			$consignee = $this->body($history)['shipments'][0]['consignee'];
			self::assertSame((int)$id, $consignee['retailID']);
			self::assertArrayNotHasKey('postNumber', $consignee);
			self::assertSame('customer@example.test', $consignee['email']);
		}
	}

	public function testCodAndNamedServicesPreserveMoneyBankDataAndTransferNotes(): void {
		$cod = new DHLCashOnDeliveryService(1234.56,'Firma GmbH','DE89370400440532013000','COBADEFFXXX','Testbank',str_repeat('Ä',35),str_repeat('Ö',35));
		$history = [];
		$this->api([$this->auth(), $this->success()], $history)->createLabel($this->config(), $this->request(services: [$cod,new DHLNamedPersonOnly()]));
		$services = $this->body($history)['shipments'][0]['services'];
		self::assertSame(['currency'=>'EUR','value'=>1234.56], $services['cashOnDelivery']['amount']);
		self::assertSame(['accountHolder'=>'Firma GmbH','bankName'=>'Testbank','iban'=>'DE89370400440532013000','bic'=>'COBADEFFXXX'], $services['cashOnDelivery']['bankAccount']);
		self::assertSame(str_repeat('Ä',35), $services['cashOnDelivery']['transferNote1']);
		self::assertSame(str_repeat('Ö',35), $services['cashOnDelivery']['transferNote2']);
		self::assertTrue($services['namedPersonOnly']);
		foreach([[str_repeat('A',36),''],['',str_repeat('A',36)]] as [$first,$second]) {
			$this->assertLocalFailure($this->request(services:[new DHLCashOnDeliveryService(1.0,'Firma','IBAN','BIC','Bank',$first,$second)]), 'Nachnahme-Verwendungszweck');
		}
	}

	public function testWarningsAreReturnedAlongsideBothSuccessfulDocuments(): void {
		$history = [];
		$api = $this->api([$this->auth(), $this->success(warnings: true, cod: true)], $history);
		$out = $api->createLabel($this->config(), $this->request());
		self::assertSame(self::PDF, $out->getLabelData());
		self::assertSame(self::PDF, $out->getCodLabelData());
		self::assertSame('00340434161094096225', $out->getTrackingNumber());
		self::assertSame(['services.goGreenPlus: GoGreen Plus wird automatisch hinzugebucht.'], $out->getWarnings());
		self::assertCount(1, $out->getData()->validationMessages);
		self::assertArrayNotHasKey('services', $this->body($history)['shipments'][0]);
	}

	public function testHttpFailuresRetainDetailsAndConservativeOutcome(): void {
		foreach([400=>true,401=>true,403=>true,404=>true,422=>true,429=>true,408=>false,409=>false,302=>false,500=>false,503=>false] as $status=>$definite) {
			$history = [];
			$data = ['status'=>['status'=>$status,'title'=>'DHL request failed'],'items'=>[['validationMessages'=>[['property'=>'consignee.postalCode','validationMessage'=>'Ungültige PLZ','validationState'=>'Error']]]]];
			$api = $this->api([$this->auth(),new Response($status,[],json_encode($data,JSON_THROW_ON_ERROR))], $history);
			try {
				$api->createLabel($this->config(), $this->request());
				self::fail('Expected failure');
			} catch(DHLApiException $e) {
				self::assertSame($status,$e->httpStatus);
				self::assertSame($definite,$e->definiteRejection);
				self::assertSame(['consignee.postalCode: Ungültige PLZ'],$e->details);
				self::assertCount(2,$history);
			}
		}
	}

	public function testAuthenticationFailuresHappenBeforeShipmentPost(): void {
		foreach([new Response(401,[], '{"error_description":"Invalid credentials"}'), new Response(503,[], '{"message":"Unavailable"}'), new Response(200,[], '{'), new Response(200,[], '{"token_type":"Bearer","access_token":""}') ] as $response) {
			$history = [];
			$api = $this->api([$response,$this->success()],$history);
			try {
				$api->createLabel($this->config(),$this->request());
				self::fail('Expected auth failure');
			} catch(DHLApiException $e) {
				self::assertTrue($e->definiteRejection);
				self::assertSame($response->getStatusCode(),$e->httpStatus);
				self::assertCount(1,$history);
			}
		}
	}

	public function testTransportFailureIsSafeOnlyBeforeShipmentPost(): void {
		foreach([true,false] as $duringAuth) {
			$timeout = new ConnectException('Synthetic timeout', new Request('POST','https://example.test'));
			$history=[];
			$api=$this->api($duringAuth ? [$timeout] : [$this->auth(),$timeout],$history);
			try {
				$api->createLabel($this->config(),$this->request());
				self::fail('Expected transport failure');
			} catch(DHLApiException $e) {
				self::assertSame($duringAuth,$e->definiteRejection);
				self::assertNull($e->httpStatus);
				self::assertCount($duringAuth ? 1 : 2,$history);
			}
		}
	}

	public function testUnusableResponsesAreUncertainAndNeverRetried(): void {
		$valid = ['sstatus'=>['status'=>200],'shipmentNo'=>'12345','label'=>['b64'=>base64_encode(self::PDF)]];
		$bodies = ['{','null','{}'];
		foreach([
			['shipmentNo'=>''], ['shipmentNo'=>'../../file'], ['shipmentNo'=>str_repeat('A',51)],
			['label'=>['b64'=>'invalid!']], ['label'=>['b64'=>' ']], ['codLabel'=>['b64'=>'invalid!']], ['sstatus'=>null],
		] as $changes) {
			$bodies[]=json_encode(['items'=>[array_replace($valid,$changes)]],JSON_THROW_ON_ERROR);
		}
		foreach($bodies as $body) {
			$history=[];
			$api=$this->api([$this->auth(),new Response(200,[],$body)],$history);
			try {
				$api->createLabel($this->config(),$this->request());
				self::fail('Expected unusable response');
			} catch(DHLApiException $e) {
				self::assertFalse($e->definiteRejection);
				self::assertSame(200,$e->httpStatus);
				self::assertCount(2,$history);
			}
		}
	}

	public function testValidationOnlyUsesSameBodyButNeverRequiresDocuments(): void {
		$createdHistory=[];
		$this->api([$this->auth(),$this->success()],$createdHistory)->createLabel($this->config(),$this->request());
		$history=[];
		$reply=new Response(200,[],json_encode(['items'=>[['sstatus'=>['status'=>200],'validationMessages'=>[['property'=>'services.goGreenPlus','validationMessage'=>'GoGreen Plus wird automatisch hinzugebucht.','validationState'=>'Warning']]]]],JSON_THROW_ON_ERROR));
		$out=$this->api([$this->auth(),$reply],$history)->validateShipment($this->config(),$this->request());
		self::assertSame($this->body($createdHistory),$this->body($history));
		parse_str($history[1]['request']->getUri()->getQuery(),$query);
		self::assertSame('true',$query['validate']);
		self::assertSame(['services.goGreenPlus: GoGreen Plus wird automatisch hinzugebucht.'],$out->getWarnings());
		self::assertFalse(isset($out->getData()->label));
	}

	private function assertLocalFailure(DHLShipmentRequest $request,string $message=''): void {
		$history=[];
		try {
			$this->api([$this->auth(),$this->success()],$history)->createLabel($this->config(),$request);
			self::fail('Expected local request rejection');
		} catch(DHLRequestValidationException $e) {
			self::assertTrue($e->definiteRejection);
			self::assertSame([],$history);
			if($message!=='') { self::assertStringContainsString($message,$e->getMessage()); }
		}
	}

	private function api(array $responses,array &$history): DHLShipmentService {
		$handler=HandlerStack::create(new MockHandler($responses));
		$handler->push(Middleware::history($history));
		$client=new DHLHttpClient(new HttpFactory(),new Client(['handler'=>$handler,'connect_timeout'=>5,'timeout'=>30]),false);
		return new DHLShipmentService(new DHLOAuthTokenProvider(new DHLOAuthCredentials('user','password','key','secret',false),$client),$client);
	}
	private function body(array $history): array { return json_decode((string)$history[1]['request']->getBody(),true,512,JSON_THROW_ON_ERROR); }
	private function config(): DHLShippingServiceConfiguration { return new DHLShippingServiceConfiguration('DE','V01PAK','V53WPAK','33333333330102','33333333335301'); }
	private function auth(): Response { return new Response(200,[], '{"token_type":"Bearer","access_token":"synthetic-token","expires_in":300}'); }
	private function success(bool $warnings=false,bool $cod=false): Response {
		$item=['sstatus'=>['status'=>200],'shipmentNo'=>'00340434161094096225','label'=>['b64'=>base64_encode(self::PDF),'fileFormat'=>'PDF']];
		if($warnings) { $item['validationMessages']=[['property'=>'services.goGreenPlus','validationMessage'=>'GoGreen Plus wird automatisch hinzugebucht.','validationState'=>'Warning']]; }
		if($cod) { $item['codLabel']=['b64'=>base64_encode(self::PDF),'fileFormat'=>'PDF','printFormat'=>'A4']; }
		return new Response(200,[],json_encode(['items'=>[$item]],JSON_THROW_ON_ERROR));
	}
	private function postal(string $company='',string $first='Max',string $last='Muster',string $addition='',string $country='DE'): DHLShipmentRecipientAddressPostal {
		return new DHLShipmentRecipientAddressPostal($company,$first,$last,'Teststraße','1',$addition,'12345','Teststadt',null,$country);
	}
	private function request(?DHLShipmentRecipientAddress $address=null,string $reference='Ref12345',array $services=[],?string $email=null): DHLShipmentRequest {
		return new DHLShipmentRequest($reference,new DHLShipmentSenderAddress('Test GmbH','Teststraße','1','12345','Teststadt','DE','sender@example.test'),$address??$this->postal(),$email,null,1.2,$services);
	}
}
