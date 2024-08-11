<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DEReformatAddressService\DEPackstationReformatService;
use EcommerceUtilities\DHL\Services\AddressTools\ReformatAddressResult;
use EcommerceUtilities\DHL\Services\AddressTools\ReformatProbability;
use EcommerceUtilities\DHL\Services\AddressTools\ResultTypes\ReformatPostalAddressResult;

class DEReformatAddressService {
	public function __construct(private readonly DEPackstationReformatService $packstationReformatService) {}

	/**
	 * @param string[] $premiseLines
	 * @param string $street
	 * @param string $houseNumber
	 * @param string $postalCode
	 * @param string $city
	 * @param string $country
	 * @return ReformatAddressResult
	 */
	public function reformat(array $premiseLines, string $street, string $houseNumber, string $postalCode, string $city, string $country): ReformatAddressResult {
		$result = $this->packstationReformatService->handleAddress(premiseLines: $premiseLines, street: $street, houseNumber: $houseNumber, postalCode: $postalCode, city: $city, country: $country);
		if($result !== null) {
			return $result;
		}

		$addresses[] = new ReformatPostalAddressResult(
			premiseLines: array_filter($premiseLines, static fn($premiseLine) => $premiseLine !== null),
			street: $street,
			houseNumber: '',
			postalCode: $postalCode,
			city: $city,
			country: $country,
			hasChange: false,
			isDefect: false,
			probability: ReformatProbability::VeryLow
		);

		usort($addresses, static fn(ReformatPostalAddressResult $a, ReformatPostalAddressResult $b) => $b->probability <=> $a->probability);

		return $addresses[0];
	}
}
