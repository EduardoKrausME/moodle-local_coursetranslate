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
 * Shared MCP READ permissions for translation jobs.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use context;
use context_course;
use local_mcp\read\tool\base_tool;

/**
 * Resolve context for a translation job without trusting a caller-supplied course.
 */
abstract class read_base extends base_tool {
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
     * @param array $arguments Tool arguments.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        global $DB;
        $job = $DB->get_record("local_coursetranslate_job",
            ["id" => (int)$arguments["jobid"]], "id,courseid", MUST_EXIST);
        return context_course::instance((int)$job->courseid, MUST_EXIST);
    }
}
