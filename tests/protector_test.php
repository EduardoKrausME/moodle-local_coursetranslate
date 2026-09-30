<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursetranslate;

use local_coursetranslate\local\token\protector;

/**
 * Token protection tests.
 *
 * @package local_coursetranslate
 * @covers \local_coursetranslate\local\token\protector
 */
final class protector_test extends \advanced_testcase {
    /** HTML, URL, pluginfile, placeholders and mandatory terminology survive byte-for-byte. */
    public function test_html_pluginfile_url_placeholder_and_terminology(): void {
        $source = '<p class="lead">Learner, open <a href="https://example.org/a?id=7">@@PLUGINFILE@@/Manual.pdf</a> {{name}}</p>';
        $protector = new protector();
        $payload = $protector->protect($source, ['Learner' => 'Aluno']);
        $translatedprotected = str_replace('open', 'abra', $payload['text']);
        $restored = $protector->restore($payload, $translatedprotected);

        $this->assertStringContainsString('Aluno, abra', $restored);
        $this->assertStringContainsString('<p class="lead">', $restored);
        $this->assertStringContainsString('<a href="https://example.org/a?id=7">', $restored);
        $this->assertStringContainsString('@@PLUGINFILE@@/Manual.pdf', $restored);
        $this->assertStringContainsString('{{name}}', $restored);
    }

    /** Missing tokens invalidate a response instead of silently corrupting content. */
    public function test_missing_token_is_rejected(): void {
        $protector = new protector();
        $payload = $protector->protect('<p>Text <a href="https://example.org">link</a></p>');
        $bad = preg_replace('/__CTP_\d{6}__/', '', $payload['text'], 1);
        $this->expectException(\moodle_exception::class);
        $protector->restore($payload, (string)$bad);
    }

    /** AI cannot inject new markup into a protected HTML field. */
    public function test_new_html_is_rejected(): void {
        $protector = new protector();
        $payload = $protector->protect('<p>Hello</p>');
        $this->expectException(\moodle_exception::class);
        $protector->restore($payload, $payload['text'] . '<script>alert(1)</script>');
    }

    /** Keeping tag order is not enough if text is moved outside the element. */
    public function test_text_moved_across_html_boundary_is_rejected(): void {
        $protector = new protector();
        $payload = $protector->protect('<p>Hello</p>');
        $open = $payload['tagtokens'][0];
        $close = $payload['tagtokens'][1];
        $this->expectException(\moodle_exception::class);
        $protector->restore($payload, $open . $close . 'Olá');
    }

    /** UTF-8 remains intact. */
    public function test_utf8_round_trip(): void {
        $protector = new protector();
        $payload = $protector->protect('<p>Educação, ação, coração, 日本語 😀</p>');
        $restored = $protector->restore($payload, str_replace('Educação', 'Education', $payload['text']));
        $this->assertStringContainsString('Education, ação, coração, 日本語 😀', $restored);
    }

    /** Question-like formulas, code and filenames are protected. */
    public function test_question_formula_code_and_filename_are_preserved(): void {
        $source = '<p>Explain $$E=mc^2$$ and inspect <code>local_ai_bridge::generate()</code> in example.php.</p>';
        $protector = new protector();
        $payload = $protector->protect($source);
        $restored = $protector->restore($payload, str_replace('Explain', 'Explique', $payload['text']));
        $this->assertStringContainsString('$$E=mc^2$$', $restored);
        $this->assertStringContainsString('<code>local_ai_bridge::generate()</code>', $restored);
        $this->assertStringContainsString('example.php', $restored);
    }
}
