<?php

namespace EcommerceUtilities\DHL\Services\DHLShipmentService;

class DHLShipmentRecipientAddressPackstation extends DHLShipmentRecipientAddress {
	public function __construct(
		?string $firstname,
		?string $lastname,
		public readonly string $customerNumber,
		public readonly string $packstationNumber,
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
		return 'packstation';
	}
}
