<?php

namespace EcommerceUtilities\DHL\Services\DHLParcelStatusService;

use DateTimeImmutable;

class ParcelStatus {
	public function __construct(
		public ?string $reference,
		public string $name,
		public string $pieceIdentifier,
		public DateTimeImmutable $buildTime,
		public string $pieceId,
		public ?string $leitcode,
		public string $pslzNr,
		public bool $orderPreferredDeliveryDay,
		/** @var string[] */
		public array $searchedPieceCode,
		public int $pieceStatus,
		public int $identifierType,
		public ?string $recipientName,
		public ?string $recipientId,
		public ?string $recipientIdText,
		public ?string $panRecipientName,
		public ?string $streetName,
		public ?string $houseNumber,
		public ?string $cityName,
		public DateTimeImmutable $lastEventTimestamp,
		public ?string $shipmentType,
		public ?string $statusNext,
		public string $status,
		public int $errorStatus,
		public int $deliveryEventFlag,
		public ?string $upu,
		public int $internationalFlag,
		public string $pieceCode,
		public ?string $matchcode,
		public ?string $domesticId,
		public ?string $airwayBillNumber,
		public string $ice,
		public string $ric,
		public string $division,
		public string $destCountry,
		public string $originCountry,
		public string $productCode,
		public string $productName,
		public ?string $searchedRefNr,
		public string $standardEventCode,
		public ?string $panRecipientStreet,
		public ?string $panRecipientCity,
		public ?string $eventCountry,
		public ?string $eventLocation,
		public ?string $preferredDeliveryDay,
		public ?string $preferredDeliveryTimeframeFrom,
		public ?string $preferredDeliveryTimeframeTo,
		public ?string $preferredTimeframeRefusedText,
		public float $shipmentLength,
		public float $shipmentWidth,
		public float $shipmentHeight,
		public float $shipmentWeight,
		/** @var ParcelStatusEvent[] */
		public array $events = []
	) {}

	public function getShippingDate(): ?DateTimeImmutable {
		$events = $this->events;
		usort($events, static fn(ParcelStatusEvent $a, ParcelStatusEvent $b) => $a->timestamp->getTimestamp() - $b->timestamp->getTimestamp());
		$events = array_filter($events, static fn(ParcelStatusEvent $event) => $event->ice !== 'PARCV');
		$events = array_values($events);
		return ($events[0] ?? null)?->timestamp;
	}
}
