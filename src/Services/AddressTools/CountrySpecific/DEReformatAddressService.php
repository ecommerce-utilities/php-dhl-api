<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE\DEStringTools;
use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DEReformatAddressService\DEPackstationReformatService;
use EcommerceUtilities\DHL\Services\AddressTools\DEFuzzyAddressMatchService;
use EcommerceUtilities\DHL\Services\AddressTools\ReformatAddressResult;
use EcommerceUtilities\DHL\Services\AddressTools\ReformatProbability;
use EcommerceUtilities\DHL\Services\AddressTools\ResultTypes\ReformatPostalAddressResult;

class DEReformatAddressService {
	public function __construct(
		private readonly DEFuzzyAddressMatchService $fuzzyAddressMatchService,
		private readonly DEPackstationReformatService $packstationReformatService
	) {}

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
		$premiseLines = array_map(static fn($val) => DEStringTools::fixEncoding((string) $val), $premiseLines);
		$street = DEStringTools::fixEncoding($street);
		$houseNumber = DEStringTools::fixEncoding($houseNumber);
		$postalCode = DEStringTools::fixEncoding($postalCode);
		$city = DEStringTools::fixEncoding($city);
		$country = DEStringTools::fixEncoding($country);

		$faulyAddress = new ReformatPostalAddressResult(
			premiseLines: array_values(array_filter($premiseLines, static fn($premiseLine) => $premiseLine !== null)),
			street: $street,
			houseNumber: '',
			postalCode: $postalCode,
			city: $city,
			country: $country,
			hasChange: false,
			isDefect: true,
			probability: ReformatProbability::VeryLow,
			handler: 'ALGO'
		);

		$result = $this->handlePostfiliale(premiseLines: $premiseLines, street: $street, houseNumber: $houseNumber, postalCode: $postalCode, city: $city, country: $country);
		if($result !== null) {
			return $faulyAddress;
		}

		$result = $this->packstationReformatService->handleAddress(premiseLines: $premiseLines, street: $street, houseNumber: $houseNumber, postalCode: $postalCode, city: $city, country: $country);
		if($result !== null) {
			return $faulyAddress;
		}

		$result = $this->fuzzyAddressMatchService->match(
			postalCode: $postalCode,
			city: $city,
			street: $street,
			houseNumber: $houseNumber
		);

		if($result !== null) {
			$streetName = $result->streetLong;
			if(mb_strlen("{$streetName} {$result->houseNumber}", 'UTF-8') > 40) {
				$streetName = $result->streetShort;
			}

			$cityName = !empty($result->cityLong) ? $result->cityLong : $result->city;
			if(mb_strlen($cityName, 'UTF-8') > 40) {
				$cityName = $result->city;
			}

			if($result->score > 0.99) {
				$addresses[] = new ReformatPostalAddressResult(
					premiseLines: array_filter($premiseLines, static fn($premiseLine) => $premiseLine !== null),
					street: $streetName,
					houseNumber: $result->houseNumber ?? '',
					postalCode: $result->postalCode,
					city: $cityName,
					country: $country,
					hasChange: false,
					isDefect: false,
					probability: ReformatProbability::VeryHigh,
					handler: 'ALGO'
				);
			} elseif($result->score > 0.8) {
				$addresses[] = new ReformatPostalAddressResult(
					premiseLines: array_filter($premiseLines, static fn($premiseLine) => $premiseLine !== null),
					street: $streetName,
					houseNumber: $result->houseNumber ?? '',
					postalCode: $result->postalCode,
					city: $cityName,
					country: $country,
					hasChange: true,
					isDefect: false,
					probability: ReformatProbability::High,
					handler: 'ALGO'
				);
			} elseif($result->score > 0.6) {
				$addresses[] = new ReformatPostalAddressResult(
					premiseLines: array_filter($premiseLines, static fn($premiseLine) => $premiseLine !== null),
					street: $streetName,
					houseNumber: $result->houseNumber ?? '',
					postalCode: $result->postalCode,
					city: $cityName,
					country: $country,
					hasChange: true,
					isDefect: false,
					probability: ReformatProbability::Medium,
					handler: 'ALGO'
				);
			}
		}

		$addresses[] = $faulyAddress;

		usort($addresses, static fn(ReformatPostalAddressResult $a, ReformatPostalAddressResult $b) => $b->probability->value <=> $a->probability->value);

		return $addresses[0];
	}

	/**
	 * The difference between packstation and postfiliale is, that for the postfiliale the customer does not neet to enter a dhl customer number.
	 *
	 * @param string[] $premiseLines
	 * @param string $street
	 * @param string $houseNumber
	 * @param string $postalCode
	 * @param string $city
	 * @return ReformatPostalAddressResult|null
	 */
	private function handlePostfiliale(array $premiseLines, string $street, string $houseNumber, string $postalCode, string $city, string $country): ?ReformatPostalAddressResult {
		$fullLine = implode(' ', [...$premiseLines, $street, $houseNumber]);
		if(preg_match('{\\bPostfiliale\\W*?\\d{2,4}}i', $fullLine)) {
			return new ReformatPostalAddressResult(
				premiseLines: array_filter($premiseLines, static fn($premiseLine) => $premiseLine !== null),
				street: $street,
				houseNumber: '',
				postalCode: $postalCode,
				city: $city,
				country: $country,
				hasChange: false,
				isDefect: false,
				probability: ReformatProbability::High,
				handler: 'ALGO'
			);
		}
		return null;
	}
}
