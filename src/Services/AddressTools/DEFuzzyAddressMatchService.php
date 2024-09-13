<?php

namespace EcommerceUtilities\DHL\Services\AddressTools;

use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE\DECityTools;
use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE\DEStreetTools;
use EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\StreetTools;
use EcommerceUtilities\DHL\Services\AddressTools\FuzzyAddressMatch\FuzzyAddressMatchResult;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class DEFuzzyAddressMatchService {
	public function __construct(
		private readonly ClientInterface $httpClient,
		private readonly RequestFactoryInterface $requestFactory,
		private readonly StreamFactoryInterface $streamFactory
	) {}

	public function match(string $postalCode, string $city, string $street, ?string $houseNumber): ?FuzzyAddressMatchResult {
		//$streets = $this->allStreetsWithNumbers();
		//print_r($streets);
		//exit;

		[$street, $houseNumber, $addition] = StreetTools::trySeparateStreetAndHouseNumber('DE', $street, $houseNumber);

		$hits = $this->query(fuzzyStreet: $street, fuzzyCity: $city, fuzzyPostalCode: $postalCode);

		// Find exact match of city name and postal code and make a fuzzy match of street name
		foreach($hits as $hit) {
			if($postalCode !== $hit->_source->postalCode) {
				continue;
			}

			foreach(['str46', 'str22'] as $streetKey) {
				foreach(['city', 'cityLong'] as $cityKey) {
					$debug = [
						'originalHouseNumber' => $houseNumber,
						'originalStreet' => $street,
						'foundStreet' => $hit->_source->{$streetKey},
						'originalPostalCode' => $postalCode,
						'foundPostalCode' => $hit->_source->postalCode,
						'originalCity' => $city,
						'foundCity' => $hit->_source->{$cityKey},
					];

					if(!DECityTools::isSameName($city, $hit->_source->{$cityKey})) {
						continue;
					}

					if(!DEStreetTools::isSameStreetName($street, $hit->_source->{$streetKey})) {
						$hnPattern = DEStreetTools::HOUSE_NUMBER;
						if(
							preg_match("{^(.{6,})\\W*($hnPattern)\\W*(.*?)$}", $street, $m) &&
							DEStreetTools::isSameStreetName($m[1], $hit->_source->{$streetKey})
						) {
							$houseNumber = $m[2];
							$additionLines = array_values(array_filter([$addition, $m[3]], static fn($a) => trim($a ?? '') !== ''));
							$addition = implode('; ', $additionLines);
						} else {
							continue;
						}
					}

					return new FuzzyAddressMatchResult(
						streetShort: $hit->_source->str22,
						streetLong: $hit->_source->str46,
						houseNumber: $houseNumber,
						addressAddition: $addition,
						postalCode: $hit->_source->postalCode,
						city: $hit->_source->city,
						cityLong: $hit->_source->cityLong,
						countryId: 'DE',
						score: 1
					);
				}
			}
		}

		$hits = $this->query(fuzzyStreet: $street, fuzzyPostalCode: $postalCode);

		// Find exact match of street and postal code, city is changed
		foreach($hits as $hitIndex => $hit) {
			if($postalCode !== $hit->_source->postalCode) {
				continue;
			}

			foreach(['str46', 'str22'] as $streetKey) {
				if(!DEStreetTools::isSameStreetName(street1: $street, street2: $hit->_source->{$streetKey}, similarityPercent: $hitIndex < 3 ? 85 : 99)) {
					continue;
				}

				return new FuzzyAddressMatchResult(
					streetShort: $hit->_source->str22,
					streetLong: $hit->_source->str46,
					houseNumber: $houseNumber,
					addressAddition: $addition,
					postalCode: $hit->_source->postalCode,
					city: $hit->_source->city,
					cityLong: $hit->_source->cityLong,
					countryId: 'DE',
					score: 0.75
				);
			}
		}

		$hits = $this->query(fuzzyStreet: $street, fuzzyCity: $city);

		// Find exact match of street and city, postal code is changed
		foreach($hits as $hitIndex => $hit) {
			foreach(['str46', 'str22'] as $streetKey) {
				foreach(['city', 'cityLong'] as $cityKey) {
					if(!DEStreetTools::isSameStreetName(street1: $street, street2: $hit->_source->{$streetKey}, similarityPercent: $hitIndex < 3 ? 85 : 99)) {
						continue;
					}

					if(!DECityTools::isSameName(cityName1: $city, cityName2: $hit->_source->{$cityKey}, similarityPercent: $hitIndex < 3 ? 85 : 99)) {
						continue;
					}

					return new FuzzyAddressMatchResult(
						streetShort: $hit->_source->str22,
						streetLong: $hit->_source->str46,
						houseNumber: $houseNumber,
						addressAddition: $addition,
						postalCode: $hit->_source->postalCode,
						city: $hit->_source->city,
						cityLong: $hit->_source->cityLong,
						countryId: 'DE',
						score: match (true) {
							levenshtein($postalCode, $hit->_source->postalCode) >= 3 => 0.5,
							levenshtein($postalCode, $hit->_source->postalCode) >= 2 => 0.7,
							levenshtein($postalCode, $hit->_source->postalCode) >= 1 => 0.8,
							levenshtein($postalCode, $hit->_source->postalCode) >= 0 => 0.9,
							default => 0.4,
						}
					);
				}
			}
		}

		return null;
	}

	/**
	 * @return string[]
	 */
	private function allStreetsWithNumbers(): array {
		$postdata = json_encode([
			"query" => [
				"bool" => [
					"must" => [[
						'match' => [
							'strWithNumber' => true
						],
					]]
				]
			]
		], JSON_THROW_ON_ERROR);

		$request = $this->requestFactory->createRequest('POST', '/addresses/_search');
		$body = $this->streamFactory->createStream($postdata);
		$request = $request->withBody($body);
		$request = $request->withHeader('Content-Type', 'application/json');
		$response = $this->httpClient->sendRequest($request);

		$responseBodyRaw = $response->getBody()->getContents();
		$responseBody = json_decode($responseBodyRaw, false);

		$hits = $responseBody?->hits?->hits ?? [];
		return $hits;
	}

	/**
	 * @param string|null $fuzzyStreet
	 * @param string|null $fuzzyCity
	 * @param string|null $postalCode
	 * @param string|null $fuzzyPostalCode
	 * @return array<object{_id: string, _score: float, _source: object{str46: string, str22: string, postalCode: string, postalCodeFuzzy: string, city: string, cityLong: string}}>
	 * @throws \JsonException
	 * @throws \Psr\Http\Client\ClientExceptionInterface
	 */
	private function query(?string $fuzzyStreet = null, ?string $fuzzyCity = null, ?string $postalCode = null, ?string $fuzzyPostalCode = null): array {
		$match = [];

		if($fuzzyStreet !== null) {
			$match[] = [
				"match" => [
					"str46" => [
						"query" => $fuzzyStreet,
						"minimum_should_match" => "1%"
					]
				]
			];
		}

		if($fuzzyCity !== null) {
			$match[] = [
				"match" => [
					"city" => [
						"query" => $fuzzyCity,
						"minimum_should_match" => "1%"
					]
				]
			];
		}

		if($fuzzyPostalCode !== null) {
			$match[] = [
				"match" => [
					"postalCodeFuzzy" => [
						"query" => $fuzzyPostalCode,
						"minimum_should_match" => "1%",
						"boost" => 5
					]
				]
			];
		}

		$filter = [];

		if($postalCode !== null) {
			$filter[] = [
				"term" => [
					"postalCodeFuzzy" => $postalCode
				]
			];
		}

		$postdata = json_encode([
			"query" => [
				"bool" => [
					"must" => $match,
					"filter" => $filter
				]
			]
		], JSON_THROW_ON_ERROR);

		$request = $this->requestFactory->createRequest('POST', '/addresses/_search');
		$body = $this->streamFactory->createStream($postdata);
		$request = $request->withBody($body);
		$request = $request->withHeader('Content-Type', 'application/json');
		$response = $this->httpClient->sendRequest($request);

		$responseBodyRaw = $response->getBody()->getContents();
		$responseBody = json_decode($responseBodyRaw, false);

		$hits = $responseBody?->hits?->hits ?? [];

		return $hits;
	}
}
