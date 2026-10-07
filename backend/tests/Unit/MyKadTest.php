<?php

namespace Tests\Unit;

use App\Support\MyKad;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class MyKadTest extends TestCase
{
    public function test_normalize_accepts_bare_dashed_or_spaced_digits(): void
    {
        $this->assertSame('880314-14-5687', MyKad::normalize('880314145687'));
        $this->assertSame('880314-14-5687', MyKad::normalize('880314-14-5687'));
        $this->assertSame('880314-14-5687', MyKad::normalize(' 880314 14 5687 '));
    }

    public function test_normalize_returns_null_for_anything_else(): void
    {
        $this->assertNull(MyKad::normalize('88031414568'));
        $this->assertNull(MyKad::normalize(''));
        $this->assertNull(MyKad::normalize(null));
        $this->assertNull(MyKad::normalize('A1234567'));
    }

    public function test_date_of_birth_uses_the_century_that_is_not_in_the_future(): void
    {
        $today = CarbonImmutable::parse('2026-10-07');
        $this->assertSame('1988-03-14', MyKad::dateOfBirth('880314145687', $today));
        $this->assertSame('2005-01-01', MyKad::dateOfBirth('050101-01-0001', $today));
        $this->assertSame('2026-03-14', MyKad::dateOfBirth('260314-14-5687', $today));
        $this->assertSame('1926-12-31', MyKad::dateOfBirth('261231-14-5687', $today));
    }

    public function test_date_of_birth_is_null_for_impossible_dates(): void
    {
        $today = CarbonImmutable::parse('2026-10-07');
        $this->assertNull(MyKad::dateOfBirth('881314145687', $today)); // month 13
        $this->assertNull(MyKad::dateOfBirth('880230145687', $today)); // 30 Feb
        $this->assertNull(MyKad::dateOfBirth('8803', $today));
    }
}
