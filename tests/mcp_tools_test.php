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
 * Regression tests for optional course translation MCP tools.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetranslate;

use advanced_testcase;
use context_course;
use local_coursetranslate\mcp\job_snapshot;
use local_coursetranslate\mcp\get_field;
use local_coursetranslate\mcp\get_job;
use local_coursetranslate\mcp\create_job;
use local_coursetranslate\mcp\translate_items;
use local_coursetranslate\mcp\apply_original;
use local_coursetranslate\mcp\create_copy;
use moodle_exception;

/**
 * Validate state tokens, permission isolation and side-effect-free previews.
 *
 * @covers \local_coursetranslate\mcp\job_snapshot
 * @covers \local_coursetranslate\mcp\access
 * @covers \local_coursetranslate\mcp\create_job
 * @covers \local_coursetranslate\mcp\translate_items
 * @covers \local_coursetranslate\mcp\apply_original
 * @covers \local_coursetranslate\mcp\create_copy
 * @covers \local_coursetranslate\mcp\get_job
 * @covers \local_coursetranslate\mcp\get_field
 */
final class mcp_tools_test extends advanced_testcase {
    /**
     * Create a real job without making any AI requests.
     *
     * @return array
     */
    private function make_job(): array {
        global $USER;
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(["fullname" => "English course"]);
        $jobid = job_service::create((int)$course->id, (int)$USER->id, [
            "sourcelang" => "en",
            "targetlang" => "pt_br",
            "coursefullname" => true,
            "coursesummary" => false,
            "includeglossary" => false,
            "terminology" => "",
        ]);
        return [$course, $jobid];
    }

    /**
     * Skip MCP execution tests when its optional dependency is not installed.
     *
     * @return void
     */
    private function require_mcp(): void {
        if (!class_exists(\local_mcp\security\authenticated_identity::class)) {
            $this->markTestSkipped("local_mcp is optional and not installed.");
        }
    }

    /**
     * State tokens must change when translations or course content changes.
     *
     * @return void
     */
    public function test_state_hash_rejects_changes(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $jobid] = $this->make_job();
        $job = $DB->get_record("local_coursetranslate_job", ["id" => $jobid], "*", MUST_EXIST);
        $first = job_snapshot::inspect($job);
        $this->assertGreaterThan(0, $first["total"]);
        $this->assertSame(0, $first["counts"]["translated"]);
        $itemid = $first["items"][0]["itemid"];
        $DB->set_field("local_coursetranslate_item", "translatedvalue", "Curso em português",
            ["id" => $itemid]);
        $DB->set_field("local_coursetranslate_item", "translatedhash",
            hash("sha256", "Curso em português"), ["id" => $itemid]);
        $DB->set_field("local_coursetranslate_item", "state", "translated",
            ["id" => $itemid]);
        $second = job_snapshot::inspect($job);
        $this->assertNotSame($first["state_hash"], $second["state_hash"]);
        $this->assertSame(1, $second["counts"]["translated"]);
        $this->expectException(moodle_exception::class);
        job_snapshot::require_state($second, $first["state_hash"]);
    }

    /**
     * Selected IDs must be present in the same job, and duplicates are invalid.
     *
     * @return void
     */
    public function test_rejects_invalid_item_selection(): void {
        $this->resetAfterTest();
        [$course, $jobid] = $this->make_job();
        $job = job_service::get_job($jobid);
        $snapshot = job_snapshot::inspect($job);
        $itemid = $snapshot["items"][0]["itemid"];
        $this->assertSame([$itemid], job_snapshot::select($snapshot, [$itemid]));
        $this->expectException(moodle_exception::class);
        job_snapshot::select($snapshot, [$itemid, $itemid]);
    }

    /**
     * Preview and reads cannot create translation records or consume AI credits.
     *
     * @return void
     */
    public function test_mcp_reads_and_previews_have_no_side_effects(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->require_mcp();
        [$course, $jobid] = $this->make_job();
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$USER->id, "oauth", ["read", "write"]
        );
        $job = (new get_job())->execute(["jobid" => $jobid], $identity);
        $this->assertGreaterThan(0, $job["total"]);
        $fieldid = $job["items"][0]["itemid"];
        $field = (new get_field())->execute([
            "jobid" => $jobid, "itemid" => $fieldid,
        ], $identity);
        $this->assertNotEmpty($field["source"]);
        $createdbefore = $DB->count_records("local_coursetranslate_job");
        $itemsbefore = $DB->count_records("local_coursetranslate_item");
        $preview = (new create_job())->preview([
            "courseid" => $course->id, "sourcelang" => "en", "targetlang" => "es",
            "coursefullname" => true,
        ], $identity);
        $this->assertFalse($preview["ai_request"]);
        $translationpreview = (new translate_items())->preview([
            "jobid" => $jobid, "itemids" => [$fieldid],
            "expected_state_hash" => $job["state_hash"],
        ], $identity);
        $this->assertTrue($translationpreview["may_consume_ai_credits"]);
        $this->assertSame($createdbefore, $DB->count_records("local_coursetranslate_job"));
        $this->assertSame($itemsbefore, $DB->count_records("local_coursetranslate_item"));
    }

    /**
     * Original-course application remains guarded by stale state and selection.
     *
     * @return void
     */
    public function test_apply_preview_does_not_modify_original(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->require_mcp();
        [$course, $jobid] = $this->make_job();
        $items = job_service::get_items($jobid);
        $item = reset($items);
        $DB->set_field("local_coursetranslate_item", "translatedvalue", "Curso traduzido",
            ["id" => $item->id]);
        $DB->set_field("local_coursetranslate_item", "translatedhash",
            hash("sha256", "Curso traduzido"), ["id" => $item->id]);
        $DB->set_field("local_coursetranslate_item", "state", "translated", ["id" => $item->id]);
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$USER->id, "oauth", ["read", "write"]
        );
        $before = $DB->get_field("course", "fullname", ["id" => $course->id]);
        $snapshot = (new get_job())->execute(["jobid" => $jobid], $identity);
        $preview = (new apply_original())->preview([
            "jobid" => $jobid, "itemids" => [(int)$item->id],
            "expected_state_hash" => $snapshot["state_hash"],
        ], $identity);
        $this->assertTrue($preview["will_change_original_course"]);
        $this->assertSame($before, $DB->get_field("course", "fullname", ["id" => $course->id]));
    }

    /**
     * Any MCP identity without the course capability is denied even if the PHP global is admin.
     *
     * @return void
     */
    public function test_read_denied_to_unprivileged_identity(): void {
        $this->resetAfterTest();
        $this->require_mcp();
        [$course, $jobid] = $this->make_job();
        $student = $this->getDataGenerator()->create_user();
        $identity = new \local_mcp\security\authenticated_identity(
            (int)$student->id, "oauth", ["read", "write"]
        );
        $this->assertFalse(has_capability(
            "local/coursetranslate:translate", context_course::instance($course->id), $student
        ));
        $this->expectException(\required_capability_exception::class);
        (new get_job())->execute(["jobid" => $jobid], $identity);
    }
}
