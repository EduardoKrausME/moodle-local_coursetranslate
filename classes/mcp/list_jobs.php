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
 * List course translation jobs with a bounded MCP response.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use context;
use context_course;
use local_mcp\read\tool\base_tool;
use local_mcp\security\authenticated_identity;

/**
 * List course jobs visible to users with the existing translation capability.
 */
final class list_jobs extends base_tool {
    /**
     * @return string
     */
    public function get_name(): string {
        return "coursetranslate_list_jobs";
    }

    /**
     * @return string
     */
    public function get_title(): string {
        return get_string("mcplistjobs", "local_coursetranslate");
    }

    /**
     * @return string
     */
    public function get_description(): string {
        return get_string("mcplistjobsdesc", "local_coursetranslate");
    }

    /**
     * @return string
     */
    public function get_required_capability(): string {
        return "local/coursetranslate:translate";
    }

    /**
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 1],
            "offset" => ["type" => "integer", "minimum" => 0],
            "limit" => ["type" => "integer", "minimum" => 1, "maximum" => 40],
        ], ["courseid"]);
    }

    /**
     * @param array $arguments Input arguments.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        get_course((int)$arguments["courseid"]);
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    /**
     * @param array $arguments Input arguments.
     * @param authenticated_identity $identity User.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $courseid = (int)$arguments["courseid"];
        access::course($courseid, $identity);
        $offset = max(0, (int)($arguments["offset"] ?? 0));
        $limit = max(1, min(40, (int)($arguments["limit"] ?? 20)));
        $total = $DB->count_records("local_coursetranslate_job", ["courseid" => $courseid]);
        $records = $DB->get_records("local_coursetranslate_job", ["courseid" => $courseid],
            "timecreated DESC, id DESC", "id,userid,sourcelang,targetlang,status,copycourseid,timecreated",
            $offset, $limit);
        $jobs = [];
        foreach ($records as $job) {
            $jobs[] = [
                "jobid" => (int)$job->id,
                "creatorid" => (int)$job->userid,
                "sourcelang" => (string)$job->sourcelang,
                "targetlang" => (string)$job->targetlang,
                "status" => (string)$job->status,
                "copycourseid" => $job->copycourseid === null ? null : (int)$job->copycourseid,
                "timecreated" => (int)$job->timecreated,
            ];
        }
        return ["courseid" => $courseid, "total" => $total, "offset" => $offset,
            "limit" => $limit, "has_more" => $offset + count($jobs) < $total, "jobs" => $jobs];
    }
}
