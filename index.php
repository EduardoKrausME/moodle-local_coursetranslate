<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/coursetranslate:translate', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/coursetranslate/index.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('pluginname', 'local_coursetranslate'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \local_coursetranslate\form\start_form();
$form->set_data(['courseid' => $courseid]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} else if ($data = $form->get_data()) {
    $jobid = \local_coursetranslate\local\job_service::create($courseid, $USER->id, (array)$data);
    $count = $DB->count_records('local_coursetranslate_item', ['jobid' => $jobid]);
    redirect(
        new moodle_url('/local/coursetranslate/view.php', ['id' => $jobid]),
        get_string('jobcreated', 'local_coursetranslate', $count),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_coursetranslate'));
echo $OUTPUT->notification(get_string('onlycoursecontent', 'local_coursetranslate'), 'info');
$form->display();

$jobs = $DB->get_records('local_coursetranslate_job', ['courseid' => $courseid], 'timecreated DESC', '*', 0, 20);
if ($jobs) {
    echo $OUTPUT->heading(get_string('job', 'local_coursetranslate'), 3);
    $table = new html_table();
    $table->head = [get_string('targetlang', 'local_coursetranslate'), get_string('status', 'local_coursetranslate'), get_string('timecreated')];
    foreach ($jobs as $job) {
        $url = new moodle_url('/local/coursetranslate/view.php', ['id' => $job->id]);
        $table->data[] = [
            html_writer::link($url, s($job->sourcelang . ' → ' . $job->targetlang)),
            s($job->status),
            userdate($job->timecreated),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
