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
 * Apply selected translations to the original course after confirmation.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use local_coursetranslate\apply_service;
use local_mcp\security\authenticated_identity;

/**
 * Deliberately destructive operation: changes existing teacher-authored content.
 */
final class apply_original extends write_base {
    /**
     * Return the unique MCP tool name.
     *
     * @return string
     */
    public function get_name(): string {
        return "coursetranslate_apply_original";
    }

    /**
     * Return the translated MCP tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpapplyoriginal", "local_coursetranslate");
    }

    /**
     * Return the translated description of this tool.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpapplyoriginaldesc", "local_coursetranslate");
    }

    /**
     * Identify this action as a destructive course update.
     *
     * @return bool
     */
    public function is_destructive(): bool {
        return true;
    }

    /**
     * Declare the valid arguments for the MCP tool.
     *
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema(job_snapshot::selection_schema(),
            ["jobid", "itemids", "expected_state_hash"]);
    }

    /**
     * Verify selected fields without modifying the original course.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Connected user.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        [$job, $snapshot, $ids] = $this->checked_selection($arguments, $identity);
        $fresh = (new apply_service())->fresh_itemids($job, $ids);
        if (!$fresh) {
            throw new \moodle_exception("mcpnofreshfields", "local_coursetranslate");
        }
        return [
            "operation" => $this->get_name(),
            "jobid" => (int)$job->id,
            "courseid" => (int)$job->courseid,
            "selected_fields" => job_snapshot::describe_selection($snapshot, $ids),
            "fresh_translations" => count($fresh),
            "selected_count" => count($ids),
            "will_change_original_course" => true,
            "question_bank_fields_skipped_by_service" => true,
            "may_skip_changed_or_unsupported_fields" => true,
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
        [$job, $snapshot, $ids] = $this->checked_selection($arguments, $identity);
        $fresh = (new apply_service())->fresh_itemids($job, $ids);
        if (!$fresh) {
            throw new \moodle_exception("mcpnofreshfields", "local_coursetranslate");
        }
        return [
            "jobid" => (int)$job->id,
            "courseid" => (int)$job->courseid,
            "result" => (new apply_service())->apply_original((int)$job->id, $ids),
            "original_course_modified" => true,
        ];
    }
}
