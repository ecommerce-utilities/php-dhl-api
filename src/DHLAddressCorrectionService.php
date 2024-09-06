<?php

namespace EcommerceUtilities\DHL;

use EcommerceUtilities\DHL\Abstract\UUIDGeneratorInterface;
use EcommerceUtilities\DHL\AddressCorrection\Address;
use EcommerceUtilities\DHL\AddressCorrection\AddressCheckResult;
use EcommerceUtilities\DHL\AFDelivery\AFDeliveryHttpClient;
use EcommerceUtilities\DHL\AFDelivery\AFDeliveryTokenProvider;
use EcommerceUtilities\DHL\Common\DHLTools;
use RuntimeException;

class DHLAddressCorrectionService {
	public function __construct(
		private readonly AFDeliveryTokenProvider $tokenProvider,
		private readonly AFDeliveryHttpClient $client,
		private readonly UUIDGeneratorInterface $uuidGenerator,
	) {}

	public function tryFixAddress(Address $address): AddressCheckResult {
		$addressBody = [
			'requestId' => $this->uuidGenerator->generateUUID(),
			'firstname' => $address->firstname,
			'name' => $address->name,
			'postalCode' => $address->postalCode,
			'city' => $address->city,
			'district' => $address->district,
			'street' => $address->street,
			'houseNumber' => $address->houseNumber
		];

		/** @var string|false $body */
		$body = DHLTools::jsonEncode($addressBody);

		if($body === false) {
			throw new RuntimeException('Failed to encode address body to JSON');
		}

		$response = $this->client->post('verifyAddress/1.0.0', $body, [
			'headers' => [
				'Authorization' => "bearer {$this->tokenProvider->getToken()}",
				'Content-Type' => 'application/json',
			]
		]);

		$data = DHLTools::jsonDecode($response->body);

		return new AddressCheckResult(
			requestId: $data['requestId'],
			personMatch: $data['personMatch'],
			householdMatch: $data['householdMatch'],
			addressMatch: $data['addressMatch'],
			bestDeliveryAddress: $data['bestDeliveryAddress'],
			postNumber: $data['postnumber'] ?? null,
			firstname: $data['firstname'],
			name: $data['name'],
			street: $data['street'],
			houseNumber: $data['houseNumber'],
			houseNumberAffix: $data['houseNumberAffix'],
			postalCode: $data['postalCode'],
			city: $data['city'],
			district: $data['district'],
			distributionCode: $data['distributionCode13'],
			distributionCode13: $data['distributionCode'],
			addressChanged: $data['addressChanged'],
			nameChanged: $data['nameChanged'],
			similarity: $data['similarity']
		);
	}

	public function tryFixUnstructuredAddress(?string $name, string $premiseLine1, string $premiseLine2, string $postalCode, string $city, string $countryId): AddressCheckResult {
		if($countryId !== 'DE') {
			throw new RuntimeException('Only German addresses are supported at the moment');
		}

		$requestBody = [
			'requestId' => $this->uuidGenerator->generateUUID(),
			'name' => $name,
			'premiseLine1' => $premiseLine1,
			'premiseLine2' => $premiseLine2,
			'postalCode' => $postalCode,
			'city' => $city,
			'config' => ''
		];

		$body = DHLTools::jsonEncode($requestBody);

		$response = $this->client->post('verifyUnstructuredAddress/1.0.0', $body, [
			'headers' => [
				'Authorization' => "bearer {$this->tokenProvider->getToken()}",
				'Content-Type' => 'application/json',
			]
		]);

		$data = DHLTools::jsonDecode($response->body);

		$result = [];
		foreach(['requestId', 'personMatch', 'householdMatch', 'addressMatch', 'bestDeliveryAddress', 'postalCode', 'city', 'district', 'street', 'houseNumber', 'houseNumberAffix', 'firstname', 'name', 'distributionCode', 'distributionCode13', 'addressChanged', 'nameChanged', 'similarity', 'postnumber'] as $key) {
			$data[$key] ??= null;
			$result[$key] = $data[$key];
			unset($data[$key]);
		}

		if(count($data)) {
			throw new RuntimeException(sprintf("Found excess fields: %s", json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
		}

		return new AddressCheckResult(
			requestId: $result['requestId'],
			personMatch: $result['personMatch'],
			householdMatch: $result['householdMatch'],
			addressMatch: $result['addressMatch'],
			bestDeliveryAddress: $result['bestDeliveryAddress'],
			postNumber: $result['postnumber'],
			firstname: $result['firstname'],
			name: $result['name'],
			street: $result['street'],
			houseNumber: $result['houseNumber'],
			houseNumberAffix: $result['houseNumberAffix'],
			postalCode: $result['postalCode'],
			city: $result['city'],
			district: $result['district'],
			distributionCode: $result['distributionCode'],
			distributionCode13: $result['distributionCode13'],
			addressChanged: $result['addressChanged'],
			nameChanged: $result['nameChanged'],
			similarity: $result['similarity']
		);
	}
}
