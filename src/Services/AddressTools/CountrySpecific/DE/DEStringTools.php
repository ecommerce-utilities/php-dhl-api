<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE;

class DEStringTools {
	/**
	 * Applied dot-shortening to the given strings.
	 *
	 * Example 1:
	 * $s1 = "Musterstraße 12-13"
	 * $s2 = "Musterstr. 12-13"
	 * Result in [Musterstr. 12-13, Musterstr. 12-13]
	 *
	 * Example 2:
	 * $s1 = "Kirchentellinsfurterstraße 12-13"
	 * $s2 = "K.furterstraße 12-13"
	 * Result in [K.furterstraße 12-13, K.furterstraße 12-13]
	 *
	 * @param string $s1
	 * @param string $s2
	 * @return array{string, string}
	 */
	public static function applyDotShortening(string $s1, string $s2): array {
		$prep = static function($s) {
			/** @var string[] $p */
			$p = preg_split('{([\\p{L}\\p{N}.]+)}ui', $s, -1, PREG_SPLIT_DELIM_CAPTURE);

			if(preg_match('{^([\\p{L}\\p{N}.]+)$}', $p[0])) {
				$p = ['', ...$p];
			}

			$result = [];
			for($i = 0, $c = count($p); $i < $c; $i += 2) {
				$result[] = [$p[$i], $p[$i + 1] ?? ''];
			}

			return $result;
		};

		$p1 = $prep($s1);
		$p2 = $prep($s2);

		$result1 = $result2 = '';

		for($i = 0, $c = max(count($p1), count($p2)); $i < $c; $i++) {
			$p1xv = $p1[$i][1] ?? '';
			$p2xv = $p2[$i][1] ?? '';
			$smaller = mb_strlen(strtr($p1xv, ['.' => '']), 'UTF-8') < mb_strlen(strtr($p2xv, ['.' => '']), 'UTF-8');
			[$p1x, $p2x] = $smaller ? [$p1[$i] ?? ['', ''], $p2[$i] ?? ['', '']] : [$p2[$i] ?? ['', ''], $p1[$i] ?? ['', '']];

			if(str_contains($p1x[1], '.')) {
				$pattern = strtr($p1x[1], ['.' => '.+?']);
				if(preg_match("/^$pattern$/ui", $p2x[1])) {
					$result1 .= $p1x[0] . $p2x[1];
					$result2 .= $p2x[0] . $p2x[1];
				}
			} else {
				$result1 .= $p1x[0] . $p1x[1];
				$result2 .= $p2x[0] . $p2x[1];
			}
		}

		return [$result1, $result2];
	}

	/**
	 * @param string $input
	 * @return string
	 */
	public static function fixEncoding(string $input): string {
		static $cache = null;
		$cache ??= [
			// Convert german HTML encoded umlauts to single encoded umlauts using \u{####}-Encoding
			'&Auml;'   => "\xC4",
			'&auml;'   => "\xE4",
			'&Ouml;'   => "\xD6",
			'&ouml;'   => "\xF6",
			'&Uuml;'   => "\xDC",
			'&uuml;'   => "\xFC",
			'&szlig;'  => "\xDF",
			'&Agrave;' => "\xC0",
			'&agrave;' => "\xE0",
			'&Acirc;'  => "\xC2",
			'&acirc;'  => "\xE2",
			'&Atilde;' => "\xC3",
			'&atilde;' => "\xE3",
			'&Aacute;' => "\xC1",
			'&aacute;' => "\xE1",
			'&Aring;'  => "\xC5",
			'&aring;'  => "\xE5",
			'&Egrave;' => "\xC8",
			'&egrave;' => "\xE8",
			'&Ecirc;'  => "\xCA",
			'&ecirc;'  => "\xEA",
			'&Eacute;' => "\xC9",
			'&eacute;' => "\xE9",
			'&Igrave;' => "\xCC",
			'&igrave;' => "\xEC",
			'&Icirc;'  => "\xCE",
			'&icirc;'  => "\xEE",
			'&Iacute;' => "\xCD",
			'&iacute;' => "\xED",
			'&Ograve;' => "\xD2",
			'&ograve;' => "\xF2",
			'&Ocirc;'  => "\xD4",
			'&ocirc;'  => "\xF4",
			'&Otilde;' => "\xD5",
			'&otilde;' => "\xF5",
			'&Oacute;' => "\xD3",
			'&oacute;' => "\xF3",
			'&Ugrave;' => "\xD9",
			'&ugrave;' => "\xF9",
			'&Ucirc;'  => "\xDB",
			'&ucirc;'  => "\xFB",
			'&Uacute;' => "\xDA",
			'&uacute;' => "\xFA",

			// Convert german UTF8 double encoded SZ to single encoded umlauts, using \u{####}-Encoding
			"\xC3\x84" => "\xC4",
			"\xC3\xA4" => "\xE4",
			"\xC3\x96" => "\xD6",
			"\xC3\xB6" => "\xF6",
			"\xC3\x9C" => "\xDC",
			"\xC3\xBC" => "\xFC",
			"\xC3\x9F" => "\xDF",
			"\xC3\x80" => "\xC0",
			"\xC3\xA0" => "\xE0",
			"\xC3\x82" => "\xC2",
			"\xC3\xA2" => "\xE2",
			"\xC3\x83" => "\xC3",
			"\xC3\xA3" => "\xE3",
			"\xC3\x81" => "\xC1",
			"\xC3\xA1" => "\xE1",
			"\xC3\x85" => "\xC5",
			"\xC3\xA5" => "\xE5",
			"\xC3\x88" => "\xC8",
			"\xC3\xA8" => "\xE8",
			"\xC3\x8A" => "\xCA",
			"\xC3\xAA" => "\xEA",
			"\xC3\x89" => "\xC9",
			"\xC3\xA9" => "\xE9",
			"\xC3\x8C" => "\xCC",
			"\xC3\xAC" => "\xEC",
			"\xC3\x8E" => "\xCE",
			"\xC3\xAE" => "\xEE",
			"\xC3\x8D" => "\xCD",
			"\xC3\xAD" => "\xED",
			"\xC3\x92" => "\xD2",
			"\xC3\xB2" => "\xF2",
			"\xC3\x94" => "\xD4",
			"\xC3\xB4" => "\xF4",
			"\xC3\x95" => "\xD5",
			"\xC3\xB5" => "\xF5",
			"\xC3\x93" => "\xD3",
			"\xC3\xB3" => "\xF3",
			"\xC3\x99" => "\xD9",
			"\xC3\xB9" => "\xF9",
			"\xC3\x9B" => "\xDB",
			"\xC3\xBB" => "\xFB",
			"\xC3\x9A" => "\xDA",
			"\xC3\xBA" => "\xFA",
		];

		return strtr($input, $cache);
	}
}
