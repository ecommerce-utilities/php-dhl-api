<?php

namespace EcommerceUtilities\DHL\Services\DHLShipmentService;

class DHLShipmentSenderAddress {
	public function __construct(
		public readonly string $company,
		public readonly string $street,
		public readonly string $houseNumber,
		public readonly string $zip,
		public readonly string $city,
		public readonly string $countryCode,
		public readonly string $mail,
	) {}
}
