<?php

namespace EcommerceUtilities\DHL\Services\DHLShipmentService;

use DateTimeInterface;

class DHLShipmentRequest {
	/**
	 * @param DHLShipmentServiceProduct[] $services
	 */
	public function __construct(
		public readonly string $reference,
		public readonly DHLShipmentSenderAddress $senderAddress,
		public readonly DHLShipmentRecipientAddress $recipientAddress,
		public readonly ?string $email,
		public readonly ?string $phone,
		public readonly float $weight,
		public readonly array $services = [],
		public readonly ?DateTimeInterface $shipDate = null,
	) {}
}
