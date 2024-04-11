<?php

namespace EcommerceUtilities\DHL\Common;

class DHLBusinessPortalCredentials {
	/**
	 * @param string $username
	 * @param string $password
	 */
	public function __construct(
		private readonly string $username,
		private readonly string $password
	) {}

	/**
	 * @return string
	 */
	public function getUsername(): string {
		return $this->username;
	}

	/**
	 * @return string
	 */
	public function getPassword(): string {
		return $this->password;
	}
}
