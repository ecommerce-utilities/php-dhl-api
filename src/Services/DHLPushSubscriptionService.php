<?php

namespace EcommerceUtilities\DHL\Services;

use EcommerceUtilities\DHL\Common\DHLOAuthTokenProvider;
use EcommerceUtilities\DHL\Common\DHLTools;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;

class DHLPushSubscriptionService {
	public function __construct(
		private readonly DHLOAuthTokenProvider $tokenProvider,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly ClientInterface $client
	) {}

	public function getSubscriptions(): array {
		$request = $this->requestFactory->createRequest('GET', 'https://api.dhl.com/webhooks/v1/subscribe');
		// https://api-eu.dhl.com/post/de/tracking/push/v2/subscriptions
		// https://api.dhl.com/webhooks/v1/subscribe
		$token = $this->tokenProvider->getToken();
		$request = $request->withHeader('Authorization', "Bearer $token");
		$response = $this->client->sendRequest($request);
		$responseJson = $response->getBody()->getContents();
		$responseData = DHLTools::jsonDecode($responseJson);
		if(!is_array($responseData)) {
			throw new RuntimeException('Unexpected push subscription response');
		}

		return $responseData;
	}
}
