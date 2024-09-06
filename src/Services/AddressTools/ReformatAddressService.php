<?php

namespace EcommerceUtilities\DHL\Services\AddressTools;

use EcommerceUtilities\DHL\DHLAddressCorrectionService;
use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DEReformatAddressService;
use EcommerceUtilities\DHL\Services\AddressTools\ResultTypes\ReformatPackstationAddressResult;
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
		$premiseLines = array_filter($premiseLines, static fn($premiseLine) => trim($premiseLine ?? '') !== '');

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
				$dhlPremiseLines = array_values(array_filter([
					implode(', ', array_slice($premiseLines, 1)),
					"$street $houseNumber",
				], static fn($l) => trim($l, ' ,') !== ''));

				try {
					$result = $this->dhlAddressCorrectionService->tryFixUnstructuredAddress(
						name: $name,
						premiseLine1: $dhlPremiseLines[0] ?? '',
						premiseLine2: $dhlPremiseLines[1] ?? '',
						postalCode: $postalCode,
						city: $city,
						countryId: $country
					);

					$any = static fn($v, $predicate) => count(array_filter($v, $predicate)) > 0;

					if($result->addressChanged) {
						if(in_array($result->addressMatch, ['BUILDING', 'STREET', 'STREET_FALLBACK'])) {
							$address = new ReformatPostalAddressResult(
								premiseLines: $result->postNumber !== null && !$any($premiseLines, fn($l) => str_contains($l, $result->postNumber)) ? [...$premiseLines, $result->postNumber] : [...$premiseLines],
								street: $result->street,
								houseNumber: "{$result->houseNumber}{$result->houseNumberAffix}",
								postalCode: $result->postalCode,
								city: $result->city,
								country: $country,
								hasChange: $result->addressChanged,
								isDefect: false,
								probability: match(true) {
									$result->similarity >= .95 => ReformatProbability::High,
									$result->similarity >= .80 => ReformatProbability::Medium,
									default => ReformatProbability::Low,
								},
								handler: 'AFAPI'
							);
						} elseif($result->addressMatch === 'POSTOFFICE') {
							$resPremiseLines = self::removePostNumberFromPremiseLines($result->postNumber, $premiseLines);

							$address = new ReformatPostalAddressResult(
								premiseLines: $resPremiseLines,
								street: $result->street,
								houseNumber: $result->houseNumber,
								postalCode: $result->postalCode,
								city: $result->city,
								country: $country,
								hasChange: $result->addressChanged,
								isDefect: false,
								probability: match(true) {
									$result->similarity >= .95 => ReformatProbability::High,
									$result->similarity >= .80 => ReformatProbability::Medium,
									default => ReformatProbability::Low,
								},
								handler: 'AFAPI'
							);
						} elseif($result->addressMatch === 'PACKSTATION') {
							$resPremiseLines = self::removePostNumberFromPremiseLines($result->postNumber, $premiseLines);

							$address = new ReformatPackstationAddressResult(
								premiseLines: $resPremiseLines,
								packstation: $result->houseNumber,
								customerNumber: $result->postNumber,
								postalCode: $result->postalCode,
								city: $result->city,
								country: $country,
								hasChange: $result->addressChanged,
								isDefect: false,
								probability: match(true) {
									$result->similarity >= .95 => ReformatProbability::High,
									$result->similarity >= .80 => ReformatProbability::Medium,
									default => ReformatProbability::Low,
								},
								handler: 'AFAPI'
							);
						} elseif($result->addressMatch === 'MISS') {
							// Do nothing
						} elseif((string) $result->addressMatch !== 'PACKSTATION') {
							throw new RuntimeException("New, yet unhandled address match: {$result->addressMatch}");
						}
					} // else: The Address was not changed
				} catch (RuntimeException) {
					// Ignore the exception and return the original address
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
			probability: ReformatProbability::VeryLow,
			handler: 'AFAPI'
		);
	}

	/**
	 * @param string[] $lines
	 * @return string[]
	 */
	private static function removePostNumberFromPremiseLines(?string $postNumber, array $lines): array {
		if($postNumber === null) {
			return $lines;
		}
		$result = [];
		foreach($lines as $line) {
			if($line === $postNumber) {
				continue;
			}
			$postNumberDigits = str_split($postNumber);
			$pattern = implode('\\D*', $postNumberDigits);
			$result[] = (string) preg_replace("/$pattern/", '', $line);
		}
		return array_values(array_filter($result, static fn($line) => trim($line) !== ''));
	}
}
