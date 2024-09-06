<?php

namespace EcommerceUtilities\DHL\AFDelivery;

use DateTimeImmutable;
use DateTimeInterface;
use EcommerceUtilities\DHL\AFDelivery\TokenProvider\NoTokenException;
use EcommerceUtilities\DHL\Common\DHLTools;
use JsonException;

class AFDeliveryTokenProvider {
	/** @var null|array{token: string, expiresAt: DateTimeInterface} */
	private null|array $cache = null;

	public function __construct(
		private readonly AFDeliveryHttpClient $client,
		private readonly string $username,
		private readonly string $password
	) {}

	public function getToken(): string {
		if($this->cache === null || $this->cache['expiresAt'] < new DateTimeImmutable()) {
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

				$this->cache = [
					'token' => $data['access_token'],
					'expiresAt' => (new DateTimeImmutable())
						->setTimestamp($data['expires_in_epoch_seconds'])
						->modify('-1 minute')
				];
			} catch(JsonException) {
				throw new NoTokenException('No access token found in response');
			}
		}

		return $this->cache['token'];
	}
}
