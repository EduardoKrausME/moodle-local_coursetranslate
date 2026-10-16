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
 * Produce bounded translation job reports and concurrency hashes.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate\mcp;

use local_coursetranslate\job_service;
use stdClass;

/**
 * Read-only projection of translation progress, with source-hash verification.
 */
final class job_snapshot {
    /** Maximum items returned per response. */
    public const MAX_PAGE = 40;

    /**
     * Inspect translation progress and build a concurrency snapshot.
     *
     * @param stdClass $job Job record.
     * @return array
     */
    public static function inspect(stdClass $job): array {
        $items = job_service::get_items((int)$job->id);
        $current = job_service::current_map($job);
        $counts = ["pending" => 0, "translated" => 0, "failed" => 0,
            "outdated" => 0, "missing" => 0, "applied" => 0];
        $list = [];
        $signatures = [(int)$job->id, (int)$job->courseid, (string)$job->status,
            (int)$job->copycourseid];
        foreach ($items as $item) {
            $now = $current[$item->localkey] ?? null;
            $status = job_service::display_status($item, $now);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            $metadata = json_decode((string)$item->metadatajson, true);
            $list[] = [
                "itemid" => (int)$item->id,
                "label" => is_array($metadata)
                    ? (string)($metadata["label"] ?? $item->fieldname) : (string)$item->fieldname,
                "itemtype" => (string)$item->itemtype,
                "field" => (string)$item->fieldname,
                "status" => $status,
                "has_translation" => $item->translatedvalue !== null,
                "sourcehash" => (string)$item->sourcehash,
                "translatedhash" => (string)($item->translatedhash ?? ""),
            ];
            $signatures[] = [
                (int)$item->id, (string)$item->localkey, (string)$item->state,
                (string)$item->sourcehash, (string)($item->translatedhash ?? ""),
                hash("sha256", (string)($item->translatedvalue ?? "")),
                (string)($now["sourcehash"] ?? "missing"),
            ];
        }
        return [
            "courseid" => (int)$job->courseid,
            "jobid" => (int)$job->id,
            "sourcelang" => (string)$job->sourcelang,
            "targetlang" => (string)$job->targetlang,
            "status" => (string)$job->status,
            "copycourseid" => $job->copycourseid === null ? null : (int)$job->copycourseid,
            "counts" => $counts,
            "total" => count($list),
            "state_hash" => hash("sha256", json_encode($signatures, JSON_THROW_ON_ERROR)),
            "items" => $list,
        ];
    }

    /**
     * Reject requests prepared against a previous job state.
     *
     * @param array $snapshot Read-only job report.
     * @param string $expected Expected state hash.
     * @return void
     */
    public static function require_state(array $snapshot, string $expected): void {
        if (!preg_match("/^[a-f0-9]{64}$/", $expected) ||
                !hash_equals($snapshot["state_hash"], $expected)) {
            throw new \moodle_exception("mcpstalejob", "local_coursetranslate");
        }
    }

    /**
     * Verify that all selected item IDs belong to the job, without silently ignoring IDs.
     *
     * @param array $snapshot Job projection.
     * @param array $itemids Selected item IDs.
     * @return array
     */
    public static function select(array $snapshot, array $itemids): array {
        if (!$itemids || count($itemids) > 30) {
            throw new \moodle_exception("mcpinvaliditems", "local_coursetranslate");
        }
        $available = array_fill_keys(array_column($snapshot["items"], "itemid"), true);
        $ids = [];
        foreach ($itemids as $raw) {
            if (!is_int($raw) || $raw <= 0 || !isset($available[$raw])) {
                throw new \moodle_exception("mcpinvaliditems", "local_coursetranslate");
            }
            $ids[$raw] = $raw;
        }
        if (count($ids) !== count($itemids)) {
            throw new \moodle_exception("mcpinvaliditems", "local_coursetranslate");
        }
        return array_values($ids);
    }

    /**
     * Return a bounded list of selected field metadata.
     *
     * @param array $snapshot Job projection.
     * @param array $ids Selection.
     * @return array
     */
    public static function describe_selection(array $snapshot, array $ids): array {
        $wanted = array_fill_keys($ids, true);
        return array_values(array_filter($snapshot["items"],
            static fn(array $item): bool => isset($wanted[$item["itemid"]])));
    }

    /**
     * Common schema for explicit selections and state concurrency checks.
     *
     * @return array
     */
    public static function selection_schema(): array {
        return [
            "jobid" => ["type" => "integer", "minimum" => 1],
            "itemids" => ["type" => "array", "minItems" => 1, "maxItems" => 30,
                "uniqueItems" => true, "items" => ["type" => "integer", "minimum" => 1]],
            "expected_state_hash" => ["type" => "string", "pattern" => "^[a-f0-9]{64}$",
                "description" => "Use the state_hash returned by coursetranslate_get_job."],
        ];
    }
}
