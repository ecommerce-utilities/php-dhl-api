<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE\DECityTools;

class CityTools {
	/**
	 * @param string $countryId
	 * @param string $cityName1
	 * @param string $cityName2
	 * @return bool
	 */
	public static function isSameName(string $countryId, string $cityName1, string $cityName2, float $similarityPercent = 100): bool {
		if($countryId === 'DE') {
			return DECityTools::isSameName(cityName1: $cityName1, cityName2: $cityName2, similarityPercent: $similarityPercent);
		}

		return $cityName1 === $cityName2;
	}
}
