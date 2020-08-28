<?php
namespace EcommerceUtilities\DHL\Common;

class DHLApiCredentials {
	/** @var bool */
	private $productionEnv;
	/** @var string */
	private $username;
	/** @var string */
	private $password;
	/** @var string */
	private $receiverId;

	/**
	 * @param bool $productionEnv
	 * @param string $username
	 * @param string $password
	 * @param string $receiverId
	 */
	public function __construct(bool $productionEnv, string $username, string $password, string $receiverId) {
		$this->productionEnv = $productionEnv;
		$this->username = $username;
		$this->password = $password;
		$this->receiverId = $receiverId;
	}

	public function getUsername(): string {
		return $this->username;
	}

	public function getPassword(): string {
		return $this->password;
	}

	public function getReceiverId(): string {
		return $this->receiverId;
	}

	public function isProductionEnv(): string {
		return $this->productionEnv;
	}
}
