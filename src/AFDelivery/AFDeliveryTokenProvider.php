<?php

namespace EcommerceUtilities\DHL\AFDelivery;

use EcommerceUtilities\DHL\AFDelivery\TokenProvider\NoTokenException;
use EcommerceUtilities\DHL\Common\DHLTools;
use JsonException;

class AFDeliveryTokenProvider {
	public function __construct(
		private readonly AFDeliveryHttpClient $client,
		private readonly string $username,
		private readonly string $password
	) {}

	public function getToken(): string {
		$base64 = base64_encode(sprintf('%s:%s', $this->username, $this->password));
		$response = $this->client->get('login/3.0.0', [
			'headers' => [
				'Authorization' => sprintf('Basic %s', $base64)
			]
		]);

		try {
			$data = DHLTools::jsonDecode($response->body);

			if(($data['access_token'] ?? null) === null) {
				throw new NoTokenException('No access token found in response');
			}

			return $data['access_token'];
		} catch(JsonException) {
			throw new NoTokenException('No access token found in response');
		}
	}
}
