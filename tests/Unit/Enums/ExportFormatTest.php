<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ExportFormat;
use PHPUnit\Framework\TestCase;

class ExportFormatTest extends TestCase
{
    public function test_flat_keeps_keys_as_they_are(): void
    {
        $pairs = ['auth.login.title' => 'Sign in', 'title' => 'App'];

        $this->assertSame($pairs, ExportFormat::Flat->shape($pairs));
    }

    public function test_nested_expands_dotted_keys(): void
    {
        $this->assertSame(
            ['auth' => ['login' => ['hint' => 'Email', 'title' => 'Sign in']], 'title' => 'App'],
            ExportFormat::Nested->shape([
                'auth.login.title' => 'Sign in',
                'auth.login.hint' => 'Email',
                'title' => 'App',
            ]),
        );
    }

    public function test_nested_lets_the_deeper_key_win_when_a_key_is_also_a_prefix(): void
    {
        $expected = ['menu' => ['file' => 'File']];

        $this->assertSame($expected, ExportFormat::Nested->shape(['menu' => 'Menu', 'menu.file' => 'File']));
        $this->assertSame($expected, ExportFormat::Nested->shape(['menu.file' => 'File', 'menu' => 'Menu']));
    }
}
