<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE;

class DECityTools {
	public static function isSameName(string $cityName1, string $cityName2, float $similarityPercent = 100): bool {
		if($cityName1 === $cityName2) {
			return true;
		}

		$cityName1 = mb_convert_case($cityName1, MB_CASE_LOWER, 'UTF-8');
		$cityName2 = mb_convert_case($cityName2, MB_CASE_LOWER, 'UTF-8');
		if($cityName1 === $cityName2) {
			return true;
		}

		$cityName1 = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cityName1);
		$cityName2 = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cityName2);
		if($cityName1 === $cityName2) {
			return true;
		}

		// Matches patterns like
		$replace = static fn($cityName) => (string) preg_replace('{\\s+(?:a[n.]\\s*?d(er)?|am?)[\\s\\.]+\\b}iu', ' x. ', $cityName);
		$cityName1 = $replace($cityName1);
		$cityName2 = $replace($cityName2);
		if($cityName1 === $cityName2) {
			return true;
		}

		$cityName1 = (string) preg_replace('{\\W+?}', '', $cityName1);
		$cityName2 = (string) preg_replace('{\\W+?}', '', $cityName2);
		if($cityName1 === $cityName2) {
			return true;
		}

		$len1 = mb_strlen($cityName1);
		$len2 = mb_strlen($cityName2);
		$maxlen = max($len1, $len2);
		$score = ($maxlen - levenshtein($cityName1, $cityName2)) / $maxlen;
		return $score > $similarityPercent;
	}
}
