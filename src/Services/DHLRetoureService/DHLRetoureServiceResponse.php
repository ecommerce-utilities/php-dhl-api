<?php
namespace EcommerceUtilities\DHL\Services\DHLRetoureService;

class DHLRetoureServiceResponse {
	public function __construct(
		private readonly string $trackingNumber,
		private readonly string $labelData,
		private readonly object $data
	) {}

	/**
	 * @return string
	 */
	public function getTrackingNumber(): string {
		return $this->trackingNumber;
	}

	/**
	 * @return string
	 */
	public function getLabelData(): string {
		return $this->labelData;
	}

	/**
	 * @return object
	 */
	public function getData(): object {
		return $this->data;
	}
}
