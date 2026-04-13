<?php

namespace EcommerceUtilities\DHL\Services\DHLShipmentService;

class DHLCashOnDeliveryService implements DHLShipmentServiceProduct {
	public function __construct(
		public readonly float $amount,
		public readonly string $accountOwner,
		public readonly string $bankIban,
		public readonly string $bankBic,
		public readonly string $bankName,
		public readonly string $reference,
		public readonly string $reference2,
	) {}
}
