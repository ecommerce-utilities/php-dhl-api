<?php

namespace EcommerceUtilities\DHL\Abstract;

interface UUIDGeneratorInterface {
	/**
	 * Generates a UUID in the form of 00000000-0000-0000-0000-000000000000.
	 *
	 * @return string
	 */
	public function generateUUID(): string;
}
