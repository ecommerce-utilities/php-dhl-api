<?php

namespace EcommerceUtilities\DHL\Common;

use Throwable;

/** An invalid request is rejected before any shipment can be created. */
class DHLRequestValidationException extends DHLApiException {
	/** @param list<string> $details */
	public function __construct(string $message, array $details = [], ?Throwable $previous = null) {
		parent::__construct($message, previous: $previous, details: $details, definiteRejection: true);
	}
}
