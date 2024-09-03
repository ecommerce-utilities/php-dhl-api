<?php

namespace Services\AddressTools\CountrySpecific\DE;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE\DEStreetTools;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DEStreetToolsTest extends TestCase {
	/**
	 * @param string $street
	 * @param string|null $houseNumber
	 * @param array{string, string|null, string|null} $expected
	 * @return void
	 */
	#[Test]
	#[DataProvider('streetAndHouseNumberProvider')]
	public function testStreetAndHouseNumberSeparation(string $street, ?string $houseNumber, array $expected): void {
		$result = DEStreetTools::trySeparateStreetAndHouseNumber($street, $houseNumber);
		self::assertEquals($expected, $result);
	}

	/**
	 * @param string $street1
	 * @param string $street2
	 * @param int $fuzzyness
	 * @param bool $expected
	 * @return void
	 */
	#[Test]
	#[DataProvider('probablySameStreetsProvider')]
	public function testIsSameStreet(string $street1, string $street2, int $fuzzyness, bool $expected): void {
		$result = DEStreetTools::isSameStreetName($street1, $street2, $fuzzyness);
		self::assertEquals($expected, $result, sprintf("Assert that '%s' %s as '%s'", $street1, $expected ? 'is the same' : 'is not the same', $street2));
	}

	/**
	 * @return array<array{string, string|null, array{string, string|null, string|null}}>
	 */
	public static function streetAndHouseNumberProvider(): array {
		return [
			['Musterstraße', '123', ['Musterstraße', '123', null]],
			['Musterstraße 123', null, ['Musterstraße', '123', null]],
			['Musterstraße 123a', null, ['Musterstraße', '123A', null]],
			['Musterstraße 123 A', null, ['Musterstraße', '123A', null]],
			['Musterstraße 123, A', null, ['Musterstraße', '123A', null]],
			['Musterstraße 12-13', null, ['Musterstraße', '12-13', null]],
			['Musterstraße 12 -13', null, ['Musterstraße', '12-13', null]],
			['Musterstraße 12- 13', null, ['Musterstraße', '12-13', null]],
			['Musterstraße 12/13', null, ['Musterstraße', '12/13', null]],
			['Musterstraße 12/ 13', null, ['Musterstraße', '12/13', null]],
			['Musterstraße 12 /13', null, ['Musterstraße', '12/13', null]],
			['Musterstraße 12/13a', null, ['Musterstraße', '12/13A', null]],
			['Musterstraße 12\\13', null, ['Musterstraße', '12/13', null]],
			['Musterstraße 12\\ 13', null, ['Musterstraße', '12/13', null]],
			['Musterstraße 12 \\13', null, ['Musterstraße', '12/13', null]],
			['Musterstraße 12\\13a', null, ['Musterstraße', '12/13A', null]],
			['Musterstraße, 80', '80', ['Musterstraße', '80', null]],
			['Musterstraße, 80 80', null, ['Musterstraße', '80', null]],
			['4. Musterstraße 4', null, ['4. Musterstraße 4', '', null]],
			['Friedenauer Straße 17.II', null, ['Friedenauer Straße', '17II', null]],
			[' Wasserburger Str 50a ', null, ['Wasserburger Str', '50A', null]],
			['L 10  3', null, ['L 10', '3', null]],
			['Musterstraße 10 Haus 2', null, ['Musterstraße', '10', 'Haus 2']],
			['Musterstraße 10 Wohnung 2', null, ['Musterstraße', '10', 'Wohnung 2']],
			['Musterstraße 10 Etage 2', null, ['Musterstraße', '10', 'Etage 2']],
			['Musterstraße 10 Halle 2', null, ['Musterstraße', '10', 'Halle 2']],
			['Musterstraße 10 App 2', null, ['Musterstraße', '10', 'App 2']],
			['Musterstraße 10 Apt 2', null, ['Musterstraße', '10', 'Apt 2']],
			['Musterstraße 10 Whg 2', null, ['Musterstraße', '10', 'Whg 2']],
			['Musterstraße 10 Raum 2', null, ['Musterstraße', '10', 'Raum 2']],
			['Musterstraße 10 Haus 2 Wohng 1', null, ['Musterstraße', '10', 'Haus 2 Wohng 1']],
		];
	}

	/**
	 * @return array<array{string, string, int, bool}>
	 */
	public static function probablySameStreetsProvider(): array {
		return [
			['Musterstraße', 'Musterstraße', 100, true],
			['Musterstraße', 'Musterstrasse', 100, true],
			['Musterstraße', 'Musterstr.', 100, true],
			['Musterstraße', 'Muster-Str.', 100, true],
			['Lissabonner Straße', 'Lissaboner Str.', 100, false],
			['Lissabonner Straße', 'Lissaboner Str.', 87, true],
			['Str. der Opfer des Faschismus', 'Straße der Opfer des Faschismus', 100, true],
			['An d. Lautsche', 'An der Lautsche', 100, true],
			['A. d. Lautsche', 'An der Lautsche', 100, true],
			['An der Lautsche', 'An d. Lautsche', 100, true],
			['An der Lautsche', 'A. d. Lautsche', 100, true],
		];
	}
}
