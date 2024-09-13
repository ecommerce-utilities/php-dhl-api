<?php

namespace Services\AddressTools\CountrySpecific;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DEReformatAddressService;
use EcommerceUtilities\DHL\Services\AddressTools\DEFuzzyAddressMatchService;
use EcommerceUtilities\DHL\Services\AddressTools\ReformatProbability;
use EcommerceUtilities\DHL\Services\AddressTools\ResultTypes\ReformatPackstationAddressResult;
use EcommerceUtilities\DHL\Services\AddressTools\ResultTypes\ReformatPostalAddressResult;
use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DEReformatAddressServiceTest extends TestCase {
	private DEReformatAddressService $service;

	public function setUp(): void {
		$fuzzyMatchService = new DEFuzzyAddressMatchService(httpClient: new Client(['base_uri' => 'http://localhost:9202']), requestFactory: new RequestFactory(), streamFactory: new StreamFactory());

		$this->service = new DEReformatAddressService(
			fuzzyAddressMatchService: $fuzzyMatchService,
			packstationReformatService: new DEReformatAddressService\DEPackstationReformatService(),
		);
	}

	/**
	 * @param string[] $premiseLines
	 * @param string $street
	 * @param string $houseNumber
	 * @param string $postalCode
	 * @param string $city
	 * @param string $country
	 * @param string[] $expectedAddress
	 */
	#[Test]
	#[DataProvider('addressProvider')]
	public function testReformat(array $premiseLines, string $street, string $houseNumber, string $postalCode, string $city, string $country, ReformatProbability $probability, array $expectedAddress): void {
		$result = $this->service->reformat(
			premiseLines: $premiseLines,
			street: $street,
			houseNumber: $houseNumber,
			postalCode: $postalCode,
			city: $city,
			country: $country
		);
		self::assertEquals($probability, $result->getProbability(), json_encode(func_get_args(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

		if($result instanceof ReformatPostalAddressResult) {
			self::assertEquals($expectedAddress[0], $result->premiseLines);
			self::assertEquals($expectedAddress[1], $result->street);
			self::assertEquals($expectedAddress[2], $result->houseNumber);
			self::assertEquals($expectedAddress[3], $result->postalCode);
			self::assertEquals($expectedAddress[4], $result->city);
		} elseif($result instanceof ReformatPackstationAddressResult) {
			self::assertEquals((object) $expectedAddress, $result);
		} else {
			throw new \RuntimeException('Unexpected result type: ' . get_debug_type($result));
		}
	}

	/**
	 * @return array<array{string[], string, string, string, string, string}>
	 */
	public static function addressProvider(): array {
		return [
			[['Max Mustermann'], 'Amselweg Straße', '3', '33175', 'Bad Lippspringe', 'DE', ReformatProbability::VeryLow, [['Max Mustermann'], 'Amselweg Straße', '3', '33175', 'Bad Lippspringe']],
			[['Max Mustermann', 'Hermann-Geisen-Straße'], 'Postfiliale', '427', '56203', 'Hlöhr-Grenzhausen', 'DE', ReformatProbability::High, [['Max Mustermann', 'Hermann-Geisen-Straße'], 'Postfiliale', '427', '56203', 'Hlöhr-Grenzhausen']],
			[['Musterfirma'], 'Industriestrasse', '2-8', '25601', 'Radolfzell', 'DE', ReformatProbability::VeryLow, [['Musterfirma'], 'Industriestrasse', '2-8', '25601', 'Radolfzell']],
			[['Musterfirma'], 'Klopstockstraße, Apt. 1504', '8', '80804', 'München', 'DE', ReformatProbability::VeryLow, [['Musterfirma'], 'Klopstockstraße, Apt. 1504', '8', '80804', 'München']],
			[['Max Mustermann'], 'Kalk-Mülheimer', '294', '51065', 'Köln', 'DE', ReformatProbability::VeryHigh, [['Max Mustermann'], 'Kalk-Mülheimer Str.', '294', '51065', 'Köln']],
			[['Max Mustermann'], 'Am Fuhrenkamp', '13', '28309', 'Bremen', 'DE', ReformatProbability::VeryLow, [['Max Mustermann'], 'Am Fuhrenkamp', '13', '28309', 'Bremen']],
			[['Max Mustermann'], 'Wasserburger Str', '50a', '83395', 'Freilassing', 'DE', ReformatProbability::VeryHigh, [['Max Mustermann'], 'Wasserburger Str.', '50a', '83395', 'Freilassing']],
			[['Max Mustermann'], 'Wasserburger Str 50a', '', '83395', 'Freilassing', 'DE', ReformatProbability::VeryHigh, [['Max Mustermann'], 'Wasserburger Str.', '50A', '83395', 'Freilassing']],
			[['Max Mustermann'], 'Wasserburger Str 50a XYZ Autoteile GmbH', '', '83395', 'Freilassing', 'DE', ReformatProbability::VeryHigh, [['Max Mustermann', 'XYZ Autoteile GmbH'], 'Wasserburger Str.', '50a', '83395', 'Freilassing']],
		];
	}
}
