<?php

namespace App\Rules;

use App\Support\AwbNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

/**
 * Validates the IATA mod 7 check digit, so a transposed digit is rejected at the
 * form rather than discovered when an airline refuses the AWB.
 */
class ValidAwb implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        try {
            AwbNumber::parse((string) $value);
        } catch (InvalidArgumentException $e) {
            $fail($e->getMessage());
        }
    }
}
