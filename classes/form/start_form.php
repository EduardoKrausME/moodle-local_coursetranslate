<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursetranslate\form;

use local_coursetranslate\local\job_service;

/**
 * Start translation job form.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class start_form extends \moodleform {
    /** Define form. */
    protected function definition(): void {
        $mform = $this->_form;
        $langs = job_service::language_options();

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('autocomplete', 'sourcelang', get_string('sourcelang', 'local_coursetranslate'), $langs);
        $mform->addRule('sourcelang', null, 'required', null, 'client');
        $mform->setDefault('sourcelang', current_language());

        $mform->addElement('autocomplete', 'targetlang', get_string('targetlang', 'local_coursetranslate'), $langs);
        $mform->addRule('targetlang', null, 'required', null, 'client');
        $mform->setDefault('targetlang', current_language() === 'pt_br' ? 'en' : 'pt_br');

        $mform->addElement('advcheckbox', 'coursefullname', get_string('translatefullname', 'local_coursetranslate'));
        $mform->setDefault('coursefullname', 1);
        $mform->addElement('advcheckbox', 'coursesummary', get_string('translatesummary', 'local_coursetranslate'));
        $mform->setDefault('coursesummary', 1);
        $mform->addElement('advcheckbox', 'includeglossary', get_string('includeglossary', 'local_coursetranslate'));
        $mform->setDefault('includeglossary', 0);

        $mform->addElement('textarea', 'terminology', get_string('terminology', 'local_coursetranslate'), [
            'rows' => 8,
            'cols' => 70,
            'placeholder' => "Learner = Aluno\nAssignment = Atividade",
        ]);
        $mform->setType('terminology', PARAM_RAW);
        $mform->addHelpButton('terminology', 'terminology', 'local_coursetranslate');

        $this->add_action_buttons(true, get_string('starttranslation', 'local_coursetranslate'));
    }

    /**
     * Validate language and terminology input.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (($data['sourcelang'] ?? '') === ($data['targetlang'] ?? '')) {
            $errors['targetlang'] = get_string('error:samelanguage', 'local_coursetranslate');
        }
        try {
            job_service::parse_terminology((string)($data['terminology'] ?? ''));
        } catch (\Throwable $e) {
            $errors['terminology'] = $e->getMessage();
        }
        return $errors;
    }
}
