<?php

namespace EcommerceUtilities\DHL\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * @phpstan-type HttpOptions = array{headers: array<string, string>}
 */
class HttpClient {
	public function __construct(
		private readonly RequestFactoryInterface $requestFactory,
		private readonly ClientInterface $client,
		private readonly string $baseUri,
	) {}

	/**
	 * @param string $path
	 * @param HttpOptions $options
	 * @return HttpResponse
	 */
	public function get(string $path, array $options = []): HttpResponse {
		$request = $this->requestFactory->createRequest('GET', rtrim($this->baseUri, '/') . '/' . ltrim($path, '/'));
		foreach($options['headers'] as $header => $value) {
			$request = $request->withHeader($header, $value);
		}
		$response = $this->client->sendRequest($request);
		$body = $response->getBody()->getContents();
		return $this->checkStatusCode(
			new HttpResponse(
				statusCode: $response->getStatusCode(),
				body: $body,
				headers: $response->getHeaders()
			)
		);
	}

	/**
	 * @param string $path
	 * @param string $body
	 * @param HttpOptions $options
	 * @return HttpResponse
	 */
	public function post(string $path, string $body, array $options = []): HttpResponse {
		$request = $this->requestFactory->createRequest('POST', rtrim($this->baseUri, '/') . '/' . ltrim($path, '/'));
		$request->getBody()->write($body);
		foreach($options['headers'] as $header => $value) {
			$request = $request->withHeader($header, $value);
		}
		$response = $this->client->sendRequest($request);
		$responseBody = $response->getBody()->getContents();

		return $this->checkStatusCode(
			new HttpResponse(
				statusCode: $response->getStatusCode(),
				body: $responseBody,
				headers: $response->getHeaders()
			)
		);
	}

	private function checkStatusCode(HttpResponse $response): HttpResponse {
		if($response->statusCode < 200 || $response->statusCode >= 300) {
			throw new HttpClientException($response);
		}
		return $response;
	}
}
