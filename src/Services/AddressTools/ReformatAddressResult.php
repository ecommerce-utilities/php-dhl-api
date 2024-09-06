<?php

namespace EcommerceUtilities\DHL\Services\AddressTools;

interface ReformatAddressResult {
	public function hasChange(): bool;

	public function getProbability(): ReformatProbability;

	public function getHandler(): string;

	public function isDefect(): bool;
}
