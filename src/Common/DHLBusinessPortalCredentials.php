<?php
namespace EcommerceUtilities\DHL\Common;

class DHLBusinessPortalCredentials {
	public function __construct(
		public readonly bool $isProductionEnv,
		public readonly string $username,
		public readonly string $password,
		public readonly string $receiverId = ''
	) {}
}
