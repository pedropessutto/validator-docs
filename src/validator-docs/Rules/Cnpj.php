<?php

declare(strict_types=1);

namespace geekcom\ValidatorDocs\Rules;

use function preg_match;

final class Cnpj extends Sanitization
{
    private const CNPJ_BASE_LENGTH = 12;
    private const REGEX_CNPJ_BASE = '/^[A-Z\d]{12}$/';
    private const REGEX_CNPJ_FULL = '/^[A-Z\d]{12}\d{2}$/';
    private const ASCII_ZERO = 48; // '0' in ASCII
    private const ZEROED_CNPJ = '00000000000000';

    // Weights from right to left: 2, 3, 4, 5, 6, 7, 8, 9, 2, 3, 4, 5 (for DV1)
    // and 2, 3, 4, 5, 6, 7, 8, 9, 2, 3, 4, 5, 6 (for DV2)
    private const DV1_WEIGHTS = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    private const DV2_WEIGHTS = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    public function validateCnpj($attribute, $value): bool
    {
        $cnpjClean = strtoupper($this->sanitizeCNPJAlphanumeric($value));
        if ($cnpjClean !== self::ZEROED_CNPJ && preg_match(self::REGEX_CNPJ_FULL, $cnpjClean)) {
            $providedDV = substr($cnpjClean, self::CNPJ_BASE_LENGTH);
            $calculatedDV = $this->calculateCNPJCheckDigits(substr($cnpjClean, 0, self::CNPJ_BASE_LENGTH));

            return $providedDV === $calculatedDV;
        }

        return false;
    }

    /**
     * Calculate CNPJ check digits (DV) using the new alphanumeric rules.
     */
    public function calculateCNPJCheckDigits($cnpj): string
    {
        $cnpjClean = strtoupper($this->sanitizeCNPJAlphanumeric($cnpj));
        if (preg_match(self::REGEX_CNPJ_BASE, $cnpjClean) && substr(self::ZEROED_CNPJ, 0, strlen($cnpjClean)) !== $cnpjClean) {
            // Calculate DV1
            $sumDV1 = 0;
            for ($i = 0; $i < self::CNPJ_BASE_LENGTH; $i++) {
                $charValue = ord($cnpjClean[$i]) - self::ASCII_ZERO;
                $sumDV1 += $charValue * self::DV1_WEIGHTS[$i];
            }

            $remainder1 = $sumDV1 % 11;
            $dv1 = ($remainder1 < 2) ? 0 : 11 - $remainder1;

            // Calculate DV2 (includes DV1 at the end)
            $sumDV2 = 0;
            for ($i = 0; $i < self::CNPJ_BASE_LENGTH; $i++) {
                $charValue = ord($cnpjClean[$i]) - self::ASCII_ZERO;
                $sumDV2 += $charValue * self::DV2_WEIGHTS[$i];
            }
            $sumDV2 += $dv1 * self::DV2_WEIGHTS[self::CNPJ_BASE_LENGTH];

            $remainder2 = $sumDV2 % 11;
            $dv2 = ($remainder2 < 2) ? 0 : 11 - $remainder2;

            return "{$dv1}{$dv2}";
        }

        throw new \InvalidArgumentException('Invalid CNPJ');
    }

    private function sanitizeCNPJAlphanumeric(string $value): string
    {
        return preg_replace('/[.\-\/]/', '', strtoupper(trim($value)));
    }
}
