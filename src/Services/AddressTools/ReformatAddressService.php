<?php

namespace EcommerceUtilities\DHL\Services\AddressTools;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DEReformatAddressService;
use EcommerceUtilities\DHL\Services\AddressTools\ResultTypes\ReformatPostalAddressResult;

class ReformatAddressService {
	public function __construct(private readonly DEReformatAddressService $deService) {}

	/**
	 * @param array<string|null> $premiseLines
	 * @param string $street
	 * @param string $houseNumber
	 * @param string $postalCode
	 * @param string $city
	 * @param string $country
	 * @return ReformatAddressResult
	 */
	public function reformat(array $premiseLines, string $street, string $houseNumber, string $postalCode, string $city, string $country): ReformatAddressResult {
		/** @var string[] $premiseLines */
		$premiseLines = array_filter($premiseLines, static fn($premiseLine) => $premiseLine !== null);

		if($country === 'DE') {
			return $this->deService->reformat(
				premiseLines: $premiseLines,
				street: $street,
				houseNumber: $houseNumber,
				postalCode: $postalCode,
				city: $city,
				country: $country
			);
		}

		return new ReformatPostalAddressResult(
			premiseLines: $premiseLines,
			street: $street,
			houseNumber: '',
			postalCode: $postalCode,
			city: $city,
			country: $country,
			hasChange: false,
			isDefect: false,
			probability: ReformatProbability::VeryLow
		);
	}
}
