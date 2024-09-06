<?php

namespace EcommerceUtilities\DHL\Services\AddressTools;

use EcommerceUtilities\DHL\DHLAddressCorrectionService;
use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DEReformatAddressService;
use EcommerceUtilities\DHL\Services\AddressTools\ResultTypes\ReformatPostalAddressResult;
use RuntimeException;

class ReformatAddressService {
	public function __construct(
		private readonly DEReformatAddressService $deService,
		private readonly DHLAddressCorrectionService $dhlAddressCorrectionService
	) {}

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

		$address = null;

		if($country === 'DE') {
			$address = $this->deService->reformat(
				premiseLines: $premiseLines,
				street: $street,
				houseNumber: $houseNumber,
				postalCode: $postalCode,
				city: $city,
				country: $country
			);

			if($address->isDefect() || $address->getProbability()->value < ReformatProbability::Medium->value) {
				$name = $premiseLines[0] ?? '';
				$premiseLine = implode(', ', array_slice($premiseLines, 1));

				$result = $this->dhlAddressCorrectionService->tryFixUnstructuredAddress(
					name: $name,
					premiseLine1: $premiseLine,
					premiseLine2: "$street $houseNumber",
					postalCode: $postalCode,
					city: $city,
					countryId: $country
				);

				if($result->addressChanged && $result->addressMatch !== 'MISS') {
					if(in_array($result->addressMatch, ['BUILDING', 'STREET', 'STREET_FALLBACK'])) {
						$address = new ReformatPostalAddressResult(
							premiseLines: $result->postNumber !== null ? [...$premiseLines, $result->postNumber] : $premiseLines,
							street: $result->street,
							houseNumber: "{$result->houseNumber} {$result->houseNumberAffix}",
							postalCode: $result->postalCode,
							city: $result->city,
							country: $country,
							hasChange: $result->addressChanged,
							isDefect: match($result->addressMatch) {
								'BUILDING',
								'STREET',
								'STREET_FALLBACK' => false,
								'MISS' => true,
								default => throw new RuntimeException("Unknown address match type: {$result->addressMatch}")
							},
							probability: match(true) {
								$result->similarity >= .985 => ReformatProbability::High,
								$result->similarity >= .90 => ReformatProbability::Medium,
								default => ReformatProbability::Low,
							}
						);
					} else {
						$address = new ReformatPostalAddressResult(
							premiseLines: $result->postNumber !== null ? [...$premiseLines, $result->postNumber] : $premiseLines,
							street: $result->street,
							houseNumber: "{$result->houseNumber} {$result->houseNumberAffix}",
							postalCode: $result->postalCode,
							city: $result->city,
							country: $country,
							hasChange: $result->addressChanged,
							isDefect: false,
							probability: match(true) {
								$result->similarity >= .985 => ReformatProbability::High,
								$result->similarity >= .90 => ReformatProbability::Medium,
								default => ReformatProbability::Low,
							}
						);
					}
				} else {
					$address = new ReformatPostalAddressResult(
						premiseLines: $premiseLines,
						street: $street,
						houseNumber: $houseNumber,
						postalCode: $postalCode,
						city: $city,
						country: $country,
						hasChange: false,
						isDefect: false,
						probability: ReformatProbability::High
					);
				}
			}
		}

		return $address ?? new ReformatPostalAddressResult(
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
