<?php

namespace EcommerceUtilities\DHL\Services\AddressTools\CountrySpecific\DEReformatAddressService;

use EcommerceUtilities\DHL\Services\AddressTools\ReformatProbability;
use EcommerceUtilities\DHL\Services\AddressTools\ResultTypes\ReformatPackstationAddressResult;

class DEPackstationReformatService {
	/**
	 * @param array<string|null> $premiseLines
	 * @param string $street
	 * @param string $houseNumber
	 * @param string $postalCode
	 * @param string $city
	 * @param string $country
	 * @return null|ReformatPackstationAddressResult
	 */
	public function handleAddress(array $premiseLines, string $street, string $houseNumber, string $postalCode, string $city, string $country): ?ReformatPackstationAddressResult {
		/** @var string[] $premiseLines */
		$premiseLines = array_filter($premiseLines, static fn($line) => trim($line ?? '') !== '');

		$street = rtrim("$street $houseNumber");

		$result = new ReformatPackstationAddressResult(
			premiseLines: $premiseLines,
			packstation: 0,
			customerNumber: null,
			postalCode: $postalCode,
			city: $city,
			country: $country,
			hasChange: true,
			isDefect: false,
			probability: ReformatProbability::VeryHigh
		);

		$allSpellings = $this->generateAllPackstationSpellings();

		//$str = 'Packststaion';
		//$str = (string) preg_replace(sprintf('{(%s)}i', implode('|', $allSpellings)), 'Packstation', $str);
		//printf("%s\n", $str);
		//exit;

		//region Normalize Packstation and according numbers
		$lines = implode("\n", [...$premiseLines, $street]);

		$lines = (string) preg_replace(sprintf('{(%s)}miu', implode('|', $allSpellings)), 'Packstation', $lines);
		$lines = (string) preg_replace('{(?<!\\d)(\\d{2,3})\\W+(\\d{3})\\W+(\\d{3,4})(?!\\d)}mu', '$1$2$3', $lines);
		$lines = (string) preg_replace('{(?<!\\d)(\\d{4,5})\\W+(\\d{4})(?!\\d)}mu', '$1$2$3', $lines);

		//region Check if the adress is not meant to be a packstation ("KEINE PACKSTATION")
		if(preg_match("{\\bKeine\\W+Packstation\\b}ui", $lines)) {
			return null;
		}
		//endregion

		$premiseLines = explode("\n", $lines);
		$premiseLines = array_filter($premiseLines, static fn($line) => trim($line) !== '');
		//endregion

		// Exact match "Packstation <PostNo>"
		$lines = implode("\n", [...$premiseLines, $street]);
		$pattern = "{({$this->getPackstationSpellingsPattern()})(\\W+)({$this->getFilialPattern()})}mu";
		if(preg_match($pattern, $lines, $m)) {
			$result->packstation = (int) $m[3];
			$lines = (string) preg_replace($pattern, '$2', $lines);

			$pattern = "((?<!\\d)(?:\\d[\\s\\d]{4,11}\\d|\\d{6,10})(?!\\d))";
			if(preg_match($pattern, $lines, $m)) {
				$result->customerNumber = (string) preg_replace('{\\s+}', '', $m[0]);
				if(!preg_match('{^\\d{6,11}$}', $result->customerNumber)) {
					$result->customerNumber = null;
				} else {
					$lines = (string) preg_replace($pattern, '', $lines);

					$premiseLines = explode("\n", $lines);
					$premiseLines = array_filter($premiseLines, static fn($line) => trim($line) !== '');
					$result->premiseLines = $premiseLines;

					return $this->postProcess($result);
				}
			}
		}

		if(preg_match("{(?:Packstation|Postfiliale).*?({$this->getFilialPattern()})}i", $street, $m)) {
			$result->packstation = (int) $m[1];

			$street = (string) preg_replace("{({$this->getPackstationSpellingsPattern()})(.*?)({$this->getFilialPattern()})}i", '$2', $street);

			// Is the street "Packstation <CustomerNo> <PostNo>"
			if(preg_match("{{$this->getPackstationSpellingsPattern()}\\W+({$this->getCustomerNoPattern()})\\W+({$this->getFilialPattern()})}ui", $street, $m)) {
				$result->customerNumber = $m[1];
				$result->packstation = (int) $m[2];
				$result->probability = ReformatProbability::VeryHigh;
				return $this->postProcess($result);
			}

			// Is the street "Packstation <PostNo> <CustomerNo>"
			if(preg_match("{{$this->getPackstationSpellingsPattern()}\\W+({$this->getFilialPattern()})\\W+({$this->getCustomerNoPattern()})}ui", $street, $m)) {
				$result->customerNumber = $m[2];
				$result->packstation = (int) $m[1];
				$result->probability = ReformatProbability::VeryHigh;
				return $this->postProcess($result);
			}

			// Is there a dhl customer number in the street?
			if(preg_match('{(?:Kundennumm?er|Postnumm?er|Post\\W*nr)\\W*(?<!\\d)(\\d{6,10}(?!\\d))}ui', $street, $m)) {
				$result->customerNumber = $m[1];
				$result->probability = ReformatProbability::VeryHigh;
				return $this->postProcess($result);
			}

			// Is there a dhl customer number in the street?
			if(preg_match('{(?<!\\d)(\\d{6,10}(?!\\d))}', $street, $m)) {
				$result->customerNumber = $m[1];
				$result->probability = ReformatProbability::High;
				return $this->postProcess($result);
			}

			// Is there a dhl customer number in the street like 123 456 789?
			if(preg_match('{(?<!\\d)(\\d{6,10}(?!\\d))}', (string) preg_replace('{\\s+}', '', $street), $m)) {
				$result->customerNumber = $m[1];
				$result->probability = ReformatProbability::High;
				return $this->postProcess($result);
			}

			// Is there a dhl customer number in a premise line?
			foreach($premiseLines as $idx => $premiseLine) {
				if(preg_match('{((?<!\\d)\\d{6,10}(?!\\d))}', $premiseLine, $m)) {
					$premiseLines[$idx] = (string) preg_replace('{(?<!\\d)(\\d{6,10}(?!\\d))}', '', $premiseLine);
					$result->premiseLines = $premiseLines;
					$result->customerNumber = $m[1];
					$result->probability = ReformatProbability::High;
					return $this->postProcess($result);
				}
			}

			// Is there a dhl customer number in a premise line like 123 456 789?
			foreach($premiseLines as $idx => $premiseLine) {
				if(preg_match('{((?<!\\d)\\d{6,10}(?!\\d))}', (string) preg_replace('{\\s+}', '', $premiseLine), $m)) {
					$premiseLine = (string) preg_replace('{(\\d)\\s+(\\d)}', '$1$2', $premiseLine);
					$premiseLines[$idx] = (string) preg_replace('{(?<!\\d)(\\d{6,10}(?!\\d))}', '', $premiseLine);
					$result->premiseLines = $premiseLines;
					$result->customerNumber = $m[1];
					$result->probability = ReformatProbability::High;
					return $this->postProcess($result);
				}
			}

			if(preg_match('{Packstation}i', $street, $m)) {
				$result->probability = ReformatProbability::VeryHigh;
				return $this->postProcess($result);
			}

			$result->probability = ReformatProbability::Low;
			$result->isDefect = true;
			return $this->postProcess($result);
		}

		$names = implode(' ', $premiseLines);

		if(preg_match("{{$this->getPackstationSpellingsPattern()}.*?({$this->getFilialPattern()})}i", $names, $m)) {
			$result->packstation = (int) $m[1];

			foreach($premiseLines as $idx => $premiseLine) {
				$premiseLines[$idx] = (string) preg_replace("{({$this->getPackstationSpellingsPattern()})(.*?)({$this->getFilialPattern()})}", '$2', $premiseLine);
				$result->premiseLines = $premiseLines;
			}

			foreach($premiseLines as $idx => $premiseLine) {
				if(preg_match("{({$this->getCustomerNoPattern()})}", $premiseLine, $m)) {
					$premiseLines[$idx] = (string) preg_replace("{({$this->getCustomerNoPattern()})}", '', $premiseLine);
					$result->premiseLines = $premiseLines;
					$result->customerNumber = $m[1];
					$result->probability = ReformatProbability::VeryHigh;
					return $this->postProcess($result);
				}
			}

			foreach([...$premiseLines, $street] as $idx => $premiseLine) {
				if(preg_match("{({$this->getCustomerNoPattern()})}", $premiseLine, $m)) {
					$premiseLines[$idx] = (string) preg_replace("{({$this->getCustomerNoPattern()})}", '', $premiseLine);
					$result->premiseLines = $premiseLines;
					$result->customerNumber = $m[1];
					$result->probability = ReformatProbability::VeryHigh;
					return $this->postProcess($result);
				}
			}

			$result->probability = ReformatProbability::High;
			$result->isDefect = true;
			return $this->postProcess($result);
		}

		$premiseLinesAndStreetStr = implode(' ', [...$premiseLines, $street]);
		foreach([['p', 'c', 'f'], ['p', 'f', 'c'], ['c', 'p', 'f'], ['f', 'p', 'c'], ['c', 'f', 'p'], ['f', 'c', 'p']] as $letters) {
			$pattern = [];
			$ix = 1;
			$fi = 0;
			$ci = 0;
			foreach($letters as $letter) {
				if($letter === 'p') {
					$pattern[] = "(?:{$this->getPackstationSpellingsPattern()})";
				} elseif($letter === 'f') {
					$pattern[] = "({$this->getFilialPattern()})";
					$fi = $ix;
					$ix++;
				} elseif($letter === 'c') {
					$pattern[] = "({$this->getCustomerNoPattern()})";
					$ci = $ix;
					$ix++;
				}
			}
			$fullPattern = implode(".*?", $pattern);
			if(preg_match(sprintf('{%s}i', $fullPattern), $premiseLinesAndStreetStr, $m)) {
				$result->customerNumber = $m[$ci];
				$result->packstation = (int) $m[$fi];
				$result->probability = ReformatProbability::VeryHigh;
				return $this->postProcess($result);
			}
		}

		if(preg_match("{{$this->getPackstationSpellingsPattern()}}", $premiseLinesAndStreetStr)) {
			$result->probability = ReformatProbability::High;
			$result->isDefect = true;
			return $result;
		}

		return null;
	}

