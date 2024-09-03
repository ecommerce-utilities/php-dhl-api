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
}
