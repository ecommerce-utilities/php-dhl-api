<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE\DEStreetTools;

class StreetTools {
	/**
	 * Returns the street and house number separated. If the separation is not possible, the original street and house number are returned.
	 *
	 * @param string $countryId
	 * @param string $street
	 * @param string|null $houseNumber
	 * @return array{string, string|null, string|null} The first element is the street, the second element is the house number and the third element is an address addition.
	 */
	public static function trySeparateStreetAndHouseNumber(string $countryId, string $street, ?string $houseNumber) {
		if($countryId === 'DE') {
			return DEStreetTools::trySeparateStreetAndHouseNumber($street, $houseNumber);
		}
		return [$street, $houseNumber, null];
	}

	/**
	 * @param string $countryId
	 * @param string $street1
	 * @param string $street2
	 * @param float $similarityPercent The minimum percentage of similarity between the two street names.
	 * @return bool
	 */
	public static function isSameStreetName(string $countryId, string $street1, string $street2, float $similarityPercent = 100): bool {
		if($countryId === 'DE') {
			return DEStreetTools::isSameStreetName(street1: $street1, street2: $street2, similarityPercent: $similarityPercent);
		}

		return $street1 === $street2;
	}
}
