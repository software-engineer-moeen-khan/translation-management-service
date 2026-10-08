<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\FullTextExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FullTextExpressionTest extends TestCase
{
    #[DataProvider('terms')]
    public function test_it_builds_a_boolean_mode_expression(string $term, ?string $expected): void
    {
        $this->assertSame($expected, FullTextExpression::allWordsPrefixed($term));
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function terms(): array
    {
        return [
            'single word' => ['welcome', '+welcome*'],
            'several words' => ['sign in now', '+sign* +in* +now*'],
            'unicode letters' => ['début été', '+début* +été*'],
            'digits' => ['step 2', '+step* +2*'],
            'operators are stripped' => ['+foo -bar* "baz" (qux) ~a <b >c @4', '+foo* +bar* +baz* +qux* +a* +b* +c* +4*'],
            'punctuation splits words' => ["don't re-enter", '+don* +t* +re* +enter*'],
            'only symbols' => ['+-*"()', null],
            'empty' => ['', null],
        ];
    }
}
