<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursetranslate;

use local_coursetranslate\local\translation\translator;

/**
 * AI response parsing tests.
 *
 * @package local_coursetranslate
 * @covers \local_coursetranslate\local\translation\translator
 */
final class translator_response_test extends \advanced_testcase {
    /** Valid JSON map. */
    public function test_valid_json(): void {
        $result = translator::parse_response('{"translations":{"i1":"Olá","i2":"Mundo"}}');
        $this->assertSame(['i1' => 'Olá', 'i2' => 'Mundo'], $result);
    }

    /** Common fenced JSON is tolerated. */
    public function test_fenced_json(): void {
        $result = translator::parse_response("```json\n{\"translations\":{\"i3\":\"Teste\"}}\n```");
        $this->assertSame(['i3' => 'Teste'], $result);
    }

    /** Partial mapping is parseable so caller can save successes and flag omissions. */
    public function test_partial_json_mapping(): void {
        $result = translator::parse_response('{"translations":{"i1":"Only one"}}');
        $this->assertArrayHasKey('i1', $result);
        $this->assertArrayNotHasKey('i2', $result);
    }

    /** Invalid provider output is rejected. */
    public function test_invalid_response(): void {
        $this->expectException(\moodle_exception::class);
        translator::parse_response('not json');
    }

    /** Prose wrapped around otherwise valid JSON is rejected. */
    public function test_prose_around_json_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        translator::parse_response('Here is the result: {"translations":{"i1":"Olá"}}');
    }
}
