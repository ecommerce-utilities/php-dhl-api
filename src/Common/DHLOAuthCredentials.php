<?php

namespace EcommerceUtilities\DHL\Common;

class DHLOAuthCredentials {
	/**
	 * @param string $key API key from developer.dhl.com
	 * @param string $secret API secret from developer.dhl.com
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $secret
	) {}
}
