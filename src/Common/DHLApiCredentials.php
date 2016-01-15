<?php
namespace EcommerceUtilities\DHL\Common;

class DHLApiCredentials {
	/** @var string */
	private $username = null;
	/** @var string */
	private $password = null;
	/** @var string */
	private $portalId = null;
	/** @var string */
	private $endpoint = null;
	/** @var string */
	private $deliveryName;

	/**
	 * @param string $username
	 * @param string $password
	 * @param string $portalId
	 * @param string $deliveryName
	 * @param string $endpoint
	 */
	public function __construct($username, $password, $portalId, $deliveryName, $endpoint = 'https://amsel.dpwn.net/abholportal/gw/lp/SoapConnector') {
		$this->username = $username;
		$this->password = $password;
		$this->portalId = $portalId;
		$this->endpoint = $endpoint;
		$this->deliveryName = $deliveryName;
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * @return string
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @return string
	 */
	public function getPortalId() {
		return $this->portalId;
	}

	/**
	 * @return string
	 */
	public function getDeliveryName() {
		return $this->deliveryName;
	}

	/**
	 * @return string
	 */
	public function getEndpoint() {
		return $this->endpoint;
	}
}