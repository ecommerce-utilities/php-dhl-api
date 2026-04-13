<?php

namespace EcommerceUtilities\DHL\Services\DHLShipmentService;

class DHLShipmentServiceResponse {
	public function __construct(
		private readonly string $trackingNumber,
		private readonly string $labelData,
		private readonly ?string $codLabelData,
		private readonly ?string $routingCode,
		private readonly object $data,
	) {}

	public function getTrackingNumber(): string {
		return $this->trackingNumber;
	}

	public function getLabelData(): string {
		return $this->labelData;
	}

	public function getCodLabelData(): ?string {
		return $this->codLabelData;
	}

	public function getRoutingCode(): ?string {
		return $this->routingCode;
	}

	public function getData(): object {
		return $this->data;
	}
}
