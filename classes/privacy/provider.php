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

namespace local_coursetranslate\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_user_data_provider;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * Course text and translated text are course content, not learner personal
 * data. The plugin stores the user id of the teacher/manager who created each
 * job; privacy deletion anonymises that attribution while preserving the
 * course translation work.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    core_user_data_provider,
    core_userlist_provider {

    /**
     * Describe stored user-related metadata.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_coursetranslate_job', [
            'userid' => 'privacy:metadata:job:userid',
            'courseid' => 'privacy:metadata:job:courseid',
            'sourcelang' => 'privacy:metadata:job:sourcelang',
            'targetlang' => 'privacy:metadata:job:targetlang',
            'timecreated' => 'privacy:metadata:job:timecreated',
        ], 'privacy:metadata:job');
        return $collection;
    }

    /**
     * Contexts containing jobs created by a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_coursetranslate_job} j
                    ON j.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel
                   AND j.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export user attribution metadata only.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $jobs = $DB->get_records('local_coursetranslate_job', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ], 'timecreated ASC');
            $export = [];
            foreach ($jobs as $job) {
                $export[] = (object)[
                    'source_language' => $job->sourcelang,
                    'target_language' => $job->targetlang,
                    'status' => $job->status,
                    'created' => transform::datetime($job->timecreated),
                    'modified' => transform::datetime($job->timemodified),
                ];
            }
            if ($export) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path', 'local_coursetranslate')],
                    (object)['jobs' => $export]
                );
            }
        }
    }

    /**
     * Remove all personal attribution in a course while preserving translation content.
     *
     * @param context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_course) {
            return;
        }
        $DB->set_field('local_coursetranslate_job', 'userid', 0, ['courseid' => $context->instanceid]);
    }

    /**
     * Anonymise one user's job attribution in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                $DB->set_field('local_coursetranslate_job', 'userid', 0, [
                    'courseid' => $context->instanceid,
                    'userid' => $userid,
                ]);
            }
        }
    }

    /**
     * Add users with translation-job attribution in a course context.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $sql = "SELECT j.userid
                  FROM {local_coursetranslate_job} j
                 WHERE j.courseid = :courseid
                   AND j.userid <> 0";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Anonymise approved users' attribution in one course context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $params['courseid'] = $context->instanceid;
        $DB->set_field_select(
            'local_coursetranslate_job',
            'userid',
            0,
            "courseid = :courseid AND userid {$insql}",
            $params
        );
    }
}
