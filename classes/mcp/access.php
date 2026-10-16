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

/**
 * Check course translation permissions for MCP identities.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use context_course;
use context_coursecat;
use local_mcp\security\authenticated_identity;
use stdClass;

/**
 * All MCP operations use the authenticated user's course permissions.
 */
final class access {
    /**
     * Load a Moodle course and check the connected user's permissions.
     *
     * @param int $courseid Course identifier.
     * @param authenticated_identity $identity Authenticated user.
     * @return stdClass
     */
    public static function course(int $courseid, authenticated_identity $identity): stdClass {
        $course = get_course($courseid);
        require_capability("local/coursetranslate:translate",
            context_course::instance($courseid, MUST_EXIST), $identity->userid);
        return $course;
    }

    /**
     * Load an authorized translation job.
     *
     * @param int $jobid Translation job identifier.
     * @param authenticated_identity $identity Authenticated user.
     * @return stdClass
     */
    public static function job(int $jobid, authenticated_identity $identity): stdClass {
        global $DB;
        $job = $DB->get_record("local_coursetranslate_job", ["id" => $jobid], "*", MUST_EXIST);
        self::course((int)$job->courseid, $identity);
        return $job;
    }

    /**
     * Course copying also needs the native Moodle permissions used by copy_service.
     *
     * @param stdClass $job Job.
     * @param authenticated_identity $identity Authenticated user.
     * @return void
     */
    public static function copy(stdClass $job, authenticated_identity $identity): void {
        $course = self::course((int)$job->courseid, $identity);
        require_capability("moodle/course:create",
            context_coursecat::instance((int)$course->category), $identity->userid);
        require_capability("moodle/backup:backupcourse",
            context_course::instance((int)$course->id), $identity->userid);
    }
}
