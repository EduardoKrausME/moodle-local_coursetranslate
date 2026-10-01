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

namespace local_coursetranslate\local;

use context_course;
use local_coursetranslate\local\content\collector;
use moodle_exception;
use stdClass;

/**
 * Translation job persistence and snapshot management.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_service {
    /**
     * Create a translation job and snapshot all supported fields.
     *
     * @param int $courseid Course id.
     * @param int $userid Creator user id.
     * @param array $data Form data.
     * @return int Job id.
     */
    public static function create(int $courseid, int $userid, array $data): int {
        global $DB;

        $context = context_course::instance($courseid);
        require_capability('local/coursetranslate:translate', $context, $userid);

        $sourcelang = clean_param((string)$data['sourcelang'], PARAM_ALPHANUMEXT);
        $targetlang = clean_param((string)$data['targetlang'], PARAM_ALPHANUMEXT);
        if ($sourcelang === $targetlang) {
            throw new moodle_exception('error:samelanguage', 'local_coursetranslate');
        }

        $terminology = self::parse_terminology((string)($data['terminology'] ?? ''));
        $options = [
            'coursefullname' => !empty($data['coursefullname']),
            'coursesummary' => !empty($data['coursesummary']),
            'includeglossary' => !empty($data['includeglossary']),
        ];
        $collector = new collector();
        $descriptors = $collector->collect($courseid, $options);
        if (!$descriptors) {
            throw new moodle_exception('emptycourse', 'local_coursetranslate');
        }

        $now = time();
        $job = (object)[
            'courseid' => $courseid,
            'userid' => $userid,
            'sourcelang' => $sourcelang,
            'targetlang' => $targetlang,
            'terminology' => json_encode($terminology, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'optionsjson' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'draft',
            'copycourseid' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $transaction = $DB->start_delegated_transaction();
        $jobid = (int)$DB->insert_record('local_coursetranslate_job', $job);
        foreach ($descriptors as $descriptor) {
            $metadata = $descriptor['metadata'];
            $metadata['label'] = $descriptor['label'];
            $record = (object)[
                'jobid' => $jobid,
                'localkey' => $descriptor['localkey'],
                'component' => $descriptor['component'],
                'itemtype' => $descriptor['itemtype'],
                'sourcetable' => $descriptor['sourcetable'],
                'sourceid' => $descriptor['sourceid'],
                'fieldname' => $descriptor['fieldname'],
                'contentformat' => $descriptor['contentformat'],
                'sourcehash' => $descriptor['sourcehash'],
                'sourcevalue' => $descriptor['value'],
                'translatedhash' => null,
                'translatedvalue' => null,
                'state' => 'pending',
                'errormessage' => null,
                'locatorjson' => json_encode($descriptor['locator'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'metadatajson' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_coursetranslate_item', $record);
        }
        $transaction->allow_commit();
        return $jobid;
    }

    /**
     * Get one job and enforce course capability.
     *
     * @param int $jobid Job id.
     * @return stdClass
     */
    public static function get_job(int $jobid): stdClass {
        global $DB;

        $job = $DB->get_record('local_coursetranslate_job', ['id' => $jobid], '*', MUST_EXIST);
        require_capability('local/coursetranslate:translate', context_course::instance($job->courseid));
        return $job;
    }

    /**
     * Get job options.
     *
     * @param stdClass $job Job.
     * @return array
     */
    public static function options(stdClass $job): array {
        $options = json_decode((string)$job->optionsjson, true);
        return is_array($options) ? $options : [];
    }

    /**
     * Get mandatory terminology.
     *
     * @param stdClass $job Job.
     * @return array
     */
    public static function terminology(stdClass $job): array {
        $terms = json_decode((string)$job->terminology, true);
        return is_array($terms) ? $terms : [];
    }

    /**
     * Get items for a job.
     *
     * @param int $jobid Job id.
     * @return array<int,stdClass>
     */
    public static function get_items(int $jobid): array {
        global $DB;
        return $DB->get_records('local_coursetranslate_item', ['jobid' => $jobid], 'id ASC');
    }

    /**
     * Recollect the current course and index descriptors by stable local key.
     *
     * @param stdClass $job Job.
     * @param int|null $courseid Alternate course, e.g. translated copy.
     * @return array<string,array>
     */
    public static function current_map(stdClass $job, ?int $courseid = null): array {
        $collector = new collector();
        return $collector->collect($courseid ?? (int)$job->courseid, self::options($job));
    }

    /**
     * Compute current display status of a stored item.
     *
     * @param stdClass $item Stored item.
     * @param array|null $current Current descriptor.
     * @return string
     */
    public static function display_status(stdClass $item, ?array $current): string {
        if ($current === null) {
            return 'missing';
        }
        $currenthash = $current['sourcehash'];
        if ($item->translatedhash && hash_equals((string)$item->translatedhash, $currenthash)) {
            return 'applied';
        }
        if (!hash_equals((string)$item->sourcehash, $currenthash)) {
            return 'outdated';
        }
        return (string)$item->state;
    }

    /**
     * Refresh a stored snapshot from the current course descriptor.
     *
     * This is used immediately before (re)translation, so an edited source field
     * does not accidentally send an obsolete snapshot to the AI.
     *
     * @param stdClass $item Stored item.
     * @param array $descriptor Current descriptor.
     * @return stdClass Updated item.
     */
    public static function refresh_item(stdClass $item, array $descriptor): stdClass {
        global $DB;

        $metadata = $descriptor['metadata'];
        $metadata['label'] = $descriptor['label'];
        $item->component = $descriptor['component'];
        $item->itemtype = $descriptor['itemtype'];
        $item->sourcetable = $descriptor['sourcetable'];
        $item->sourceid = $descriptor['sourceid'];
        $item->fieldname = $descriptor['fieldname'];
        $item->contentformat = $descriptor['contentformat'];
        $item->sourcehash = $descriptor['sourcehash'];
        $item->sourcevalue = $descriptor['value'];
        $item->locatorjson = json_encode($descriptor['locator'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $item->metadatajson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $item->state = 'pending';
        $item->errormessage = null;
        $item->translatedhash = null;
        $item->translatedvalue = null;
        $item->timemodified = time();
        $DB->update_record('local_coursetranslate_item', $item);
        return $item;
    }

    /**
     * Parse one Source = Target mapping per line.
     *
     * @param string $text User-provided terminology.
     * @return array<string,string>
     */
    public static function parse_terminology(string $text): array {
        $terms = [];
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s*=\s*/u', $line, 2);
            if (!$parts || count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new moodle_exception('error:invalidterminology', 'local_coursetranslate', '', $line);
            }
            $terms[trim($parts[0])] = trim($parts[1]);
        }
        return $terms;
    }

    /**
     * Build language options from installed packs plus common translation targets.
     *
     * @return array<string,string>
     */
    public static function language_options(): array {
        $common = [
            'pt_br' => 'Português (Brasil)',
            'en' => 'English',
            'en_us' => 'English (United States)',
            'es' => 'Español',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'it' => 'Italiano',
            'nl' => 'Nederlands',
            'pl' => 'Polski',
            'ru' => 'Русский',
            'ja' => '日本語',
            'ko' => '한국어',
            'zh_cn' => '简体中文',
            'zh_tw' => '繁體中文',
            'ar' => 'العربية',
        ];
        $installed = get_string_manager()->get_list_of_translations();
        foreach ($installed as $code => $name) {
            $common[$code] = $name;
        }
        asort($common, SORT_NATURAL | SORT_FLAG_CASE);
        return $common;
    }
}
