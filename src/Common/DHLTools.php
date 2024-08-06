<?php

namespace EcommerceUtilities\DHL\Common;

use JsonException;

class DHLTools {
	/**
	 * @param mixed $input
	 * @return string
	 * @throws JsonException
	 */
	public static function jsonEncode(mixed $input): string {
		return json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	}

	/**
	 * @param string $input
	 * @return mixed
	 * @throws JsonException
	 */
	public static function jsonDecode(string $input): mixed {
		return json_decode($input, true, 512, JSON_THROW_ON_ERROR);
	}
}
