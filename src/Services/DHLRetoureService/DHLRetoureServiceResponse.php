<?php
namespace EcommerceUtilities\DHL\Services\DHLRetoureService;

class DHLRetoureServiceResponse {
	/** @var string|null */
	private $trackingNumber = null;
	/** @var string|null */
	private $pdf = null;
	/** @var string|null */
	private $xml = null;

	/**
	 * @param null|string $trackingNumber
	 * @param null|string $pdf
	 * @param null|string $xml
	 */
	public function __construct($trackingNumber, $pdf, $xml) {
		$this->trackingNumber = $trackingNumber;
		$this->pdf = $pdf;
		$this->xml = $xml;
	}

	/**
	 * @return null|string
	 */
	public function getTrackingNumber() {
		return $this->trackingNumber;
	}

	/**
	 * @return null|string
	 */
	public function getPdf() {
		return $this->pdf;
	}

	/**
	 * @return null|string
	 */
	public function getXml() {
		return $this->xml;
	}
}