	private function postProcess(ReformatPackstationAddressResult $result): ReformatPackstationAddressResult {
		$result->premiseLines = array_filter($result->premiseLines, static fn($line) => trim($line) !== '');
		$lines = implode("\n", $result->premiseLines);
		$lines = (string) preg_replace("{({$this->getPackstationSpellingsPattern()})}", '', $lines);
		$lines = (string) preg_replace("{((?<!\\d){$result->customerNumber}(?!\\d))}", '', $lines);
		$lines = (string) preg_replace("{((?<!\\d){$result->packstation}(?!\\d))}", '', $lines);

		$result->premiseLines = explode("\n", $lines);
		$result->premiseLines = array_map($this->postProcessLine(...), $result->premiseLines);
		$result->premiseLines = array_filter($result->premiseLines, static fn($line) => trim($line) !== '');

		if((int) $result->customerNumber === 0) {
			$result->isDefect = true;
		}

		if($result->packstation === 0) {
			$result->isDefect = true;
		}

		return $result;
	}

	private function postProcessLine(string $line): string {
		$line = (string) preg_replace('{\\W*(?:(?:DHL|Post)\\W*)?(Nr|Kunden\\W*numm?er|Post\\W*numm?er|Post\\W*(?:nr|no|nummer))(?!\\p{L})\\W*}ui', ' ', $line);
		$line = (string) preg_replace("{\\W*({$this->getPackstationSpellingsPattern()})\\W*({$this->getFilialPattern()})}ui", ' ', $line);
		$line = (string) preg_replace('{^\\W*(.*?)\\W*$}u', '$1', $line);
		return trim($line);
	}

