<?php

namespace EcommerceUtilities\DHL\AddressCorrection;

class AddressCheckResult {
	public function __construct(
		public readonly string $requestId, // : "1",
		public readonly string $personMatch, // : "MATCH",
		public readonly string $householdMatch, // : "MATCH",
		public readonly string $addressMatch, // : "BUILDING",
		public readonly string $bestDeliveryAddress, // : "UNKNOWN",
		public readonly string $postalCode, // : "53113",
		public readonly string $city, // : "Bonn",
		public readonly string $district, // : "Gronau",
		public readonly string $street, // : "Sträßchensweg",
		public readonly string $houseNumber, // : "20",
		public readonly string $houseNumberAffix, // : "",
		public readonly string $firstname, // : "Abcdefg",
		public readonly string $name, // : "Hijklmno",
		public readonly string $distributionCode, // : "53113090020",
		public readonly bool $addressChanged, // : false,
		public readonly bool $nameChanged, // : false,
		public readonly float $similarity // : 0.95,
	) {}
}
