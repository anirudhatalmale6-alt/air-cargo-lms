<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * An IATA air waybill number.
 *
 * Format: 3 digit airline accounting prefix + 7 running digits + 1 check digit.
 * The check digit is the 7 digit serial modulo 7, which is why a mistyped AWB
 * is almost always caught before it reaches an airline system.
 */
final class AwbNumber
{
    private function __construct(
        public readonly string $prefix,
        public readonly string $serial,
    ) {}

    /**
     * Accept anything a human might type: 020-1234567 8, 02012345678, 020 1234 5678.
     */
    public static function parse(string $input): self
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if (strlen($digits) !== 11) {
            throw new InvalidArgumentException(
                'An AWB number must be 11 digits (3 digit prefix + 8 digit serial), got '.strlen($digits).'.'
            );
        }

        $prefix = substr($digits, 0, 3);
        $serial = substr($digits, 3);

        $expected = self::checkDigitFor(substr($serial, 0, 7));

        if ($serial[7] !== $expected) {
            throw new InvalidArgumentException(
                "Check digit mismatch: {$prefix}-{$serial} should end in {$expected}. Please re-read the AWB."
            );
        }

        return new self($prefix, $serial);
    }

    public static function tryParse(string $input): ?self
    {
        try {
            return self::parse($input);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Build a complete AWB from a prefix and the 7 running digits, appending the
     * check digit ourselves. This is what AWB stock generation uses.
     */
    public static function fromRunningSerial(string $prefix, string|int $runningSerial): self
    {
        $prefix = str_pad(preg_replace('/\D+/', '', (string) $prefix) ?? '', 3, '0', STR_PAD_LEFT);
        $running = str_pad(preg_replace('/\D+/', '', (string) $runningSerial) ?? '', 7, '0', STR_PAD_LEFT);

        if (strlen($prefix) !== 3) {
            throw new InvalidArgumentException('Airline prefix must be 3 digits.');
        }

        if (strlen($running) !== 7) {
            throw new InvalidArgumentException('Running serial must be 7 digits or fewer.');
        }

        return new self($prefix, $running.self::checkDigitFor($running));
    }

    public static function checkDigitFor(string $runningSerial): string
    {
        // bcmod keeps this exact on 32 bit builds, where (int) '9999999' arithmetic
        // is fine but a longer intermediate would not be.
        return bcmod($runningSerial, '7');
    }

    public static function isValid(string $input): bool
    {
        return self::tryParse($input) !== null;
    }

    /** Digits only: 02012345678. The storage and lookup form. */
    public function digits(): string
    {
        return $this->prefix.$this->serial;
    }

    /** Human form: 020-1234567 8. */
    public function formatted(): string
    {
        return sprintf('%s-%s %s', $this->prefix, substr($this->serial, 0, 7), $this->serial[7]);
    }

    public function runningSerial(): string
    {
        return substr($this->serial, 0, 7);
    }

    public function __toString(): string
    {
        return $this->formatted();
    }
}
