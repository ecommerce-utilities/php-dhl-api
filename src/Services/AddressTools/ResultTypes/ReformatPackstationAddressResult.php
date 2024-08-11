<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\ResultTypes;

use EcommerceUtilities\DHL\Services\AddressTools\ReformatAddressResult;
use EcommerceUtilities\DHL\Services\AddressTools\ReformatProbability;

class ReformatPackstationAddressResult implements ReformatAddressResult {
	/**
	 * @param string[] $premiseLines
	 * @param int $packstation
	 * @param string|null $customerNumber
	 * @param string $postalCode
	 * @param string $city
	 * @param string $country
	 * @param bool $hasChange
	 */
	public function __construct(
		public array $premiseLines,
		public int $packstation,
		public ?string $customerNumber,
		public string $postalCode,
		public string $city,
		public string $country,
		public bool $hasChange,
		public bool $isDefect,
		public ReformatProbability $probability
	) {}

	/**
	 * @return array<string>
	 */
	public function getPremiseLines(): array {
		return array_filter($this->premiseLines, static fn($premiseLine) => trim($premiseLine) !== '');
	}

	public function getPackstation(): int {
		return $this->packstation;
	}

	public function getCustomerNumber(): ?string {
		return $this->customerNumber;
	}

	public function getPostalCode(): string {
		return $this->postalCode;
	}

	public function getCity(): string {
		return $this->city;
	}

	public function getCountry(): string {
		return $this->country;
	}

	public function hasChange(): bool {
		return $this->hasChange;
	}

	public function getProbability(): ReformatProbability {
		return $this->probability;
	}

	public function isDefect(): bool {
		return $this->isDefect;
	}
}
