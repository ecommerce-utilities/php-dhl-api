<?php

namespace Services\AddressTools\CountrySpecific\DE;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE\DEStringTools;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DEStringToolsTest extends TestCase {
	/**
	 * @param string $s1
	 * @param string $s2
	 * @param array{string, string} $expected
	 * @return void
	 */
	#[Test]
	#[DataProvider('nameProvider')]
	public function testDotShortening(string $s1, string $s2, array $expected): void {
		$result = DEStringTools::applyDotShortening($s1, $s2);
		self::assertEquals($expected, $result);
	}

	/**
	 * @return array<array{string, string, array{string, string}}>
	 */
	public static function nameProvider(): array {
		return [
			['Musterstraße 12-13', 'Musterstr. 12-13', ['Musterstraße 12-13', 'Musterstraße 12-13']],
			['Musterstraße 12', 'Musterstr. 12-13', ['Musterstraße 12', 'Musterstraße 12-13']],
			['Kirchentellinsfurterstraße 12-13', 'K.furterstraße 12-13', ['Kirchentellinsfurterstraße 12-13', 'Kirchentellinsfurterstraße 12-13']],
			['K.furterstraße 12-13', 'Kirchentellinsfurterstraße 12-13', ['Kirchentellinsfurterstraße 12-13', 'Kirchentellinsfurterstraße 12-13']],
			['A. d. Lautsche', 'An der Lautsche', ['An der Lautsche', 'An der Lautsche']],
		];
	}
}
