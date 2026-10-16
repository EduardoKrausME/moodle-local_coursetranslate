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
 * Create a translation snapshot with a confirmed MCP write.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use context;
use context_course;
use local_coursetranslate\content\collector;
use local_coursetranslate\job_service;
use local_mcp\security\authenticated_identity;
use local_mcp\write\tool\base_tool;
use moodle_url;

/**
 * Create a job without calling AI or changing any course content.
 */
final class create_job extends base_tool {
    /**
     * Return the unique MCP tool name.
     *
     * @return string
     */
    public function get_name(): string {
        return "coursetranslate_create_job";
    }

    /**
     * Return the translated MCP tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpcreatejob", "local_coursetranslate");
    }

    /**
     * Return the translated description of this tool.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpcreatejobdesc", "local_coursetranslate");
    }

    /**
     * Return the Moodle capability required for this tool.
     *
     * @return string
     */
    public function get_required_capability(): string {
        return "local/coursetranslate:translate";
    }

    /**
     * Declare the valid arguments for the MCP tool.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "courseid" => ["type" => "integer", "minimum" => 1],
            "sourcelang" => ["type" => "string", "pattern" => "^[a-z]{2,8}(?:_[a-z0-9]{2,8})?$",
                "maxLength" => 32],
            "targetlang" => ["type" => "string", "pattern" => "^[a-z]{2,8}(?:_[a-z0-9]{2,8})?$",
                "maxLength" => 32],
            "terminology" => ["type" => "string", "maxLength" => 6000],
            "coursefullname" => ["type" => "boolean"],
            "coursesummary" => ["type" => "boolean"],
            "includeglossary" => ["type" => "boolean"],
        ], ["courseid", "sourcelang", "targetlang"]);
    }

    /**
     * Resolve the Moodle context of the requested translation job or course.
     *
     * @param array $arguments Input.
     * @return context
     */
    public function resolve_context(array $arguments): context {
        get_course((int)$arguments["courseid"]);
        return context_course::instance((int)$arguments["courseid"], MUST_EXIST);
    }

    /**
     * Verify the source options without persisting a job or sending content to AI.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Connected user.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        $courseid = (int)$arguments["courseid"];
        access::course($courseid, $identity);
        $source = clean_param((string)$arguments["sourcelang"], PARAM_ALPHANUMEXT);
        $target = clean_param((string)$arguments["targetlang"], PARAM_ALPHANUMEXT);
        if ($source === $target || !preg_match("/^[a-z]{2,8}(?:_[a-z0-9]{2,8})?$/", $source) ||
                !preg_match("/^[a-z]{2,8}(?:_[a-z0-9]{2,8})?$/", $target)) {
            throw new \moodle_exception("mcpinvalidlanguage", "local_coursetranslate");
        }
        $term = (string)($arguments["terminology"] ?? "");
        if (strlen($term) > 12000) {
            throw new \moodle_exception("mcpinvalidterms", "local_coursetranslate");
        }
        $parsed = job_service::parse_terminology($term);
        $options = [
            "coursefullname" => !empty($arguments["coursefullname"]),
            "coursesummary" => !empty($arguments["coursesummary"]),
            "includeglossary" => !empty($arguments["includeglossary"]),
        ];
        $descriptors = (new collector())->collect($courseid, $options);
        if (!$descriptors) {
            throw new \moodle_exception("emptycourse", "local_coursetranslate");
        }
        return [
            "operation" => $this->get_name(),
            "courseid" => $courseid,
            "sourcelang" => $source,
            "targetlang" => $target,
            "options" => $options,
            "terminology_count" => count($parsed),
            "fields_to_snapshot" => count($descriptors),
            "ai_request" => false,
            "course_content_modified" => false,
        ];
    }

    /**
     * Execute the authorized MCP operation.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Connected user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $this->preview($arguments, $identity);
        $courseid = (int)$arguments["courseid"];
        $jobid = job_service::create($courseid, $identity->userid, $arguments);
        global $DB;
        return [
            "jobid" => $jobid,
            "courseid" => $courseid,
            "fields" => $DB->count_records("local_coursetranslate_item", ["jobid" => $jobid]),
            "next_read_tool" => "coursetranslate_get_job",
            "url" => (new moodle_url("/local/coursetranslate/view.php", ["id" => $jobid]))->out(false),
        ];
    }
}
