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
 * Read a bounded portion of an authorized translation field.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use local_coursetranslate\job_service;
use local_mcp\security\authenticated_identity;

/**
 * Retrieve source and translation exactly as stored, without mutating records.
 */
final class get_field extends read_base {
    /**
     * @return string
     */
    public function get_name(): string {
        return "coursetranslate_get_field";
    }

    /**
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpgetfield", "local_coursetranslate");
    }

    /**
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpgetfielddesc", "local_coursetranslate");
    }

    /**
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "jobid" => ["type" => "integer", "minimum" => 1],
            "itemid" => ["type" => "integer", "minimum" => 1],
            "offset" => ["type" => "integer", "minimum" => 0],
            "length" => ["type" => "integer", "minimum" => 1, "maximum" => 12000],
        ], ["jobid", "itemid"]);
    }

    /**
     * @param array $arguments Arguments.
     * @param authenticated_identity $identity Authenticated user.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        global $DB;
        $job = access::job((int)$arguments["jobid"], $identity);
        $item = $DB->get_record("local_coursetranslate_item",
            ["jobid" => (int)$job->id, "id" => (int)$arguments["itemid"]], "*", MUST_EXIST);
        $offset = max(0, (int)($arguments["offset"] ?? 0));
        $length = max(1, min(12000, (int)($arguments["length"] ?? 4000)));
        $current = job_service::current_map($job);
        $source = (string)$item->sourcevalue;
        $translated = (string)($item->translatedvalue ?? "");
        return [
            "jobid" => (int)$job->id,
            "itemid" => (int)$item->id,
            "status" => job_service::display_status($item, $current[$item->localkey] ?? null),
            "source_length" => \core_text::strlen($source),
            "translated_length" => \core_text::strlen($translated),
            "offset" => $offset,
            "length" => $length,
            "source" => \core_text::substr($source, $offset, $length),
            "translation" => $item->translatedvalue === null
                ? null : \core_text::substr($translated, $offset, $length),
            "has_more_source" => $offset + $length < \core_text::strlen($source),
            "has_more_translation" => $offset + $length < \core_text::strlen($translated),
        ];
    }
}
