<?php

namespace EcommerceUtilities\DHL\Services\DHLShipmentService;

use EcommerceUtilities\DHL\Common\DHLCountryCodes;

class DHLShippingService {
	public function __construct(
		private readonly string $myCountryId,
		private readonly string $productKeyNational,
		private readonly string $productKeyInternational,
		private readonly string $billingNumberNational,
		private readonly string $billingNumberInternational,
		private readonly string $profile = 'STANDARD_GRUPPENPROFIL',
		private readonly string $printFormat = '910-300-600',
		private readonly string $documentFormat = 'PDF',
		private readonly string $acceptLanguage = 'en-US',
		private readonly bool $mustEncode = false,
	) {}

	public function getProductKeyDomestic(string $countryCode): string {
		return DHLCountryCodes::equals($countryCode, $this->myCountryId)
			? $this->productKeyNational
			: $this->productKeyInternational;
	}

	public function getBillingNumberDomestic(string $countryCode): string {
		return DHLCountryCodes::equals($countryCode, $this->myCountryId)
			? $this->billingNumberNational
			: $this->billingNumberInternational;
	}

	public function getProfile(): string {
		return $this->profile;
	}

	public function getPrintFormat(): string {
		return $this->printFormat;
	}

	public function getDocumentFormat(): string {
		return $this->documentFormat;
	}

	public function getAcceptLanguage(): string {
		return $this->acceptLanguage;
	}

	public function mustEncode(): bool {
		return $this->mustEncode;
	}
}
