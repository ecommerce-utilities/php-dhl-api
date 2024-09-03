<?php

namespace Services\AddressTools\CountrySpecific;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DEReformatAddressService;
use EcommerceUtilities\DHL\Services\AddressTools\DEFuzzyAddressMatchService;
use EcommerceUtilities\DHL\Services\AddressTools\ReformatProbability;
use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DEReformatAddressServiceTest extends TestCase {
	private DEReformatAddressService $service;

	public function setUp(): void {
		$fuzzyMatchService = new DEFuzzyAddressMatchService(httpClient: new Client(['base_uri' => 'http://localhost:9200']), requestFactory: new RequestFactory(), streamFactory: new StreamFactory());

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
	 */
	#[Test]
	#[DataProvider('addressProvider')]
	public function testReformat(array $premiseLines, string $street, string $houseNumber, string $postalCode, string $city, string $country, ReformatProbability $probability): void {
		$result = $this->service->reformat(
			premiseLines: $premiseLines,
			street: $street,
			houseNumber: $houseNumber,
			postalCode: $postalCode,
			city: $city,
			country: $country
		);
		self::assertEquals($probability, $result->getProbability());
		#self::assertEquals((object) [], $result);
	}

	/**
	 * @return array<array{string[], string, string, string, string, string}>
	 */
	public static function addressProvider(): array {
		return [
			[['Saab, Tarek'], 'Amselweg Straße', '3', '33175', 'Bad Lippspringe', 'DE', ReformatProbability::VeryLow],
			[['Postfiliale 427', 'Letschert, Anja'], 'Hermann-Geisen-Straße', '61', '56203', 'Hlöhr-Grenzhausen', 'DE', ReformatProbability::High],
			[['BCS AIS'], 'Industriestrasse', '2-8', '25601', 'Radolfzell', 'DE', ReformatProbability::VeryLow],
			[['LBS Süd'], 'Klopstockstraße, Apt. 1504', '8', '80804', 'München', 'DE', ReformatProbability::VeryLow],
			[['Max Mustermann'], 'Kalk-Mülheimer', '294', '51065', 'Köln', 'DE', ReformatProbability::VeryHigh],
			[['Max Mustermann'], 'Am Fuhrenkamp', '13', '28309', 'Bremen', 'DE', ReformatProbability::VeryLow],
			[['Max Mustermann'], 'Wasserburger Str', '50a', '83395', 'Freilassing', 'DE', ReformatProbability::VeryHigh],
		];
	}
}
