<?php
namespace EcommerceUtilities\DHL\Common;

use RuntimeException;
use Throwable;

class DHLApiException extends RuntimeException {
	/** @param list<string> $details */
	public function __construct(
		string $message = '',
		int $code = 0,
		?Throwable $previous = null,
		public readonly ?int $httpStatus = null,
		public readonly array $details = [],
		public readonly bool $definiteRejection = false,
	) {
		parent::__construct($message, $code, $previous);
	}
}
