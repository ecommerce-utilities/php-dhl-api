<?php

namespace Services\AddressTools\CountrySpecific\DE;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE\DECityTools;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DECityToolsTest extends TestCase {
	#[Test]
	#[DataProvider('cityNameProvider')]
	public function testIsSameName(string $cityName1, string $cityName2, int $similarityPercent, bool $expectedResult): void {
		$result = DECityTools::isSameName(cityName1: $cityName1, cityName2: $cityName2, similarityPercent: $similarityPercent);
		self::assertEquals($expectedResult, $result, sprintf("Assert that '%s' %s as '%s'", $cityName1, $expectedResult ? 'is the same' : 'is not the same', $cityName2));
	}

	/**
	 * @return array<array{string, string, float, bool}>
	 */
	public static function cityNameProvider(): array {
		return [
			['Reutlingen', 'Reutlingen', 100, true],
			['Reutlingen', 'Retlingen', 100, false],
			['Stadt an der Havel', 'Stadt a. d. Havel', 100, true],
			['Stadt an der Havel', 'Stadt a.d. Havel', 100, true],
			['Stadt an der Havel', 'Stadt a.d. Havl', 100, false],
		];
	}
}
