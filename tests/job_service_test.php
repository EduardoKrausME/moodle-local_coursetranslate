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
use local_coursetranslate\job_service;
use moodle_exception;

/**
 * Pure job helper tests.
 *
 * @package local_coursetranslate
 * @covers \local_coursetranslate\job_service
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class job_service_test extends advanced_testcase {
    /**
     * Terminology parser accepts UTF-8 and equals signs inside target value.
     */
    public function test_parse_terminology(): void {
        $terms = job_service::parse_terminology("Learner = Aluno\nEquation = x = y");
        $this->assertSame('Aluno', $terms['Learner']);
        $this->assertSame('x = y', $terms['Equation']);
    }

    /**
     * Malformed terminology is rejected.
     */
    public function test_invalid_terminology(): void {
        $this->expectException(moodle_exception::class);
        job_service::parse_terminology('Learner -> Aluno');
    }
}
