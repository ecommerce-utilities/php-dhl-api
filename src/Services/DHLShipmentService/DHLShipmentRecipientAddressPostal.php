<?php

namespace EcommerceUtilities\DHL\Services\DHLShipmentService;

class DHLShipmentRecipientAddressPostal extends DHLShipmentRecipientAddress {
	public function __construct(
		public readonly string $company,
		?string $firstname,
		?string $lastname,
		public readonly string $street,
		public readonly string $houseNumber,
		public readonly ?string $addressAddition,
		string $zip,
		string $city,
		?string $state,
		string $countryCode,
	) {
		parent::__construct(
			firstname: $firstname,
			lastname: $lastname,
			zip: $zip,
			city: $city,
			state: $state,
			countryCode: $countryCode,
		);
	}

	public function getAddressType(): string {
		return 'postal';
	}
}
