<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE;

class DEStreetTools {
	public const HOUSE_NUMBER = '(?<!\\d)\\d+\\W*?(?:-\\W*?\\d+|\\x2F\\W*?\\d+|\\\\\\W*?\\d+)?\\W*?(?:[a-z]{0,2}\\b)?(?!\\d)';
	public const ADDITIONAL_INFORMATION = '(?:\\s*(?:Haus|Wohnung|Wohng|Whg|Wng|Etage|Halle|App|Apt|Raum)\\W*[\\d\\.\\:]+|\\d+\\.\\W*(?:Haus|Wohnung|Wohng|Whg|Wng|Etage|Halle|App|Apt|Raum)|c/o\\W+(.+?))*';

	/**
	 * Returns the street and house number separated. If the separation is not possible, the original street and house number are returned.
	 *
	 * @param string $street
	 * @param string|null $houseNumber
	 * @return array{string, string|null, string|null} The first element is the street, the second element is the house number and the third element is an address addition.
	 */
	public static function trySeparateStreetAndHouseNumber(string $street, ?string $houseNumber): array {
		if($houseNumber !== null && !preg_match(sprintf('{^(\\D+?)\\W*(%s)\\W*$}uix', self::HOUSE_NUMBER), $street)) {
			if(preg_match(sprintf('{^\\s*?(%s)\\s*$}', self::HOUSE_NUMBER), $houseNumber)) {
				return [$street, $houseNumber, null];
			}

			$streetAndHouseNumber = sprintf('%s %s', trim($street), trim($houseNumber));
		} else {
			$streetAndHouseNumber = trim($street);
		}

		// Exact match of "Some Street Name Nr. ###"
		$pattern = sprintf('{^()\\W+(%1$s)$}uix', self::HOUSE_NUMBER);
		if(preg_match($pattern, $streetAndHouseNumber, $m)) {
			$hn = self::houseNumberPostProcessing($m[2]);
			return [$m[1], $hn, null];
		}

		// Exact match of "Str. ### ##", Berlin and L ##, # Mannheim
		$pattern = sprintf('{^([a-z]{1,3}\\W*\\d+)\\W+(%1$s)$}uix', self::HOUSE_NUMBER);
		if(preg_match($pattern, $streetAndHouseNumber, $m)) {
			$hn = self::houseNumberPostProcessing($m[2]);
			return [$m[1], $hn, null];
		}

		// Exact match
		$pattern = sprintf('{^(\\D+?)\\W*(%s)\\W*$}uix', self::HOUSE_NUMBER);
		if(preg_match($pattern, $streetAndHouseNumber, $m)) {
			$hn = self::houseNumberPostProcessing($m[2]);
			return [$m[1], $hn, null];
		}

		// Exact match; Test unnecessary doubling of house numbers like "Musterstraße 12c 12c"
		$pattern = sprintf('{^(\\D+?)\\W*?(%1$s)\\W+?(%1$s)$}uix', self::HOUSE_NUMBER);
		if(preg_match($pattern, $streetAndHouseNumber, $m)) {
			$hn1 = self::houseNumberPostProcessing($m[2]);
			$hn2 = self::houseNumberPostProcessing($m[3]);
			if($hn1 === $hn2) {
				return [$m[1], $hn1, null];
			}
			return [$m[1], "{$hn1} {$hn2}", null];
		}

		// Exact match with known additions; Test additional known address information
		$pattern = sprintf('{^(\\D+?\\.?)\\W*(%1$s)\\W+?(\\d+\\.?\\W+?Stock|Stock\\W+?\\d+\\.?)$}uix', self::HOUSE_NUMBER);
		if(preg_match($pattern, $streetAndHouseNumber, $m)) {
			$hn = self::houseNumberPostProcessing($m[2]);
			$addition = $m[3];
			return [$m[1], $hn, $addition];
		}

		// Match "10 Musterstraße"
		if(preg_match(sprintf('{^\\W*?(%s)\\W*?(\\D+)$}', self::HOUSE_NUMBER), $streetAndHouseNumber, $m)) {
			$hn = self::houseNumberPostProcessing($m[2]);
			return [$m[2], $hn, null];
		}

		// Match known patterns for Additional Information
		$pattern = sprintf('{^(\\D+?)\\W*(%s)\\W+(%s)\\W*$}uix', self::HOUSE_NUMBER, self::ADDITIONAL_INFORMATION);
		if(preg_match($pattern, $streetAndHouseNumber, $m)) {
			$hn = self::houseNumberPostProcessing($m[2]);
			return [$m[1], $hn, $m[3]];
		}

		#printf("%s:%s %s\n", __FILE__, __LINE__, $streetAndHouseNumber);
		return [$street, $houseNumber, null];
	}

	private static function houseNumberPostProcessing(string $houseNumber): string {
		$houseNumber = (string) preg_replace('{[,.\\s]}', '', $houseNumber);
		$houseNumber = strtr($houseNumber, ['\\' => '/']);
		$houseNumber = (string) preg_replace('{/+}', '/', $houseNumber);
		$houseNumber = mb_convert_case($houseNumber, MB_CASE_UPPER, 'UTF-8');
		return $houseNumber;
	}

	public static function isSameStreetName(string $street1, string $street2, float $similarityPercent = 100): bool {
		if($street1 === $street2) {
			return true;
		}

		$street1 = mb_convert_case($street1, MB_CASE_LOWER, 'UTF-8');
		$street2 = mb_convert_case($street2, MB_CASE_LOWER, 'UTF-8');
		if($street1 === $street2) {
			return true;
		}

		$street1 = (string) @iconv('UTF-8', 'ASCII//TRANSLIT', $street1);
		$street2 = (string) @iconv('UTF-8', 'ASCII//TRANSLIT', $street2);
		if($street1 === $street2) {
			return true;
		}

		[$test1, $test2] = DEStringTools::applyDotShortening($street1, $street2);
		if($test1 === $test2) {
			return true;
		}

		$street1 = (string) preg_replace('{(?:(str)a[ßs]+e|Stra?[ßs]*e?\\.)\\b}iu', '$1', $street1);
		$street2 = (string) preg_replace('{(?:(str)a[ßs]+e|Stra?[ßs]*e?\\.)\\b}iu', '$1', $street2);
		if($street1 === $street2) {
			return true;
		}

		if(preg_match(sprintf('{%s(?:\\s*Str(?:aße)?\\.?)}ui', preg_quote($street1, '{}')), $street2)) {
			return true;
		}

		$street1 = (string) preg_replace('{\\W+?}', '', $street1);
		$street2 = (string) preg_replace('{\\W+?}', '', $street2);
		if($street1 === $street2) {
			return true;
		}

		$len1 = mb_strlen($street1);
		$len2 = mb_strlen($street2);
		$maxlen = max($len1, $len2);
		$levenshtein = levenshtein($street1, $street2);
		$score = ($maxlen - $levenshtein) / $maxlen * 100;
		return $score > $similarityPercent;
	}
}
