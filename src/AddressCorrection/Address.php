<?php

namespace EcommerceUtilities\DHL\AddressCorrection;

class Address {
	public function __construct(
		public readonly ?string $firstname,
		public readonly ?string $name,
		public readonly string $street,
		public readonly int|string $houseNumber,
		public readonly string $postalCode,
		public readonly string $city,
		public readonly ?string $district
	) {}
}
