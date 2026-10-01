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

namespace local_coursetranslate\local\translation;

use core_text;
use local_ai_bridge\api;
use local_coursetranslate\local\job_service;
use local_coursetranslate\local\token\protector;
use moodle_exception;
use stdClass;
use Throwable;

/**
 * AI translation orchestration through local_ai_bridge only.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translator {
    /**
     * AI Bridge purpose.
     */
    private const PURPOSE = 'coursetranslate-translate';

    /**
     * Maximum number of fields per request.
     */
    private const MAX_ITEMS = 8;

    /**
     * Approximate protected characters per request.
     */
    private const MAX_CHARS = 12000;

    /**
     * Translate selected items, or pending/failed items if no selection is supplied.
     *
     * @param int $jobid Job id.
     * @param array<int>|null $itemids Selected item ids.
     * @return array{translated:int,failed:int,partial:bool}
     */
    public function translate(int $jobid, ?array $itemids = null): array {
        global $DB;

        $job = job_service::get_job($jobid);
        if (!class_exists('\\local_ai_bridge\\api')) {
            throw new moodle_exception('error:bridgeunavailable', 'local_coursetranslate');
        }

        $allitems = job_service::get_items($jobid);
        $currentmap = job_service::current_map($job);
        $selected = [];
        $selection = $itemids === null ? null : array_fill_keys(array_map('intval', $itemids), true);
        foreach ($allitems as $item) {
            if ($selection !== null && !isset($selection[(int)$item->id])) {
                continue;
            }
            if ($selection === null && !in_array($item->state, ['pending', 'failed'], true)) {
                continue;
            }
            if (!isset($currentmap[$item->localkey])) {
                $item->state = 'failed';
                $item->errormessage = get_string('missingitem', 'local_coursetranslate');
                $item->timemodified = time();
                $DB->update_record('local_coursetranslate_item', $item);
                continue;
            }
            $selected[] = job_service::refresh_item($item, $currentmap[$item->localkey]);
        }

        if (!$selected) {
            return ['translated' => 0, 'failed' => 0, 'partial' => false];
        }

        $protector = new protector();
        $terms = job_service::terminology($job);
        $prepared = [];
        foreach ($selected as $item) {
            $payload = $protector->protect((string)$item->sourcevalue, $terms);
            $metadata = json_decode((string)$item->metadatajson, true) ?: [];
            $prepared[] = [
                'item' => $item,
                'payload' => $payload,
                'id' => 'i' . (int)$item->id,
                'label' => (string)($metadata['label'] ?? $item->itemtype . '/' . $item->fieldname),
            ];
        }

        $batches = $this->make_batches($prepared);
        $translatedcount = 0;
        $failedcount = 0;
        $partial = false;
        foreach ($batches as $batch) {
            $result = $this->translate_batch($job, $batch, $protector);
            $translatedcount += $result['translated'];
            $failedcount += $result['failed'];
            $partial = $partial || $result['partial'];
        }

        $job->status = $failedcount > 0 ? 'partial' : 'translated';
        $job->timemodified = time();
        $DB->update_record('local_coursetranslate_job', $job);
        return ['translated' => $translatedcount, 'failed' => $failedcount, 'partial' => $partial];
    }

    /**
     * Translate one bounded request.
     *
     * @param stdClass $job Job.
     * @param array $batch Prepared items.
     * @param protector $protector Token protector.
     * @return array{translated:int,failed:int,partial:bool}
     */
    private function translate_batch(stdClass $job, array $batch, protector $protector): array {
        global $DB;

        $input = [];
        foreach ($batch as $entry) {
            $input[$entry['id']] = [
                'context' => $entry['label'],
                'format' => ((int)$entry['item']->contentformat === FORMAT_HTML) ? 'html-protected' : 'text-protected',
                'content' => $entry['payload']['text'],
            ];
        }
        $terms = job_service::terminology($job);
        $prompt = $this->build_prompt($job, $input, $terms);

        try {
            $response = api::generate(self::PURPOSE, [
                ['role' => 'user', 'content' => $prompt],
            ]);
            $translations = self::parse_response((string)$response->text);
        } catch (Throwable $e) {
            foreach ($batch as $entry) {
                $this->fail_item($entry['item'], get_string('bridgeerror', 'local_coursetranslate', $e->getMessage()));
            }
            return ['translated' => 0, 'failed' => count($batch), 'partial' => false];
        }

        $translated = 0;
        $failed = 0;
        $partial = false;
        foreach ($batch as $entry) {
            $id = $entry['id'];
            if (!array_key_exists($id, $translations) || !is_string($translations[$id])) {
                $this->fail_item($entry['item'], get_string('missingtranslation', 'local_coursetranslate'));
                $failed++;
                $partial = true;
                continue;
            }
            try {
                $restored = $protector->restore($entry['payload'], $translations[$id]);
                $item = $entry['item'];
                $item->translatedvalue = $restored;
                $item->translatedhash = hash('sha256', $restored);
                $item->state = 'translated';
                $item->errormessage = null;
                $item->timemodified = time();
                $DB->update_record('local_coursetranslate_item', $item);
                $translated++;
            } catch (Throwable $e) {
                $this->fail_item($entry['item'], $e->getMessage());
                $failed++;
                $partial = true;
            }
        }
        return ['translated' => $translated, 'failed' => $failed, 'partial' => $partial];
    }

    /**
     * Build a deterministic JSON-only translation prompt.
     *
     * @param stdClass $job Job.
     * @param array $input Protected fields.
     * @param array $terms Required terminology.
     * @return string
     */
    private function build_prompt(stdClass $job, array $input, array $terms): string {
        $payload = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $termjson = json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return "Translate the supplied Moodle course fields from {$job->sourcelang} to {$job->targetlang}.\n"
            . "Return JSON only in exactly this shape: {\"translations\":{\"i123\":\"translated text\"}}.\n"
            . "Every input id must map to exactly one string. Do not add explanations.\n"
            . "Tokens matching __CTP_000001__ are immutable. Copy every token exactly once. Never translate, edit, "
            . "remove, duplicate, split or invent a token. HTML tags, URLs, pluginfile references, placeholders, code, "
            . "formulas, identifiers and filenames have already been converted to immutable tokens.\n"
            . "Translate only human-readable language around the tokens. Preserve meaning and Moodle terminology.\n"
            . "Required terminology is already token-protected, but use this mapping as semantic guidance too: {$termjson}\n"
            . "INPUT:\n{$payload}";
    }

    /**
     * Split prepared items by both count and protected text size.
     *
     * @param array $prepared Prepared items.
     * @return array
     */
    private function make_batches(array $prepared): array {
        $batches = [];
        $batch = [];
        $chars = 0;
        foreach ($prepared as $entry) {
            $size = mb_strlen((string)$entry['payload']['text']);
            if ($batch && (count($batch) >= self::MAX_ITEMS || $chars + $size > self::MAX_CHARS)) {
                $batches[] = $batch;
                $batch = [];
                $chars = 0;
            }
            $batch[] = $entry;
            $chars += $size;
        }
        if ($batch) {
            $batches[] = $batch;
        }
        return $batches;
    }

    /**
     * Parse an AI response. Fenced JSON is tolerated, prose is not.
     *
     * @param string $text Provider text.
     * @return array<string,string>
     */
    public static function parse_response(string $text): array {
        $text = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/isu', $text, $matches)) {
            $text = trim($matches[1]);
        }
        if (!str_starts_with($text, '{') || !str_ends_with($text, '}')) {
            throw new moodle_exception('invalidresponse', 'local_coursetranslate');
        }
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new moodle_exception('invalidresponse', 'local_coursetranslate');
        }
        $map = $decoded['translations'] ?? $decoded;
        if (!is_array($map)) {
            throw new moodle_exception('invalidresponse', 'local_coursetranslate');
        }
        $result = [];
        foreach ($map as $id => $value) {
            if (is_string($id) && preg_match('/^i\d+$/', $id) && is_string($value)) {
                $result[$id] = $value;
            }
        }
        if (!$result) {
            throw new moodle_exception('invalidresponse', 'local_coursetranslate');
        }
        return $result;
    }

    /**
     * Mark one item failed.
     *
     * @param stdClass $item Item.
     * @param string $message Error message.
     * @return void
     */
    private function fail_item(stdClass $item, string $message): void {
        global $DB;
        $item->state = 'failed';
        $item->errormessage = core_text::substr($message, 0, 4000);
        $item->timemodified = time();
        $DB->update_record('local_coursetranslate_item', $item);
    }
}
