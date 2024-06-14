<?php

namespace EcommerceUtilities\DHL\Http;

class HttpResponse {
	public function __construct(
		public readonly int $statusCode,
		public readonly string $body,
		/** @var array<string, string[]> $headers */
		public readonly array $headers,
	) {}
}
