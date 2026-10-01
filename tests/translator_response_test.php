<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_coursetranslate;

use advanced_testcase;
use local_coursetranslate\translation\translator;
use moodle_exception;

/**
 * AI response parsing tests.
 *
 * @package local_coursetranslate
 * @covers \local_coursetranslate\translation\translator
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class translator_response_test extends advanced_testcase {
    /**
     * Valid JSON map.
     */
    public function test_valid_json(): void {
        $result = translator::parse_response('{"translations":{"i1":"Olá","i2":"Mundo"}}');
        $this->assertSame(['i1' => 'Olá', 'i2' => 'Mundo'], $result);
    }

    /**
     * Common fenced JSON is tolerated.
     */
    public function test_fenced_json(): void {
        $fence = str_repeat(chr(96), 3);
        $result = translator::parse_response(
            $fence . "json\n{\"translations\":{\"i3\":\"Teste\"}}\n" . $fence
        );
        $this->assertSame(['i3' => 'Teste'], $result);
    }

    /**
     * Partial mapping is parseable so caller can save successes and flag omissions.
     */
    public function test_partial_json_mapping(): void {
        $result = translator::parse_response('{"translations":{"i1":"Only one"}}');
        $this->assertArrayHasKey('i1', $result);
        $this->assertArrayNotHasKey('i2', $result);
    }

    /**
     * Invalid provider output is rejected.
     */
    public function test_invalid_response(): void {
        $this->expectException(moodle_exception::class);
        translator::parse_response('not json');
    }

    /**
     * Prose wrapped around otherwise valid JSON is rejected.
     */
    public function test_prose_around_json_is_rejected(): void {
        $this->expectException(moodle_exception::class);
        translator::parse_response('Here is the result: {"translations":{"i1":"Olá"}}');
    }
}
