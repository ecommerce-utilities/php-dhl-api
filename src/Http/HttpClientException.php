<?php

namespace EcommerceUtilities\DHL\Http;

use RuntimeException;

class HttpClientException extends RuntimeException {
	public function __construct(public readonly HttpResponse $response) {
		parent::__construct("HTTP request failed with status code {$this->response->statusCode}");
	}
}
