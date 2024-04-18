<?php

namespace EcommerceUtilities\DHL;

use EcommerceUtilities\DHL\Abstract\UUIDGeneratorInterface;
use EcommerceUtilities\DHL\AddressCorrection\Address;
use EcommerceUtilities\DHL\AddressCorrection\AddressCheckResult;
use EcommerceUtilities\DHL\AFDelivery\AFDeliveryHttpClient;
use EcommerceUtilities\DHL\AFDelivery\AFDeliveryTokenProvider;
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
		$body = json_encode($addressBody, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

		if($body === false) {
			throw new RuntimeException('Failed to encode address body to JSON');
		}

		$response = $this->client->post('verifyAddress/1.0.0', $body, [
			'headers' => [
				'Authorization' => "bearer {$this->tokenProvider->getToken()}",
				'Content-Type' => 'application/json',
			]
		]);

		$data = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

		return new AddressCheckResult(
			requestId: $data['requestId'],
			personMatch: $data['personMatch'],
			householdMatch: $data['householdMatch'],
			addressMatch: $data['addressMatch'],
			bestDeliveryAddress: $data['bestDeliveryAddress'],
			postalCode: $data['postalCode'],
			city: $data['city'],
			district: $data['district'],
			street: $data['street'],
			houseNumber: $data['houseNumber'],
			houseNumberAffix: $data['houseNumberAffix'],
			firstname: $data['firstname'],
			name: $data['name'],
			distributionCode: $data['distributionCode'],
			addressChanged: $data['addressChanged'],
			nameChanged: $data['nameChanged'],
			similarity: $data['similarity']
		);
	}
}
