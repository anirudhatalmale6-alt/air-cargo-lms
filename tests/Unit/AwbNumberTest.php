<?php

namespace Tests\Unit;

use App\Support\AwbNumber;
use InvalidArgumentException;
use Tests\TestCase;

class AwbNumberTest extends TestCase
{
    /** 4471182 mod 7 = 2, so the stored serial is 44711822. */
    public function test_it_computes_the_iata_mod_7_check_digit(): void
    {
        $this->assertSame('2', AwbNumber::checkDigitFor('4471182'));
        $this->assertSame('5', AwbNumber::checkDigitFor('1234567'));
        $this->assertSame('0', AwbNumber::checkDigitFor('0000000'));
    }

    public function test_it_builds_a_complete_awb_from_a_running_serial(): void
    {
        $awb = AwbNumber::fromRunningSerial('020', 4471182);

        $this->assertSame('02044711822', $awb->digits());
        $this->assertSame('020-4471182 2', $awb->formatted());
        $this->assertSame('4471182', $awb->runningSerial());
    }

    public function test_it_pads_a_short_running_serial(): void
    {
        $this->assertSame('02000000125', AwbNumber::fromRunningSerial('020', 12)->digits());
    }

    /** Spaces, dashes and the airline's own layout must all parse. */
    public function test_it_parses_every_format_a_human_might_type(): void
    {
        foreach (['020-1234567 5', '02012345675', '020 1234 5675', '020/1234567/5'] as $input) {
            $this->assertSame('02012345675', AwbNumber::parse($input)->digits(), $input);
        }
    }

    public function test_it_rejects_a_transposed_digit(): void
    {
        // 1234567 -> check digit 5. Transposing to 1234657 changes the remainder.
        $this->assertTrue(AwbNumber::isValid('020-1234567 5'));
        $this->assertFalse(AwbNumber::isValid('020-1234657 5'));
    }

    public function test_the_failure_message_names_the_expected_check_digit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/should end in 5/');

        AwbNumber::parse('020-1234567 9');
    }

    public function test_it_rejects_the_wrong_length(): void
    {
        $this->assertFalse(AwbNumber::isValid('020-123456'));
        $this->assertFalse(AwbNumber::isValid('020123456789'));
        $this->assertFalse(AwbNumber::isValid(''));
    }
}
