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
 * view.php
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_coursetranslate\job_service;

require_once(__DIR__ . '/../../config.php');

$jobid = required_param('id', PARAM_INT);
$job = job_service::get_job($jobid);
$course = get_course($job->courseid);
$context = context_course::instance($course->id);
require_login($course);
require_capability('local/coursetranslate:translate', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/coursetranslate/view.php', ['id' => $jobid]));
$PAGE->set_title(get_string('job', 'local_coursetranslate'));
$PAGE->set_heading(format_string($course->fullname));

$items = job_service::get_items($jobid);
$currentmap = job_service::current_map($job);
$counts = ['pending' => 0, 'translated' => 0, 'failed' => 0, 'outdated' => 0, 'applied' => 0, 'missing' => 0];
$statusmap = [];
foreach ($items as $item) {
    $status = job_service::display_status($item, $currentmap[$item->localkey] ?? null);
    $statusmap[$item->id] = $status;
    $counts[$status] = ($counts[$status] ?? 0) + 1;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(
    get_string('job', 'local_coursetranslate') . ': ' . s($job->sourcelang . ' → ' . $job->targetlang)
);
echo html_writer::div(
    html_writer::span(get_string('summary:pending', 'local_coursetranslate', $counts['pending']), 'badge text-bg-secondary') . ' ' .
    html_writer::span(get_string('summary:translated', 'local_coursetranslate', $counts['translated']), 'badge text-bg-success') . ' ' .
    html_writer::span(get_string('summary:failed', 'local_coursetranslate', $counts['failed']), 'badge text-bg-danger') . ' ' .
    html_writer::span(get_string('summary:outdated', 'local_coursetranslate', $counts['outdated']), 'badge text-bg-warning') . ' ' .
    html_writer::span(get_string('summary:applied', 'local_coursetranslate', $counts['applied']), 'badge text-bg-info'),
    'local-coursetranslate-summary'
);
echo $OUTPUT->notification(get_string('refreshhint', 'local_coursetranslate'), 'info');

$actionurl = new moodle_url('/local/coursetranslate/action.php');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $actionurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'jobid', 'value' => $jobid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('mb-3 d-flex flex-wrap gap-2');
echo html_writer::tag('button', get_string('translatepending', 'local_coursetranslate'), [
    'type' => 'submit', 'name' => 'action', 'value' => 'translate_pending', 'class' => 'btn btn-primary',
]);
echo html_writer::tag('button', get_string('translateselected', 'local_coursetranslate'), [
    'type' => 'submit', 'name' => 'action', 'value' => 'translate_selected', 'class' => 'btn btn-secondary',
]);
echo html_writer::tag('button', get_string('applyselected', 'local_coursetranslate'), [
    'type' => 'submit', 'name' => 'action', 'value' => 'apply_selected', 'class' => 'btn btn-outline-danger',
]);
echo html_writer::tag('button', get_string('createcopy', 'local_coursetranslate'), [
    'type' => 'submit', 'name' => 'action', 'value' => 'copy_selected', 'class' => 'btn btn-outline-primary',
]);
echo html_writer::end_div();

$table = new html_table();
$table->attributes['class'] = 'generaltable local-coursetranslate-table';
$table->head = [
    get_string('select', 'local_coursetranslate'),
    get_string('field', 'local_coursetranslate'),
    get_string('source', 'local_coursetranslate'),
    get_string('translation', 'local_coursetranslate'),
    get_string('status', 'local_coursetranslate'),
];
foreach ($items as $item) {
    $metadata = json_decode((string)$item->metadatajson, true) ?: [];
    $label = $metadata['label'] ?? ($item->itemtype . '/' . $item->fieldname);
    $status = $statusmap[$item->id];
    $statuslabel = get_string($status === 'applied' ? 'appliedstatus' : $status, 'local_coursetranslate');
    $statusclass = match ($status) {
        'translated' => 'badge text-bg-success',
        'failed', 'missing' => 'badge text-bg-danger',
        'outdated' => 'badge text-bg-warning',
        'applied' => 'badge text-bg-info',
        default => 'badge text-bg-secondary',
    };
    $rowclass = $status === 'outdated'
        ? 'local-coursetranslate-item-outdated'
        : ($status === 'failed' ? 'local-coursetranslate-item-error' : '');

    $checkbox = html_writer::empty_tag('input', [
        'type' => 'checkbox', 'name' => 'itemids[]', 'value' => $item->id, 'class' => 'form-check-input',
    ]);
    $source = html_writer::tag('div', s((string)$item->sourcevalue), ['class' => 'local-coursetranslate-preview']);
    $translation = $item->translatedvalue === null
        ? html_writer::span('—', 'text-muted')
        : html_writer::tag('div', s((string)$item->translatedvalue), ['class' => 'local-coursetranslate-preview']);
    if ($item->errormessage) {
        $translation .= html_writer::div(s((string)$item->errormessage), 'text-danger small mt-2');
    }
    $row = new html_table_row([
        $checkbox,
        s((string)$label),
        $source,
        $translation,
        html_writer::span($statuslabel, $statusclass),
    ]);
    $row->attributes['class'] = $rowclass;
    $table->data[] = $row;
}
echo html_writer::table($table);
echo html_writer::end_tag('form');

$back = new moodle_url('/local/coursetranslate/index.php', ['courseid' => $course->id]);
echo html_writer::div(html_writer::link($back, get_string('newjob', 'local_coursetranslate')), 'mt-3');
echo $OUTPUT->footer();
