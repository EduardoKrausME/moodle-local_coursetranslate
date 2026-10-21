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
 * Create a hidden translated course copy after MCP confirmation.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use local_coursetranslate\apply_service;
use local_coursetranslate\copy_service;
use local_mcp\security\authenticated_identity;
use moodle_url;

/**
 * Duplicate using Moodle's native backup/restore, never raw course table cloning.
 */
final class create_copy extends write_base {
    /**
     * Return the unique MCP tool name.
     *
     * @return string
     */
    public function get_name(): string {
        return "coursetranslate_create_copy";
    }

    /**
     * Return the translated MCP tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpcreatecopy", "local_coursetranslate");
    }

    /**
     * Return the translated description of this tool.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpcreatecopydesc", "local_coursetranslate");
    }

    /**
     * Declare the valid arguments for the MCP tool.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema(job_snapshot::selection_schema() + [
            "fullname" => ["type" => "string", "minLength" => 1, "maxLength" => 254],
            "shortname" => ["type" => "string", "minLength" => 1, "maxLength" => 100],
        ], ["jobid", "itemids", "expected_state_hash", "fullname", "shortname"]);
    }

    /**
     * Check naming and backup permissions without executing a duplicate.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Connected user.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        global $DB;
        [$job, $snapshot, $ids] = $this->checked_selection($arguments, $identity);
        access::copy($job, $identity);
        if (!empty($job->copycourseid)) {
            throw new \moodle_exception("mcpcopyexists", "local_coursetranslate");
        }
        $fullname = trim(clean_param((string)$arguments["fullname"], PARAM_TEXT));
        $shortname = trim(clean_param((string)$arguments["shortname"], PARAM_TEXT));
        if ($fullname === "" || $shortname === "" || strlen($fullname) > 254 ||
                strlen($shortname) > 100) {
            throw new \moodle_exception("mcpinvalidcopyname", "local_coursetranslate");
        }
        if ($DB->record_exists("course", ["shortname" => $shortname])) {
            throw new \moodle_exception("shortnametaken", "", "", $shortname);
        }
        $fresh = (new apply_service())->fresh_itemids($job, $ids);
        if (!$fresh) {
            throw new \moodle_exception("mcpnofreshfields", "local_coursetranslate");
        }
        return [
            "operation" => $this->get_name(),
            "jobid" => (int)$job->id,
            "source_courseid" => (int)$job->courseid,
            "fullname" => $fullname,
            "shortname" => $shortname,
            "selected_fields" => job_snapshot::describe_selection($snapshot, $ids),
            "fresh_translations" => count($fresh),
            "will_create_hidden_course" => true,
            "original_course_modified" => false,
            "may_skip_unmapped_fields" => true,
        ];
    }

    /**
     * Execute the authorized translation operation.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Connected user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $this->preview($arguments, $identity);
        $result = (new copy_service())->create(
            (int)$arguments["jobid"], (array)$arguments["itemids"],
            (string)$arguments["fullname"], (string)$arguments["shortname"]
        );
        return [
            "jobid" => (int)$arguments["jobid"],
            "result" => $result,
            "hidden" => true,
            "source_course_modified" => false,
            "url" => (new moodle_url("/course/view.php", ["id" => $result["courseid"]]))->out(false),
        ];
    }
}
