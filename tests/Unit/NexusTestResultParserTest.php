<?php

namespace Tests\Unit;

use App\Services\NexusTestResultParser;
use Tests\TestCase;

class NexusTestResultParserTest extends TestCase
{
    public function test_parses_successful_phpunit_summary(): void
    {
        $result = app(NexusTestResultParser::class)->parse('Tests: 83 passed, 295 assertions');

        $this->assertSame(83, $result['tests']);
        $this->assertSame(295, $result['assertions']);
        $this->assertSame(0, $result['failures']);
    }

    public function test_parses_failures_and_errors(): void
    {
        $result = app(NexusTestResultParser::class)->parse(
            "Tests: 1 failed, 82 passed (295 assertions)\nFailures: 1\nErrors: 2"
        );

        $this->assertSame(83, $result['tests']);
        $this->assertSame(1, $result['failures']);
        $this->assertSame(2, $result['errors']);
    }

    public function test_parses_skipped_incomplete_warnings_and_deprecations(): void
    {
        $result = app(NexusTestResultParser::class)->parse(
            'Tests: 2 skipped, 1 incomplete, 3 warnings, 4 deprecated, 10 passed (20 assertions)'
        );

        $this->assertSame(20, $result['assertions']);
        $this->assertSame(13, $result['tests']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(1, $result['incomplete']);
        $this->assertSame(3, $result['warnings']);
        $this->assertSame(4, $result['deprecations']);
    }

    public function test_preserves_unknown_metrics_as_null_when_phpunit_has_no_summary(): void
    {
        $result = app(NexusTestResultParser::class)->parse('PHP Fatal error: test process stopped');

        $this->assertNull($result['tests']);
        $this->assertNull($result['assertions']);
        $this->assertNull($result['failures']);
        $this->assertNull($result['errors']);
    }
}
