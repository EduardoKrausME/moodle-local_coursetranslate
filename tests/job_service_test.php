<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursetranslate;

use local_coursetranslate\local\job_service;

/**
 * Pure job helper tests.
 *
 * @package local_coursetranslate
 * @covers \local_coursetranslate\local\job_service
 */
final class job_service_test extends \advanced_testcase {
    /** Terminology parser accepts UTF-8 and equals signs inside target value. */
    public function test_parse_terminology(): void {
        $terms = job_service::parse_terminology("Learner = Aluno\nEquation = x = y");
        $this->assertSame('Aluno', $terms['Learner']);
        $this->assertSame('x = y', $terms['Equation']);
    }

    /** Malformed terminology is rejected. */
    public function test_invalid_terminology(): void {
        $this->expectException(\moodle_exception::class);
        job_service::parse_terminology('Learner -> Aluno');
    }
}
