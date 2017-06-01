<?php
namespace EcommerceUtilities\DHL;

use EcommerceUtilities\DHL\Common\DHLApiCredentials;
use EcommerceUtilities\DHL\Services\DHLRetoureService;

class DHLServices {
	/** @var DHLApiCredentials */
	private $credentials;

	/**
	 * @param DHLApiCredentials $credentials
	 */
	public function __construct(DHLApiCredentials $credentials) {
		$this->credentials = $credentials;
	}

	/**
	 * @return DHLRetoureService
	 */
	public function getRetoureService() {
		return new DHLRetoureService($this->credentials);
	}
}
