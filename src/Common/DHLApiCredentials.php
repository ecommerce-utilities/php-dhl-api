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
	private $warehouseName;

	/**
	 * @param string $username
	 * @param string $password
	 * @param string $portalId
	 * @param string $warehouseName
	 * @param string $endpoint
	 */
	public function __construct($username, $password, $portalId, $warehouseName, $endpoint = 'https://amsel.dpwn.net/abholportal/gw/lp/SoapConnector') {
		$this->username = $username;
		$this->password = $password;
		$this->portalId = $portalId;
		$this->endpoint = $endpoint;
		$this->warehouseName = $warehouseName;
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
	public function getWarehouseName() {
		return $this->warehouseName;
	}

	/**
	 * @return string
	 */
	public function getEndpoint() {
		return $this->endpoint;
	}
}