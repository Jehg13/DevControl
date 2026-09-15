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

    public function test_preserves_failure_name_message_file_line_and_trace(): void
    {
        $result = app(NexusTestResultParser::class)->parse(<<<'PHPUNIT'
There was 1 failure:

1) Tests\Feature\ExampleTest::test_it_rejects_invalid_input
Failed asserting that 422 is identical to 200.

C:\project\tests\Feature\ExampleTest.php:27
	C:\project\vendor\phpunit\phpunit\src\Framework\TestCase.php:123
PHPUNIT);

        $this->assertCount(1, $result['failure_details']);
        $failure = $result['failure_details'][0];
        $this->assertSame('Tests\Feature\ExampleTest::test_it_rejects_invalid_input', $failure['test']);
        $this->assertSame('Tests\Feature\ExampleTest', $failure['class']);
        $this->assertSame('test_it_rejects_invalid_input', $failure['method']);
        $this->assertStringContainsString('Failed asserting that 422 is identical to 200.', $failure['message']);
        $this->assertSame('C:\project\tests\Feature\ExampleTest.php', $failure['file']);
        $this->assertSame(27, $failure['line']);
        $this->assertStringContainsString('Framework\TestCase.php:123', $failure['trace']);
    }

    public function test_preserves_multiple_failure_details(): void
    {
        $result = app(NexusTestResultParser::class)->parse(<<<'PHPUNIT'
There were 2 failures:

1) Tests\Unit\FirstTest::test_first
Failed asserting that false is true.
C:\project\tests\Unit\FirstTest.php:10

2) Tests\Unit\SecondTest::test_second
Failed asserting that null is not null.
C:\project\tests\Unit\SecondTest.php:20
PHPUNIT);

        $this->assertCount(2, $result['failure_details']);
        $this->assertSame('Tests\Unit\FirstTest::test_first', $result['failure_details'][0]['test']);
        $this->assertSame('Tests\Unit\SecondTest::test_second', $result['failure_details'][1]['test']);
    }
}
