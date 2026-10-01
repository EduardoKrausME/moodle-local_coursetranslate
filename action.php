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
 * action.php
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use local_coursetranslate\local\apply_service;
use local_coursetranslate\local\copy_service;
use local_coursetranslate\local\job_service;
use local_coursetranslate\local\translation\translator;

require_once(__DIR__ . '/../../config.php');

$jobid = required_param('jobid', PARAM_INT);
$action = required_param('action', PARAM_ALPHANUMEXT);
require_sesskey();
$job = job_service::get_job($jobid);
$course = get_course($job->courseid);
require_login($course);
require_capability('local/coursetranslate:translate', context_course::instance($course->id));
$itemids = optional_param_array('itemids', [], PARAM_INT);
$viewurl = new moodle_url('/local/coursetranslate/view.php', ['id' => $jobid]);

if ($action === 'translate_pending') {
    $result = (new translator())->translate($jobid, null);
    $message = get_string('translatedcount', 'local_coursetranslate', $result['translated']);
    if ($result['partial']) {
        $message .= ' ' . get_string('translationpartial', 'local_coursetranslate');
    }
    redirect($viewurl, $message, null, notification::NOTIFY_SUCCESS);
}

if ($action === 'translate_selected') {
    if (!$itemids) {
        redirect($viewurl, get_string('nonelected', 'local_coursetranslate'), null, notification::NOTIFY_WARNING);
    }
    $result = (new translator())->translate($jobid, $itemids);
    $message = get_string('translatedcount', 'local_coursetranslate', $result['translated']);
    if ($result['partial']) {
        $message .= ' ' . get_string('translationpartial', 'local_coursetranslate');
    }
    redirect($viewurl, $message, null, notification::NOTIFY_SUCCESS);
}

if ($action === 'apply_selected') {
    if (!$itemids) {
        redirect($viewurl, get_string('nonelected', 'local_coursetranslate'), null, notification::NOTIFY_WARNING);
    }
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    if (!$confirm) {
        $PAGE->set_context(context_course::instance($course->id));
        $PAGE->set_course($course);
        $PAGE->set_url(new moodle_url('/local/coursetranslate/action.php'));
        $PAGE->set_title(get_string('confirmapply', 'local_coursetranslate'));
        $PAGE->set_heading(format_string($course->fullname));
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('confirmapply', 'local_coursetranslate'));
        echo $OUTPUT->notification(get_string('confirmapplytext', 'local_coursetranslate'), 'warning');
        echo local_coursetranslate_confirmation_form($jobid, 'apply_selected', $itemids, []);
        echo $OUTPUT->footer();
        exit;
    }
    $result = (new apply_service())->apply_original($jobid, $itemids);
    $message = get_string('applied', 'local_coursetranslate', $result['applied']);
    if ($result['skipped']) {
        $message .= ' ' . get_string('applyskipwarning', 'local_coursetranslate', $result['skipped']);
    }
    redirect($viewurl, $message, null, notification::NOTIFY_SUCCESS);
}

if ($action === 'copy_selected') {
    if (!$itemids) {
        redirect($viewurl, get_string('nonelected', 'local_coursetranslate'), null, notification::NOTIFY_WARNING);
    }
    $confirm = optional_param('confirm', 0, PARAM_BOOL);
    if (!$confirm) {
        $PAGE->set_context(context_course::instance($course->id));
        $PAGE->set_course($course);
        $PAGE->set_url(new moodle_url('/local/coursetranslate/action.php'));
        $PAGE->set_title(get_string('confirmcopy', 'local_coursetranslate'));
        $PAGE->set_heading(format_string($course->fullname));
        $fullname = copy_service::suggest_fullname($job);
        $shortname = copy_service::suggest_shortname($course, $job->targetlang);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('confirmcopy', 'local_coursetranslate'));
        echo $OUTPUT->notification(get_string('confirmcopytext', 'local_coursetranslate'), 'info');
        echo $OUTPUT->notification(get_string('copyhidden', 'local_coursetranslate'), 'info');
        echo local_coursetranslate_confirmation_form($jobid, 'copy_selected', $itemids, [
            'copyfullname' => $fullname,
            'copyshortname' => $shortname,
        ]);
        echo $OUTPUT->footer();
        exit;
    }

    $fullname = required_param('copyfullname', PARAM_TEXT);
    $shortname = required_param('copyshortname', PARAM_TEXT);
    $result = (new copy_service())->create($jobid, $itemids, $fullname, $shortname);
    $copyurl = new moodle_url('/course/view.php', ['id' => $result['courseid']]);
    $message = get_string('copycreated', 'local_coursetranslate') . ' ' . get_string('applied', 'local_coursetranslate', $result['applied']);
    if ($result['skipped']) {
        $message .= ' ' . get_string('copyapplywarning', 'local_coursetranslate', $result['skipped']);
    }
    redirect($copyurl, $message, null, notification::NOTIFY_SUCCESS);
}

redirect($viewurl);

/**
 * Render an explicit confirmation form.
 *
 * @param int $jobid Job id.
 * @param string $action Action.
 * @param array<int> $itemids Selected items.
 * @param array $fields Optional editable copy fields.
 * @return string
 */
function local_coursetranslate_confirmation_form(int $jobid, string $action, array $itemids, array $fields): string {
    $url = new moodle_url('/local/coursetranslate/action.php');
    $html = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'jobid', 'value' => $jobid]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
    $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    foreach ($itemids as $itemid) {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'itemids[]', 'value' => (int)$itemid]);
    }
    if (isset($fields['copyfullname'])) {
        $html .= html_writer::start_div('mb-3');
        $html .= html_writer::label(get_string('copyfullname', 'local_coursetranslate'), 'id_copyfullname', false, ['class' => 'form-label']);
        $html .= html_writer::empty_tag('input', [
            'id' => 'id_copyfullname', 'class' => 'form-control', 'type' => 'text', 'name' => 'copyfullname',
            'value' => $fields['copyfullname'], 'required' => 'required',
        ]);
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('mb-3');
        $html .= html_writer::label(get_string('copyshortname', 'local_coursetranslate'), 'id_copyshortname', false, ['class' => 'form-label']);
        $html .= html_writer::empty_tag('input', [
            'id' => 'id_copyshortname', 'class' => 'form-control', 'type' => 'text', 'name' => 'copyshortname',
            'value' => $fields['copyshortname'], 'required' => 'required',
        ]);
        $html .= html_writer::end_div();
    }
    $html .= html_writer::tag('button', get_string('continue'), ['type' => 'submit', 'class' => 'btn btn-primary me-2']);
    $html .= html_writer::link(new moodle_url('/local/coursetranslate/view.php', ['id' => $jobid]), get_string('cancel'), ['class' => 'btn btn-secondary']);
    $html .= html_writer::end_tag('form');
    return $html;
}
