<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library hooks.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a course navigation entry for users allowed to translate the course.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context_course $context Course context.
 * @return void
 */
function local_coursetranslate_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!has_capability('local/coursetranslate:translate', $context)) {
        return;
    }

    $url = new moodle_url('/local/coursetranslate/index.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('pluginname', 'local_coursetranslate'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_coursetranslate'
    );
}
