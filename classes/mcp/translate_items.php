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
 * Translate selected fields through the existing AI Bridge service.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use local_coursetranslate\translation\translator;
use local_mcp\security\authenticated_identity;

/**
 * Keep synchronous AI work bounded and always require confirmation.
 */
final class translate_items extends write_base {
    /** Maximum translation fields in a single confirmed call. */
    public const MAX_ITEMS = 8;

    /**
     * Return the unique MCP tool name.
     *
     * @return string
     */
    public function get_name(): string {
        return "coursetranslate_translate_items";
    }

    /**
     * Return the translated MCP tool title.
     *
     * @return string
     */
    public function get_title(): string {
        return get_string("mcptranslateitems", "local_coursetranslate");
    }

    /**
     * Return the translated description of this tool.
     *
     * @return string
     */
    public function get_description(): string {
        return get_string("mcptranslateitemsdesc", "local_coursetranslate");
    }

    /**
     * Declare the valid arguments for the MCP tool.
     *
     * @return array
     */
    public function get_input_schema(): array {
        $props = job_snapshot::selection_schema();
        $props["itemids"]["maxItems"] = self::MAX_ITEMS;
        return $this->object_schema($props, ["jobid", "itemids", "expected_state_hash"]);
    }

    /**
     * Describe the requested translation action without side effects.
     *
     * @param array $arguments Input.
     * @param authenticated_identity $identity Connected user.
     * @return array
     */
    public function preview(array $arguments, authenticated_identity $identity): array {
        [$job, $snapshot, $ids] = $this->checked_selection($arguments, $identity);
        if (count($ids) > self::MAX_ITEMS) {
            throw new \moodle_exception("mcpbatchlarge", "local_coursetranslate");
        }
        return [
            "operation" => $this->get_name(),
            "jobid" => (int)$job->id,
            "selected_fields" => job_snapshot::describe_selection($snapshot, $ids),
            "may_consume_ai_credits" => true,
            "will_refresh_source_snapshots" => true,
            "will_update_translation_job" => true,
            "course_content_modified" => false,
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
        if (count($ids) > self::MAX_ITEMS) {
            throw new \moodle_exception("mcpbatchlarge", "local_coursetranslate");
        }
        $result = (new translator())->translate((int)$job->id, $ids);
        return [
            "jobid" => (int)$job->id,
            "requested" => count($ids),
            "result" => $result,
            "next_read_tool" => "coursetranslate_get_job",
        ];
    }
}
