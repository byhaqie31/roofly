<?php

namespace Tests\Unit;

use App\Support\PrivacyMask;
use PHPUnit\Framework\TestCase;

class PrivacyMaskTest extends TestCase
{
    public function test_name_keeps_first_name_and_last_initial(): void
    {
        $this->assertSame('Aminah Y.', PrivacyMask::name('Aminah Binti Yusof'));
        $this->assertSame('Lim W.', PrivacyMask::name('  Lim   Li Wei '));
        $this->assertSame('Ravi', PrivacyMask::name('Ravi'));
        $this->assertSame('•••', PrivacyMask::name(''));
        $this->assertSame('•••', PrivacyMask::name(null));
    }

    public function test_email_keeps_two_characters_and_the_domain(): void
    {
        $this->assertSame('am•••@example.com', PrivacyMask::email('aminah.yusof@example.com'));
        $this->assertSame('a•••@example.com', PrivacyMask::email('ab@example.com'));
        $this->assertSame('•••', PrivacyMask::email('not-an-email'));
        $this->assertSame('•••', PrivacyMask::email('@example.com'));
        $this->assertSame('•••', PrivacyMask::email(null));
    }
}
