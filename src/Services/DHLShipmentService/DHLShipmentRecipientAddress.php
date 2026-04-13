<?php

namespace EcommerceUtilities\DHL\Services\DHLShipmentService;

abstract class DHLShipmentRecipientAddress {
	public function __construct(
		public readonly ?string $firstname,
		public readonly ?string $lastname,
		public readonly string $zip,
		public readonly string $city,
		public readonly ?string $state,
		public readonly string $countryCode,
	) {}

	abstract public function getAddressType(): string;
}
