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

namespace local_coursetranslate\local;

use context_course;
use context_coursecat;
use core_course_external;
use core_text;
use moodle_exception;
use stdClass;

/**
 * Create a hidden Moodle course duplicate and apply translated fields to it.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class copy_service {
    /**
     * Create translated copy.
     *
     * @param int $jobid Job id.
     * @param array<int> $itemids Selected translated items.
     * @param string $fullname Final copy fullname.
     * @param string $shortname Final copy shortname.
     * @return array{courseid:int,applied:int,skipped:int}
     */
    public function create(
        int $jobid,
        array $itemids,
        string $fullname,
        string $shortname
    ): array {
        global $CFG, $DB;

        $job = job_service::get_job($jobid);
        $sourcecourse = $DB->get_record('course', ['id' => $job->courseid], '*', MUST_EXIST);
        require_capability('moodle/course:create', context_coursecat::instance($sourcecourse->category));
        require_capability('moodle/backup:backupcourse', context_course::instance($sourcecourse->id));

        $fullname = trim(clean_param($fullname, PARAM_TEXT));
        $shortname = trim(clean_param($shortname, PARAM_TEXT));
        if ($fullname === '' || $shortname === '') {
            throw new moodle_exception('required');
        }
        if ($DB->record_exists('course', ['shortname' => $shortname])) {
            throw new moodle_exception('shortnametaken', '', '', $shortname);
        }

        $apply = new apply_service();
        $freshids = $apply->fresh_itemids($job, $itemids);
        if (!$freshids) {
            throw new moodle_exception('nonelected', 'local_coursetranslate');
        }

        require_once($CFG->dirroot . '/course/externallib.php');
        $result = core_course_external::duplicate_course(
            (int)$sourcecourse->id,
            $fullname,
            $shortname,
            (int)$sourcecourse->category,
            0,
            []
        );
        $newcourseid = (int)$result['id'];

        $applyresult = $apply->apply_to_course($job, $freshids, $newcourseid, false);

        // Respect explicit copy naming even if course/fullname was among translated fields.
        $DB->set_field('course', 'fullname', $fullname, ['id' => $newcourseid]);
        $DB->set_field('course', 'shortname', $shortname, ['id' => $newcourseid]);
        $DB->set_field('course', 'visible', 0, ['id' => $newcourseid]);
        rebuild_course_cache($newcourseid, true);

        $job->copycourseid = $newcourseid;
        $job->status = 'copied';
        $job->timemodified = time();
        $DB->update_record('local_coursetranslate_job', $job);
        return [
            'courseid' => $newcourseid,
            'applied' => $applyresult['applied'],
            'skipped' => $applyresult['skipped'] + (count($itemids) - count($freshids)),
        ];
    }

    /**
     * Suggest a unique shortname for a target language.
     *
     * @param stdClass $course Source course.
     * @param string $targetlang Target language.
     * @return string
     */
    public static function suggest_shortname(stdClass $course, string $targetlang): string {
        global $DB;

        $base = trim((string)$course->shortname) . '-' . $targetlang;
        $base = core_text::substr($base, 0, 240);
        $candidate = $base;
        $counter = 2;
        while ($DB->record_exists('course', ['shortname' => $candidate])) {
            $candidate = core_text::substr($base, 0, 235) . '-' . $counter++;
        }
        return $candidate;
    }

    /**
     * Suggest a translated fullname if the job contains one.
     *
     * @param stdClass $job Job.
     * @return string
     */
    public static function suggest_fullname(stdClass $job): string {
        global $DB;

        foreach (job_service::get_items((int)$job->id) as $item) {
            $locator = json_decode((string)$item->locatorjson, true);
            if (($locator['path'] ?? '') === 'course/fullname' && $item->translatedvalue !== null) {
                return (string)$item->translatedvalue;
            }
        }
        $course = $DB->get_record('course', ['id' => $job->courseid], '*', MUST_EXIST);
        return (string)$course->fullname . ' (' . $job->targetlang . ')';
    }
}
