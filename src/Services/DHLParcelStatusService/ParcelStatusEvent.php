<?php

namespace EcommerceUtilities\DHL\Services\DHLParcelStatusService;

use DateTimeImmutable;

class ParcelStatusEvent {
	public function __construct(
		public DateTimeImmutable $timestamp,
		public string $status,
		public string $text,
		public string $ice,
		public string $ric,
		public string $location,
		public string $country,
		public string $standardEventCode,
		public bool $returning
	) {}
}