	private function getPackstationSpellingsPattern(): string {
		return "(?:DHL\\W*)?(?:Packstation|Postfiliale)";
	}

	private function getFilialPattern(): string {
		return '(?<!\\d)\\d{3,4}(?!\\d)';
	}

	private function getCustomerNoPattern(): string {
		return '(?<!\\d)\\d{6,10}(?!\\d)';
	}

	/**
	 * @return string[]
	 */
	private function generateAllPackstationSpellings(): array {
		$result = [];
		$string = 'pack-station';

		// All possible combinations of the word "Packstation" with 1 missing character
		for($i = 0, $l = strlen($string); $i < $l; $i++) {
			$pattern = substr($string, 0, $i + 1) . '?' . substr($string, $i + 1);
			$result[] = $pattern;
		}

		// All possible combinations of the word "Packstation" with 2 missing characters
		for($i = 0, $l = strlen($string); $i < $l; $i++) {
			for($j = $i + 1; $j < $l; $j++) {
				$pattern = substr($string, 0, $i + 1) . '?' . substr($string, $i + 1, $j - $i) . '?' . substr($string, $j + 1);
				$result[] = $pattern;
			}
		}

		// All possible combinations of the word "Packstation" with 3 missing characters
		for($i = 0, $l = strlen($string); $i < $l; $i++) {
			for($j = $i + 1; $j < $l; $j++) {
				for($k = $j + 1; $k < $l; $k++) {
					$pattern = substr($string, 0, $i + 1) . '?' . substr($string, $i + 1, $j - $i) . '?' . substr($string, $j + 1, $k - $j) . '?' . substr($string, $k + 1);
					$result[] = $pattern;
				}
			}
		}

		for($i = 0, $c = count($result); $i < $c; $i++) {
			$result[$i] = strtr($result[$i], ['-' => '\\W']);
		}

		// All possible combinations of the word "Packstation" with one wrong excess character
		for($i = 0, $l = strlen($string); $i < $l; $i++) {
			$pattern = substr($string, 0, $i + 1) . '.' . substr($string, $i + 1);
			$result[] = $pattern;
		}

		// All possible combinations of the word "Packstation" with two wrong excess character
		for($i = 1, $l = strlen($string); $i < $l; $i++) {
			for($j = $i; $j < $l; $j++) {
				$pattern = substr($string, 0, $i) . '.' . substr($string, $i, $j - $i) . '.' . substr($string, $j);
				$result[] = $pattern;
			}
		}

		// All possible combinations of the word "Packstation" with one wrong character
		for($i = 0, $l = strlen($string); $i < $l; $i++) {
			$pattern = substr($string, 0, $i) . '.' . substr($string, $i + 1);
			$result[] = $pattern;
		}

		for($i = 0, $c = count($result); $i < $c; $i++) {
			$result[$i] = strtr($result[$i], ['-' => '\\W?']);
		}

		$result[] = "(?:Pack|Paket)st(?:a(?:t(?:i(?:o(?:n)?)?)?)?)?\\.?(?:nr|no|nummer)?(?![a-z])";

		return $result;
	}
}


