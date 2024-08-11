<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\ResultTypes;

use EcommerceUtilities\DHL\Services\AddressTools\ReformatAddressResult;
use EcommerceUtilities\DHL\Services\AddressTools\ReformatProbability;

class ReformatPostalAddressResult implements ReformatAddressResult {
	/**
	 * @param string[] $premiseLines
	 * @param string $street
	 * @param string $houseNumber
	 * @param string $postalCode
	 * @param string $city
	 * @param string $country
	 * @param bool $hasChange
	 */
	public function __construct(
		public array $premiseLines,
		public string $street,
		public string $houseNumber,
		public string $postalCode,
		public string $city,
		public string $country,
		public bool $hasChange,
		public bool $isDefect,
		public ReformatProbability $probability
	) {}

	/**
	 * @return string[]
	 */
	public function getPremiseLines(): array {
		return array_filter($this->premiseLines, static fn($premiseLine) => trim($premiseLine) !== '');
	}

	public function getStreet(): string {
		return $this->street;
	}

	public function getHouseNumber(): string {
		return $this->houseNumber;
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
