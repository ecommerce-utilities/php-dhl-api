<?php

namespace EcommerceUtilities\DHL\Common;

use EcommerceUtilities\DHL\Http\HttpClient;
use EcommerceUtilities\DHL\Http\HttpClientException;
use EcommerceUtilities\DHL\Http\HttpResponse;

class DHLOAuthTokenProvider {
	private ?string $accessToken = null;
	private ?int $expiresAt = null;

	public function __construct(
		private readonly DHLOAuthCredentials $oauthCredentials,
		private readonly HttpClient $client,
	) {}

	public function getToken(): string {
		if($this->accessToken !== null && $this->expiresAt !== null && time() < $this->expiresAt) {
			return $this->accessToken;
		}

		try {
			$responseJson = $this->client->post('/parcel/de/account/auth/ropc/v1/token', http_build_query([
				'grant_type' => 'password',
				'username' => $this->oauthCredentials->businessPortalUsername,
				'password' => $this->oauthCredentials->businessPortalPassword,
				'client_id' => $this->oauthCredentials->key,
				'client_secret' => $this->oauthCredentials->secret,
			]), [
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
					'Accept' => 'application/json',
				]
			]);
		} catch(HttpClientException $e) {
			throw $this->createApiException($e->response);
		}

		$responseData = DHLTools::jsonDecode($responseJson->body, asObject: true, default: (object) []);
		if(($responseData->token_type ?? null) !== 'Bearer' || !is_string($responseData->access_token ?? null)) {
			throw new DHLApiException('Invalid OAuth token response from DHL');
		}

		$expiresIn = max(1, (int) ($responseData->expires_in ?? 1) - 30);
		$this->accessToken = $responseData->access_token;
		$this->expiresAt = time() + $expiresIn;

		return $this->accessToken;
	}

	private function createApiException(HttpResponse $response): DHLApiException {
		$data = DHLTools::jsonDecode($response->body, asObject: true, default: (object) []);
		$message = $data->error_description
			?? $data->message
			?? $data->detail
			?? $data->title
			?? "OAuth token request failed with HTTP status {$response->statusCode}";

		return new DHLApiException((string) $message);
	}
}
