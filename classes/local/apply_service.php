<?php
// This file is part of Moodle - http://moodle.org/

namespace local_coursetranslate\local;

use local_coursetranslate\local\content\collector;
use moodle_exception;

/**
 * Controlled writer for translated fields.
 *
 * All writes pass through an explicit table/field allowlist and resolve the
 * current destination record through the stable collector key. Stored source
 * IDs are never trusted for a copied course.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_service {
    /**
     * Apply selected items to the original course.
     *
     * @param int $jobid Job id.
     * @param array<int> $itemids Item ids.
     * @return array{applied:int,skipped:int}
     */
    public function apply_original(int $jobid, array $itemids): array {
        $job = job_service::get_job($jobid);
        return $this->apply_to_course($job, $itemids, (int)$job->courseid, true);
    }

    /**
     * Apply selected items to a destination course.
     *
     * @param \stdClass $job Job.
     * @param array<int> $itemids Source job item ids.
     * @param int $targetcourseid Target course id.
     * @param bool $requirefreshsource Whether original source must still equal snapshot.
     * @return array{applied:int,skipped:int}
     */
    public function apply_to_course(
        \stdClass $job,
        array $itemids,
        int $targetcourseid,
        bool $requirefreshsource
    ): array {
        global $CFG, $DB;

        $selection = array_fill_keys(array_map('intval', $itemids), true);
        $items = job_service::get_items((int)$job->id);
        $sourcecurrent = $requirefreshsource ? job_service::current_map($job) : [];
        $targetmap = job_service::current_map($job, $targetcourseid);
        $questionids = [];
        $applied = 0;
        $skipped = 0;

        $transaction = $DB->start_delegated_transaction();
        foreach ($items as $item) {
            if (!isset($selection[(int)$item->id])) {
                continue;
            }
            if ($item->translatedvalue === null || !in_array($item->state, ['translated', 'failed'], true)) {
                $skipped++;
                continue;
            }
            if ($requirefreshsource) {
                $current = $sourcecurrent[$item->localkey] ?? null;
                if (!$current || !hash_equals((string)$item->sourcehash, (string)$current['sourcehash'])) {
                    $skipped++;
                    continue;
                }
            }
            $target = $targetmap[$item->localkey] ?? null;
            if (!$target) {
                $skipped++;
                continue;
            }
            if (!$this->is_allowed((string)$target['sourcetable'], (string)$target['fieldname'])) {
                throw new moodle_exception('unsupportedfield', 'local_coursetranslate');
            }

            // Moodle question editing is versioned. Mutating question tables in the
            // original course would bypass the question-bank save APIs and could
            // rewrite content already referenced by attempts. Question text is
            // therefore translated in preview/copies, but direct original-course
            // application deliberately refuses these records. A duplicated course
            // has no learner attempts and receives its isolated restored question
            // records below through the stable mapping.
            if ($requirefreshsource && $this->is_question_table((string)$target['sourcetable'])) {
                $skipped++;
                continue;
            }

            $DB->set_field(
                $target['sourcetable'],
                $target['fieldname'],
                (string)$item->translatedvalue,
                ['id' => (int)$target['sourceid']]
            );
            $metadata = $target['metadata'] ?? [];
            if (!empty($metadata['questionid'])) {
                $questionids[(int)$metadata['questionid']] = true;
            }
            $applied++;
        }
        $transaction->allow_commit();

        if ($questionids) {
            require_once($CFG->dirroot . '/question/engine/bank.php');
            foreach (array_keys($questionids) as $questionid) {
                \question_bank::notify_question_edited($questionid);
            }
        }
        rebuild_course_cache($targetcourseid, true);
        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * Check selected source items are still fresh before a course copy starts.
     *
     * @param \stdClass $job Job.
     * @param array<int> $itemids Item ids.
     * @return array<int> Fresh item ids.
     */
    public function fresh_itemids(\stdClass $job, array $itemids): array {
        $selection = array_fill_keys(array_map('intval', $itemids), true);
        $currentmap = job_service::current_map($job);
        $fresh = [];
        foreach (job_service::get_items((int)$job->id) as $item) {
            if (!isset($selection[(int)$item->id]) || $item->translatedvalue === null || $item->state !== 'translated') {
                continue;
            }
            $current = $currentmap[$item->localkey] ?? null;
            if ($current && hash_equals((string)$item->sourcehash, (string)$current['sourcehash'])) {
                $fresh[] = (int)$item->id;
            }
        }
        return $fresh;
    }

    /**
     * Explicit write allowlist.
     *
     * @param string $table Table.
     * @param string $field Field.
     * @return bool
     */
    /**
     * Whether the target belongs to Moodle's versioned question definition.
     *
     * @param string $table Table name.
     * @return bool
     */
    private function is_question_table(string $table): bool {
        return $table === 'question'
            || $table === 'question_answers'
            || $table === 'question_hints'
            || str_starts_with($table, 'qtype_');
    }

    private function is_allowed(string $table, string $field): bool {
        $allowed = [
            'course' => ['fullname', 'summary'],
            'course_sections' => ['name', 'summary'],
            'page' => ['name', 'intro', 'content'],
            'book' => ['name', 'intro'],
            'book_chapters' => ['title', 'content'],
            'label' => ['name', 'intro'],
            'assign' => ['name', 'intro'],
            'forum' => ['name', 'intro'],
            'quiz' => ['name', 'intro'],
            'quiz_sections' => ['heading'],
            'glossary' => ['name', 'intro'],
            'glossary_entries' => ['concept', 'definition'],
            'question' => ['name', 'questiontext', 'generalfeedback'],
            'question_answers' => ['answer', 'feedback'],
            'question_hints' => ['hint'],
            'qtype_multichoice_options' => ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'],
            'qtype_essay_options' => ['graderinfo', 'responsetemplate'],
            'qtype_match_subquestions' => ['questiontext', 'answertext'],
        ];
        return isset($allowed[$table]) && in_array($field, $allowed[$table], true);
    }
}
