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
}
