<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\FuzzyAddressMatch;

class FuzzyAddressMatchResult {
	public function __construct(
		public string $streetShort,
		public string $streetLong,
		public string|null $houseNumber,
		public string|null $addressAddition,
		public string $postalCode,
		public string $city,
		public string $cityLong,
		public string $countryId,
		public float $score,
	) {}
}
