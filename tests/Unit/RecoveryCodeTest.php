<?php

namespace Tests\Unit;

use App\Support\RecoveryCode;
use PHPUnit\Framework\TestCase;

class RecoveryCodeTest extends TestCase
{
    public function test_generated_code_has_expected_format_without_ambiguous_characters(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = RecoveryCode::generate();
            $this->assertMatchesRegularExpression('/^JEDA-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $code);
        }
    }

    public function test_generated_codes_are_random(): void
    {
        $codes = array_map(fn () => RecoveryCode::generate(), range(1, 200));

        $this->assertCount(200, array_unique($codes));
    }

    public function test_normalize_accepts_lowercase_spaces_and_missing_dashes(): void
    {
        $this->assertSame('JEDA-ABCD-EFGH-JK23', RecoveryCode::normalize('jeda-abcd-efgh-jk23'));
        $this->assertSame('JEDA-ABCD-EFGH-JK23', RecoveryCode::normalize('  JEDA ABCD EFGH JK23 '));
        $this->assertSame('JEDA-ABCD-EFGH-JK23', RecoveryCode::normalize('abcdefghjk23'));
    }

    public function test_normalize_rejects_invalid_codes(): void
    {
        $this->assertNull(RecoveryCode::normalize(''));
        $this->assertNull(RecoveryCode::normalize('JEDA-ABCD'));
        $this->assertNull(RecoveryCode::normalize('JEDA-ABCD-EFGH-JK2O')); // O dan 0 tidak dipakai
        $this->assertNull(RecoveryCode::normalize('JEDA-ABCD-EFGH-JK234')); // terlalu panjang
    }

    public function test_hash_is_stable_and_not_the_code_itself(): void
    {
        $code = 'JEDA-ABCD-EFGH-JK23';

        $this->assertSame(RecoveryCode::hash($code), RecoveryCode::hash($code));
        $this->assertSame(64, strlen(RecoveryCode::hash($code)));
        $this->assertStringNotContainsString('ABCD', RecoveryCode::hash($code));
    }
}
