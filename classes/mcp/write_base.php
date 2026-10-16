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
 * Common confirmed MCP write handling for translation jobs.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use context;
use context_course;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;

/**
 * Validate the job state and explicit item IDs on preview and execution.
 */
abstract class write_base extends base_tool {
    /**
     * Return the Moodle capability required for this tool.
     *
     * @return string
     */
    public function get_required_capability(): string {
        return "local/coursetranslate:translate";
    }

    /**
     * Resolve the Moodle context of the requested translation job or course.
     *
     * @param array $arguments Input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        global $DB;
        $job = $DB->get_record("local_coursetranslate_job",
            ["id" => (int)$arguments["jobid"]], "id,courseid", MUST_EXIST);
        return context_course::instance((int)$job->courseid, MUST_EXIST);
    }

    /**
     * Validate the job state and selected field identifiers.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Authenticated user.
     * @return array
     */
    protected function checked_selection(array $arguments, authenticated_identity $identity): array {
        $job = access::job((int)$arguments["jobid"], $identity);
        $snapshot = job_snapshot::inspect($job);
        job_snapshot::require_state($snapshot, (string)$arguments["expected_state_hash"]);
        $ids = job_snapshot::select($snapshot, (array)$arguments["itemids"]);
        return [$job, $snapshot, $ids];
    }
}
