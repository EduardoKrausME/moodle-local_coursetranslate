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

namespace local_coursetranslate\local\content;

use cm_info;
use context_module;
use coding_exception;
use stdClass;

/**
 * Collect supported, course-authored textual fields without touching learner data.
 *
 * The local key deliberately avoids database IDs that are regenerated during a
 * Moodle course duplicate/restore. It is based on stable course structure such
 * as section number, course-module idnumber or position, chapter order and quiz
 * slot number.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collector {
    /** @var array<string,bool> Physical fields already collected. */
    private array $seen = [];

    /**
     * Collect supported fields.
     *
     * @param int $courseid Course id.
     * @param array $options Job options.
     * @return array<string,array> Items indexed by stable local key.
     */
    public function collect(int $courseid, array $options): array {
        global $DB;

        $this->seen = [];
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $items = [];

        if (!empty($options['coursefullname'])) {
            $this->add($items, [
                'path' => 'course/fullname',
                'component' => 'core_course',
                'itemtype' => 'course',
                'sourcetable' => 'course',
                'sourceid' => (int)$course->id,
                'fieldname' => 'fullname',
                'contentformat' => FORMAT_PLAIN,
                'value' => (string)$course->fullname,
                'label' => get_string('fullnamecourse'),
                'metadata' => [],
            ]);
        }
        if (!empty($options['coursesummary'])) {
            $this->add($items, [
                'path' => 'course/summary',
                'component' => 'core_course',
                'itemtype' => 'course',
                'sourcetable' => 'course',
                'sourceid' => (int)$course->id,
                'fieldname' => 'summary',
                'contentformat' => (int)$course->summaryformat,
                'value' => (string)$course->summary,
                'label' => get_string('coursesummary'),
                'metadata' => [],
            ]);
        }

        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        $modinfo = get_fast_modinfo($courseid);
        foreach ($sections as $section) {
            $sectionpath = 'section/' . (int)$section->section;
            if (trim((string)$section->name) !== '') {
                $this->add($items, [
                    'path' => $sectionpath . '/name',
                    'component' => 'core_course',
                    'itemtype' => 'section',
                    'sourcetable' => 'course_sections',
                    'sourceid' => (int)$section->id,
                    'fieldname' => 'name',
                    'contentformat' => FORMAT_PLAIN,
                    'value' => (string)$section->name,
                    'label' => get_string('sectionname', 'format_topics') . ' ' . (int)$section->section,
                    'metadata' => ['sectionnum' => (int)$section->section],
                ]);
            }
            if (trim(strip_tags((string)$section->summary)) !== '') {
                $this->add($items, [
                    'path' => $sectionpath . '/summary',
                    'component' => 'core_course',
                    'itemtype' => 'section',
                    'sourcetable' => 'course_sections',
                    'sourceid' => (int)$section->id,
                    'fieldname' => 'summary',
                    'contentformat' => (int)$section->summaryformat,
                    'value' => (string)$section->summary,
                    'label' => get_string('summary') . ' ' . (int)$section->section,
                    'metadata' => ['sectionnum' => (int)$section->section],
                ]);
            }

            $cmids = array_values(array_filter(array_map('intval', explode(',', (string)$section->sequence))));
            foreach ($cmids as $position => $cmid) {
                try {
                    $cm = $modinfo->get_cm($cmid);
                } catch (coding_exception $e) {
                    continue;
                }
                if (!in_array($cm->modname, ['page', 'book', 'label', 'assign', 'forum', 'quiz', 'glossary'], true)) {
                    continue;
                }
                $modulepath = $this->module_path($cm, (int)$section->section, $position);
                $this->collect_module($items, $courseid, $cm, $modulepath, $options);
            }
        }

        return $items;
    }

    /**
     * Build a path that survives a same-site course copy.
     *
     * @param cm_info $cm Course module info.
     * @param int $sectionnum Section number.
     * @param int $position Position inside section sequence.
     * @return string
     */
    private function module_path(cm_info $cm, int $sectionnum, int $position): string {
        $structure = 'section/' . $sectionnum . '/position/' . $position . '/' . $cm->modname;
        if (trim((string)$cm->idnumber) !== '') {
            // idnumber is useful as an additional anchor, but Moodle does not
            // guarantee that course-module idnumbers are globally unique. Keep
            // structural coordinates too so duplicate idnumbers cannot collide.
            return 'module/idnumber/' . rawurlencode((string)$cm->idnumber) . '/' . $structure;
        }
        return 'module/' . $structure;
    }

    /**
     * Collect one supported activity/resource.
     *
     * @param array $items Result accumulator.
     * @param int $courseid Course id.
     * @param cm_info $cm Course module.
     * @param string $modulepath Stable module path.
     * @param array $options Job options.
     * @return void
     */
    private function collect_module(array &$items, int $courseid, cm_info $cm, string $modulepath, array $options): void {
        global $DB;

        $table = $cm->modname;
        $record = $DB->get_record($table, ['id' => $cm->instance]);
        if (!$record) {
            return;
        }

        $component = 'mod_' . $cm->modname;
        $displayname = format_string($cm->name, true, ['context' => context_module::instance($cm->id)]);
        if (property_exists($record, 'name') && trim((string)$record->name) !== '') {
            $this->add($items, [
                'path' => $modulepath . '/name',
                'component' => $component,
                'itemtype' => $cm->modname,
                'sourcetable' => $table,
                'sourceid' => (int)$record->id,
                'fieldname' => 'name',
                'contentformat' => FORMAT_PLAIN,
                'value' => (string)$record->name,
                'label' => $displayname . ' — name',
                'metadata' => ['cmid' => (int)$cm->id],
            ]);
        }
        if (property_exists($record, 'intro') && trim(strip_tags((string)$record->intro)) !== '') {
            $format = property_exists($record, 'introformat') ? (int)$record->introformat : FORMAT_HTML;
            $this->add($items, [
                'path' => $modulepath . '/intro',
                'component' => $component,
                'itemtype' => $cm->modname,
                'sourcetable' => $table,
                'sourceid' => (int)$record->id,
                'fieldname' => 'intro',
                'contentformat' => $format,
                'value' => (string)$record->intro,
                'label' => $displayname . ' — description',
                'metadata' => ['cmid' => (int)$cm->id],
            ]);
        }

        if ($cm->modname === 'page' && trim(strip_tags((string)$record->content)) !== '') {
            $this->add($items, [
                'path' => $modulepath . '/content',
                'component' => $component,
                'itemtype' => 'page',
                'sourcetable' => 'page',
                'sourceid' => (int)$record->id,
                'fieldname' => 'content',
                'contentformat' => (int)$record->contentformat,
                'value' => (string)$record->content,
                'label' => $displayname . ' — content',
                'metadata' => ['cmid' => (int)$cm->id],
            ]);
        } else if ($cm->modname === 'book') {
            $this->collect_book($items, $record, $modulepath, $displayname, (int)$cm->id);
        } else if ($cm->modname === 'quiz') {
            $this->collect_quiz($items, $record, $modulepath, $displayname, (int)$cm->id);
        } else if ($cm->modname === 'glossary' && !empty($options['includeglossary'])) {
            $this->collect_glossary_entries($items, $record, $modulepath, $displayname, (int)$cm->id);
        }
    }

    /**
     * Collect Book chapters.
     *
     * @param array $items Result accumulator.
     * @param stdClass $book Book record.
     * @param string $modulepath Stable module path.
     * @param string $displayname Display name.
     * @param int $cmid Course module id.
     * @return void
     */
    private function collect_book(array &$items, stdClass $book, string $modulepath, string $displayname, int $cmid): void {
        global $DB;

        $chapters = array_values($DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum ASC, id ASC'));
        foreach ($chapters as $index => $chapter) {
            $base = $modulepath . '/chapter/' . $index;
            if (trim((string)$chapter->title) !== '') {
                $this->add($items, [
                    'path' => $base . '/title',
                    'component' => 'mod_book',
                    'itemtype' => 'bookchapter',
                    'sourcetable' => 'book_chapters',
                    'sourceid' => (int)$chapter->id,
                    'fieldname' => 'title',
                    'contentformat' => FORMAT_PLAIN,
                    'value' => (string)$chapter->title,
                    'label' => $displayname . ' — chapter ' . ($index + 1) . ' title',
                    'metadata' => ['cmid' => $cmid, 'chapterindex' => $index],
                ]);
            }
            if (trim(strip_tags((string)$chapter->content)) !== '') {
                $this->add($items, [
                    'path' => $base . '/content',
                    'component' => 'mod_book',
                    'itemtype' => 'bookchapter',
                    'sourcetable' => 'book_chapters',
                    'sourceid' => (int)$chapter->id,
                    'fieldname' => 'content',
                    'contentformat' => (int)$chapter->contentformat,
                    'value' => (string)$chapter->content,
                    'label' => $displayname . ' — chapter ' . ($index + 1) . ' content',
                    'metadata' => ['cmid' => $cmid, 'chapterindex' => $index],
                ]);
            }
        }
    }

    /**
     * Collect quiz section headings and fixed questions used by the quiz.
     *
     * Random question-set references are deliberately skipped because there is
     * no single question definition to translate safely.
     *
     * @param array $items Result accumulator.
     * @param stdClass $quiz Quiz record.
     * @param string $modulepath Stable module path.
     * @param string $displayname Display name.
     * @param int $cmid Course module id.
     * @return void
     */
    private function collect_quiz(array &$items, stdClass $quiz, string $modulepath, string $displayname, int $cmid): void {
        global $DB;

        $sections = $DB->get_records('quiz_sections', ['quizid' => $quiz->id], 'firstslot ASC');
        foreach ($sections as $section) {
            if (trim((string)$section->heading) === '') {
                continue;
            }
            $this->add($items, [
                'path' => $modulepath . '/quizsection/' . (int)$section->firstslot . '/heading',
                'component' => 'mod_quiz',
                'itemtype' => 'quizsection',
                'sourcetable' => 'quiz_sections',
                'sourceid' => (int)$section->id,
                'fieldname' => 'heading',
                'contentformat' => FORMAT_PLAIN,
                'value' => (string)$section->heading,
                'label' => $displayname . ' — section heading',
                'metadata' => ['cmid' => $cmid],
            ]);
        }

        $cmcontext = context_module::instance($cmid);
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
        foreach ($slots as $slot) {
            $reference = $DB->get_record('question_references', [
                'usingcontextid' => $cmcontext->id,
                'component' => 'mod_quiz',
                'questionarea' => 'slot',
                'itemid' => $slot->id,
            ]);
            if (!$reference) {
                continue;
            }
            $question = $this->resolve_question($reference);
            if (!$question || !in_array($question->qtype, [
                    'multichoice', 'truefalse', 'shortanswer', 'numerical', 'essay', 'match',
                ], true)) {
                continue;
            }
            $this->collect_question(
                $items,
                $question,
                $modulepath . '/question/' . (int)$slot->slot,
                $displayname . ' — Q' . (int)$slot->slot,
                $cmid
            );
        }
    }

    /**
     * Resolve a fixed question reference to the version actually used.
     *
     * @param stdClass $reference question_references row.
     * @return stdClass|null
     */
    private function resolve_question(stdClass $reference): ?stdClass {
        global $DB;

        if ($reference->version !== null) {
            $version = $DB->get_record('question_versions', [
                'questionbankentryid' => $reference->questionbankentryid,
                'version' => $reference->version,
            ]);
        } else {
            $sql = "SELECT qv.*
                      FROM {question_versions} qv
                     WHERE qv.questionbankentryid = :entryid
                       AND qv.status <> :draft
                  ORDER BY qv.version DESC";
            $versions = $DB->get_records_sql($sql, [
                'entryid' => $reference->questionbankentryid,
                'draft' => 'draft',
            ], 0, 1);
            $version = reset($versions) ?: null;
        }
        if (!$version) {
            return null;
        }
        return $DB->get_record('question', ['id' => $version->questionid]) ?: null;
    }

    /**
     * Collect fields for a supported question type.
     *
     * @param array $items Result accumulator.
     * @param stdClass $question Question row.
     * @param string $questionpath Stable path based on quiz slot.
     * @param string $label Human-readable label.
     * @param int $cmid Quiz course-module id.
     * @return void
     */
    private function collect_question(
        array     &$items,
        stdClass $question,
        string    $questionpath,
        string    $label,
        int       $cmid
    ): void {
        global $DB;

        $metadata = ['questionid' => (int)$question->id, 'qtype' => (string)$question->qtype, 'cmid' => $cmid];
        foreach ([
                     ['name', FORMAT_PLAIN],
                     ['questiontext', (int)$question->questiontextformat],
                     ['generalfeedback', (int)$question->generalfeedbackformat],
                 ] as [$field, $format]) {
            if (trim(strip_tags((string)$question->{$field})) === '') {
                continue;
            }
            $this->add($items, [
                'path' => $questionpath . '/' . $field,
                'component' => 'core_question',
                'itemtype' => 'question_' . $question->qtype,
                'sourcetable' => 'question',
                'sourceid' => (int)$question->id,
                'fieldname' => $field,
                'contentformat' => $format,
                'value' => (string)$question->{$field},
                'label' => $label . ' — ' . $field,
                'metadata' => $metadata,
            ]);
        }

        $answers = array_values($DB->get_records('question_answers', ['question' => $question->id], 'id ASC'));
        foreach ($answers as $index => $answer) {
            if (in_array($question->qtype, ['multichoice', 'shortanswer'], true) &&
                trim(strip_tags((string)$answer->answer)) !== '' && !$this->looks_numeric((string)$answer->answer)) {
                $this->add($items, [
                    'path' => $questionpath . '/answer/' . $index . '/answer',
                    'component' => 'core_question',
                    'itemtype' => 'questionanswer_' . $question->qtype,
                    'sourcetable' => 'question_answers',
                    'sourceid' => (int)$answer->id,
                    'fieldname' => 'answer',
                    'contentformat' => (int)$answer->answerformat,
                    'value' => (string)$answer->answer,
                    'label' => $label . ' — answer ' . ($index + 1),
                    'metadata' => $metadata,
                ]);
            }
            if (trim(strip_tags((string)$answer->feedback)) !== '') {
                $this->add($items, [
                    'path' => $questionpath . '/answer/' . $index . '/feedback',
                    'component' => 'core_question',
                    'itemtype' => 'questionfeedback_' . $question->qtype,
                    'sourcetable' => 'question_answers',
                    'sourceid' => (int)$answer->id,
                    'fieldname' => 'feedback',
                    'contentformat' => (int)$answer->feedbackformat,
                    'value' => (string)$answer->feedback,
                    'label' => $label . ' — answer feedback ' . ($index + 1),
                    'metadata' => $metadata,
                ]);
            }
        }

        $hints = array_values($DB->get_records('question_hints', ['questionid' => $question->id], 'id ASC'));
        foreach ($hints as $index => $hint) {
            if (trim(strip_tags((string)$hint->hint)) === '') {
                continue;
            }
            $this->add($items, [
                'path' => $questionpath . '/hint/' . $index,
                'component' => 'core_question',
                'itemtype' => 'questionhint_' . $question->qtype,
                'sourcetable' => 'question_hints',
                'sourceid' => (int)$hint->id,
                'fieldname' => 'hint',
                'contentformat' => (int)$hint->hintformat,
                'value' => (string)$hint->hint,
                'label' => $label . ' — hint ' . ($index + 1),
                'metadata' => $metadata,
            ]);
        }

        if ($question->qtype === 'multichoice') {
            $options = $DB->get_record('qtype_multichoice_options', ['questionid' => $question->id]);
            if ($options) {
                foreach (['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'] as $field) {
                    if (trim(strip_tags((string)$options->{$field})) === '') {
                        continue;
                    }
                    $formatfield = $field . 'format';
                    $this->add($items, [
                        'path' => $questionpath . '/multichoice/' . $field,
                        'component' => 'qtype_multichoice',
                        'itemtype' => 'questionfeedback_multichoice',
                        'sourcetable' => 'qtype_multichoice_options',
                        'sourceid' => (int)$options->id,
                        'fieldname' => $field,
                        'contentformat' => (int)$options->{$formatfield},
                        'value' => (string)$options->{$field},
                        'label' => $label . ' — ' . $field,
                        'metadata' => $metadata,
                    ]);
                }
            }
        } else if ($question->qtype === 'essay') {
            $options = $DB->get_record('qtype_essay_options', ['questionid' => $question->id]);
            if ($options) {
                foreach (['graderinfo', 'responsetemplate'] as $field) {
                    if (trim(strip_tags((string)$options->{$field})) === '') {
                        continue;
                    }
                    $formatfield = $field . 'format';
                    $this->add($items, [
                        'path' => $questionpath . '/essay/' . $field,
                        'component' => 'qtype_essay',
                        'itemtype' => 'questionessay',
                        'sourcetable' => 'qtype_essay_options',
                        'sourceid' => (int)$options->id,
                        'fieldname' => $field,
                        'contentformat' => (int)$options->{$formatfield},
                        'value' => (string)$options->{$field},
                        'label' => $label . ' — ' . $field,
                        'metadata' => $metadata,
                    ]);
                }
            }
        } else if ($question->qtype === 'match') {
            $subquestions = array_values($DB->get_records('qtype_match_subquestions', [
                'questionid' => $question->id,
            ], 'id ASC'));
            foreach ($subquestions as $index => $subquestion) {
                foreach ([
                             ['questiontext', (int)$subquestion->questiontextformat],
                             ['answertext', FORMAT_PLAIN],
                         ] as [$field, $format]) {
                    if (trim(strip_tags((string)$subquestion->{$field})) === '') {
                        continue;
                    }
                    $this->add($items, [
                        'path' => $questionpath . '/match/' . $index . '/' . $field,
                        'component' => 'qtype_match',
                        'itemtype' => 'questionmatch',
                        'sourcetable' => 'qtype_match_subquestions',
                        'sourceid' => (int)$subquestion->id,
                        'fieldname' => $field,
                        'contentformat' => $format,
                        'value' => (string)$subquestion->{$field},
                        'label' => $label . ' — matching ' . ($index + 1) . ' ' . $field,
                        'metadata' => $metadata,
                    ]);
                }
            }
        }
    }

    /**
     * Collect only glossary entries that are authored by a user with the
     * manageentries capability in that glossary. Student-authored entries are
     * explicitly excluded from AI payloads.
     *
     * @param array $items Result accumulator.
     * @param stdClass $glossary Glossary record.
     * @param string $modulepath Stable module path.
     * @param string $displayname Display name.
     * @param int $cmid Course module id.
     * @return void
     */
    private function collect_glossary_entries(
        array     &$items,
        stdClass $glossary,
        string    $modulepath,
        string    $displayname,
        int       $cmid
    ): void {
        global $DB;

        $context = context_module::instance($cmid);
        $entries = $DB->get_records('glossary_entries', ['glossaryid' => $glossary->id], 'id ASC');
        $safeentries = [];
        foreach ($entries as $entry) {
            if ((int)$entry->userid > 0 && !has_capability('mod/glossary:manageentries', $context, (int)$entry->userid)) {
                continue;
            }
            $safeentries[] = $entry;
        }

        foreach (array_values($safeentries) as $index => $entry) {
            $metadata = ['cmid' => $cmid, 'entryindex' => $index];
            if (trim((string)$entry->concept) !== '') {
                $this->add($items, [
                    'path' => $modulepath . '/entry/' . $index . '/concept',
                    'component' => 'mod_glossary',
                    'itemtype' => 'glossaryentry',
                    'sourcetable' => 'glossary_entries',
                    'sourceid' => (int)$entry->id,
                    'fieldname' => 'concept',
                    'contentformat' => FORMAT_PLAIN,
                    'value' => (string)$entry->concept,
                    'label' => $displayname . ' — entry ' . ($index + 1) . ' concept',
                    'metadata' => $metadata,
                ]);
            }
            if (trim(strip_tags((string)$entry->definition)) !== '') {
                $this->add($items, [
                    'path' => $modulepath . '/entry/' . $index . '/definition',
                    'component' => 'mod_glossary',
                    'itemtype' => 'glossaryentry',
                    'sourcetable' => 'glossary_entries',
                    'sourceid' => (int)$entry->id,
                    'fieldname' => 'definition',
                    'contentformat' => (int)$entry->definitionformat,
                    'value' => (string)$entry->definition,
                    'label' => $displayname . ' — entry ' . ($index + 1) . ' definition',
                    'metadata' => $metadata,
                ]);
            }
        }
    }

    /**
     * Add one field, de-duplicating shared question definitions by physical DB field.
     *
     * @param array $items Result accumulator.
     * @param array $item Item data.
     * @return void
     */
    private function add(array &$items, array $item): void {
        if ($item['value'] === '' || trim(strip_tags((string)$item['value'])) === '') {
            return;
        }
        $physicalkey = $item['sourcetable'] . ':' . $item['sourceid'] . ':' . $item['fieldname'];
        if (isset($this->seen[$physicalkey])) {
            return;
        }
        $this->seen[$physicalkey] = true;

        $localkey = hash('sha256', $item['path']);
        $item['localkey'] = $localkey;
        $item['sourcehash'] = self::hash((string)$item['value']);
        $item['locator'] = ['path' => $item['path']];
        unset($item['path']);
        $items[$localkey] = $item;
    }

    /**
     * Hash exact field contents.
     *
     * @param string $value Value.
     * @return string
     */
    public static function hash(string $value): string {
        return hash('sha256', $value);
    }

    /**
     * Numeric answers and formulas must never be sent for linguistic translation.
     *
     * @param string $value Value.
     * @return bool
     */
    private function looks_numeric(string $value): bool {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return true;
        }
        return (bool)preg_match('/^[\s+\-0-9.,eE*\/^(){}\[\]=<>%]+$/u', $value);
    }
}
