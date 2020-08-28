<?php
namespace EcommerceUtilities\DHL\Services\DHLRetoureService;

class DHLRetoureServiceResponse {
	/** @var string */
	private $trackingNumber;
	/** @var string */
	private $labelData;
	/** @var object */
	private $data;

	/**
	 * @param string $trackingNumber
	 * @param string $labelData
	 * @param object $data
	 */
	public function __construct(string $trackingNumber, string $labelData, object $data) {
		$this->trackingNumber = $trackingNumber;
		$this->labelData = $labelData;
		$this->data = $data;
	}

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
