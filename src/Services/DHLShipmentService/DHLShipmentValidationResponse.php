<?php

namespace EcommerceUtilities\DHL\Services\DHLShipmentService;

/** Successful validation contains diagnostics, never a booked shipment or label. */
class DHLShipmentValidationResponse {
	/** @param list<string> $warnings */
	public function __construct(private readonly object $data, private readonly array $warnings) {}

	public function getData(): object {
		return $this->data;
	}

	/** @return list<string> */
	public function getWarnings(): array {
		return $this->warnings;
	}
}
