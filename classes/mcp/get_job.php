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
 * Inspect translation job progress and stale fields through MCP.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use local_mcp\security\authenticated_identity;

/**
 * Return state tokens and field metadata, excluding raw course content.
 */
final class get_job extends read_base {
    /**
     * @return string
     */
    public function get_name(): string {
        return "coursetranslate_get_job";
    }

    /**
     * @return string
     */
    public function get_title(): string {
        return get_string("mcpgetjob", "local_coursetranslate");
    }

    /**
     * @return string
     */
    public function get_description(): string {
        return get_string("mcpgetjobdesc", "local_coursetranslate");
    }

    /**
     * @return array
     */
    public function get_input_schema(): array {
        return $this->object_schema([
            "jobid" => ["type" => "integer", "minimum" => 1],
            "offset" => ["type" => "integer", "minimum" => 0],
            "limit" => ["type" => "integer", "minimum" => 1, "maximum" => job_snapshot::MAX_PAGE],
        ], ["jobid"]);
    }

    /**
     * @param array $arguments Arguments.
     * @param authenticated_identity $identity User.
     * @return array
     */
    public function execute(array $arguments, authenticated_identity $identity): array {
        $job = access::job((int)$arguments["jobid"], $identity);
        $snapshot = job_snapshot::inspect($job);
        $offset = max(0, (int)($arguments["offset"] ?? 0));
        $limit = max(1, min(job_snapshot::MAX_PAGE, (int)($arguments["limit"] ?? 20)));
        $snapshot["items"] = array_slice($snapshot["items"], $offset, $limit);
        $snapshot["offset"] = $offset;
        $snapshot["limit"] = $limit;
        $snapshot["has_more"] = $offset + count($snapshot["items"]) < $snapshot["total"];
        return $snapshot;
    }
}
