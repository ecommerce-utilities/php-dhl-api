<?php

namespace EcommerceUtilities\DHL\Services\AddressTools;

enum ReformatProbability : int {
	case VeryLow = 0;
	case Low = 1;
	case Medium = 2;
	case High = 3;
	case VeryHigh = 4;
}
