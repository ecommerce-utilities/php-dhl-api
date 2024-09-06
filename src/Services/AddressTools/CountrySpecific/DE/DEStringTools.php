<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DE;

class DEStringTools {
	/**
	 * @deprecated
	 *
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
			'&Auml;'   => "\u{00C4}",
			'&auml;'   => "\u{00E4}",
			'&Ouml;'   => "\u{00D6}",
			'&ouml;'   => "\u{00F6}",
			'&Uuml;'   => "\u{00DC}",
			'&uuml;'   => "\u{00FC}",
			'&szlig;'  => "\u{00DF}",
			'&Agrave;' => "\u{00C0}",
			'&agrave;' => "\u{00E0}",
			'&Acirc;'  => "\u{00C2}",
			'&acirc;'  => "\u{00E2}",
			'&Atilde;' => "\u{00C3}",
			'&atilde;' => "\u{00E3}",
			'&Aacute;' => "\u{00C1}",
			'&aacute;' => "\u{00E1}",
			'&Aring;'  => "\u{00C5}",
			'&aring;'  => "\u{00E5}",
			'&Egrave;' => "\u{00C8}",
			'&egrave;' => "\u{00E8}",
			'&Ecirc;'  => "\u{00CA}",
			'&ecirc;'  => "\u{00EA}",
			'&Eacute;' => "\u{00C9}",
			'&eacute;' => "\u{00E9}",
			'&Igrave;' => "\u{00CC}",
			'&igrave;' => "\u{00EC}",
			'&Icirc;'  => "\u{00CE}",
			'&icirc;'  => "\u{00EE}",
			'&Iacute;' => "\u{00CD}",
			'&iacute;' => "\u{00ED}",
			'&Ograve;' => "\u{00D2}",
			'&ograve;' => "\u{00F2}",
			'&Ocirc;'  => "\u{00D4}",
			'&ocirc;'  => "\u{00F4}",
			'&Otilde;' => "\u{00D5}",
			'&otilde;' => "\u{00F5}",
			'&Oacute;' => "\u{00D3}",
			'&oacute;' => "\u{00F3}",
			'&Ugrave;' => "\u{00D9}",
			'&ugrave;' => "\u{00F9}",
			'&Ucirc;'  => "\u{00DB}",
			'&ucirc;'  => "\u{00FB}",
			'&Uacute;' => "\u{00DA}",
			'&uacute;' => "\u{00FA}",

			// Convert german UTF8 double encoded SZ to single encoded umlauts, using \u{####}-Encoding
			"\xC3\x83\xC2\x84" => "\u{00C4}",
			"\xC3\x83\xC2\xA4" => "\u{00E4}",
			"\xC3\x83\xC2\x96" => "\u{00D6}",
			"\xC3\x83\xC2\xB6" => "\u{00F6}",
			"\xC3\x83\xC2\x9C" => "\u{00DC}",
			"\xC3\x83\xC2\xBC" => "\u{00FC}",
			"\xC3\x83\xC2\x9F" => "\u{00DF}",
			"\xC3\x83\xC2\x80" => "\u{00C0}",
			"\xC3\x83\xC2\xA0" => "\u{00E0}",
			"\xC3\x83\xC2\x82" => "\u{00C2}",
			"\xC3\x83\xC2\xA2" => "\u{00E2}",
			"\xC3\x83\xC2\x83" => "\u{00C3}",
			"\xC3\x83\xC2\xA3" => "\u{00E3}",
			"\xC3\x83\xC2\x81" => "\u{00C1}",
			"\xC3\x83\xC2\xA1" => "\u{00E1}",
			"\xC3\x83\xC2\x85" => "\u{00C5}",
			"\xC3\x83\xC2\xA5" => "\u{00E5}",
			"\xC3\x83\xC2\x88" => "\u{00C8}",
			"\xC3\x83\xC2\xA8" => "\u{00E8}",
			"\xC3\x83\xC2\x8A" => "\u{00CA}",
			"\xC3\x83\xC2\xAA" => "\u{00EA}",
			"\xC3\x83\xC2\x89" => "\u{00C9}",
			"\xC3\x83\xC2\xA9" => "\u{00E9}",
			"\xC3\x83\xC2\x8C" => "\u{00CC}",
			"\xC3\x83\xC2\xAC" => "\u{00EC}",
			"\xC3\x83\xC2\x8E" => "\u{00CE}",
			"\xC3\x83\xC2\xAE" => "\u{00EE}",
			"\xC3\x83\xC2\x8D" => "\u{00CD}",
			"\xC3\x83\xC2\xAD" => "\u{00ED}",
			"\xC3\x83\xC2\x92" => "\u{00D2}",
			"\xC3\x83\xC2\xB2" => "\u{00F2}",
			"\xC3\x83\xC2\x94" => "\u{00D4}",
			"\xC3\x83\xC2\xB4" => "\u{00F4}",
			"\xC3\x83\xC2\x95" => "\u{00D5}",
			"\xC3\x83\xC2\xB5" => "\u{00F5}",
			"\xC3\x83\xC2\x93" => "\u{00D3}",
			"\xC3\x83\xC2\xB3" => "\u{00F3}",
			"\xC3\x83\xC2\x99" => "\u{00D9}",
			"\xC3\x83\xC2\xB9" => "\u{00F9}",
			"\xC3\x83\xC2\x9B" => "\u{00DB}",
			"\xC3\x83\xC2\xBB" => "\u{00FB}",
			"\xC3\x83\xC2\x9A" => "\u{00DA}",
			"\xC3\x83\xC2\xBA" => "\u{00FA}"
		];

		return strtr($input, $cache);
	}
}
