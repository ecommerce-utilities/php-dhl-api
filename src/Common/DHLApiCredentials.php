<?php
namespace EcommerceUtilities\DHL\Common;

class DHLApiCredentials {
	public function __construct(
		private readonly bool $productionEnv,
		private readonly string $username,
		private readonly string $password,
		private readonly string $receiverId
	) {}

	public function getUsername(): string {
		return $this->username;
	}

	public function getPassword(): string {
		return $this->password;
	}

	public function getReceiverId(): string {
		return $this->receiverId;
	}

	public function isProductionEnv(): bool {
		return $this->productionEnv;
	}
}
