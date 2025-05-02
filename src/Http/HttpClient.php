<?php

namespace EcommerceUtilities\DHL\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @phpstan-type HttpOptions = array{headers?: array<string, string>}
 */
class HttpClient {
	public function __construct(
		private readonly RequestFactoryInterface $requestFactory,
		private readonly ClientInterface $client,
		private readonly ?string $baseUri = null,
	) {}

	/**
	 * @param string $path
	 * @param HttpOptions $options
	 * @return HttpResponse
	 */
	public function get(string $path, array $options = []): HttpResponse {
		$baseUri = $this->genBaseUri($this->baseUri, $path);
		$request = $this->requestFactory->createRequest('GET', $baseUri);
		foreach($options['headers'] ?? [] as $header => $value) {
			$request = $request->withHeader($header, $value);
		}
		$response = $this->client->sendRequest($request);
		return $this->handleResponse($response);
	}

	/**
	 * @param string $path
	 * @param string $body
	 * @param HttpOptions $options
	 * @return HttpResponse
	 */
	public function post(string $path, string $body, array $options = []): HttpResponse {
		$baseUri = $this->genBaseUri($this->baseUri, $path);
		$request = $this->requestFactory->createRequest('POST', $baseUri);
		$request->getBody()->write($body);
		foreach($options['headers'] ?? [] as $header => $value) {
			$request = $request->withHeader($header, $value);
		}
		$response = $this->client->sendRequest($request);
		return $this->handleResponse($response);
	}

	private function handleResponse(ResponseInterface $response): HttpResponse {
		$body = $response->getBody()->getContents();
		/** @var array<string, string[]> $headers */
		$headers = $response->getHeaders();
		$result = new HttpResponse(
			statusCode: $response->getStatusCode(),
			body: $body,
			headers: $headers
		);
		if($result->statusCode < 200 || $result->statusCode >= 300) {
			throw new HttpClientException($result);
		}
		return $result;
	}

	private function genBaseUri(?string $baseUri, string $path): string {
		if($baseUri === null) {
			return $path;
		}
		return rtrim($baseUri, '/') . '/' . ltrim($path, '/');
	}
}
