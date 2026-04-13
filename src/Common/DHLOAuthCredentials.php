<?php

namespace EcommerceUtilities\DHL\Common;

class DHLOAuthCredentials {
	/**
	 * @param string $key API key from developer.dhl.com
	 * @param string $secret API secret from developer.dhl.com
	 */
	public function __construct(
		public readonly string $businessPortalUsername,
		public readonly string $businessPortalPassword,
		public readonly string $key,
		public readonly string $secret,
		public readonly bool $isProductionEnv,
		public readonly string $receiverId = '',
	) {}
}
