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
 * English language strings.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['applied'] = '{$a} translated fields applied.';
$string['appliedstatus'] = 'Applied';
$string['applyselected'] = 'Apply selected to original course';
$string['applyskipwarning'] = '{$a} selected fields were skipped because they changed, disappeared, are not safely writable, or belong to versioned question-bank content.';
$string['backtojob'] = 'Back to translation job';
$string['bridgeerror'] = 'AI Bridge could not complete the translation: {$a}';
$string['confirmapply'] = 'Confirm applying translations';
$string['confirmapplytext'] = 'The selected translated fields will be written to the original course. Fields changed since translation are rejected.';
$string['confirmcopy'] = 'Confirm translated copy';
$string['confirmcopytext'] = 'A hidden copy of the course will be created, then the selected translations will be applied to the copy. The original course is not modified.';
$string['copyapplywarning'] = 'The copy was created, but {$a} translated fields could not be mapped or applied.';
$string['copycreated'] = 'Translated course copy created.';
$string['copyfullname'] = 'Copy full name';
$string['copyhidden'] = 'The translated copy is created hidden so it can be reviewed before publication.';
$string['copyshortname'] = 'Copy short name';
$string['coursetranslate:translate'] = 'Translate course content';
$string['createcopy'] = 'Create translated course copy';
$string['current'] = 'Current';
$string['emptycourse'] = 'No supported translatable fields were found with the selected options.';
$string['error:bridgeunavailable'] = 'The required local_ai_bridge API is unavailable.';
$string['error:invalidterminology'] = 'Invalid terminology line: {$a}. Use Source = Target.';
$string['error:samelanguage'] = 'Source and target languages must be different.';
$string['failed'] = 'Failed';
$string['field'] = 'Field';
$string['includeglossary'] = 'Include teacher-managed glossary entries';
$string['invalidresponse'] = 'The AI provider returned an invalid JSON response.';
$string['items'] = 'Fields';
$string['job'] = 'Translation job';
$string['jobcreated'] = 'Translation job created with {$a} fields.';
$string["mcp_banner_badge"] = "AI INTEGRATION";
$string["mcp_banner_benefit_control"] = "Confirmation required for write actions";
$string["mcp_banner_benefit_discovery"] = "Automatic tool discovery";
$string["mcp_banner_benefit_security"] = "Moodle permissions enforced";
$string["mcp_banner_cta"] = "Explore Moodle MCP";
$string["mcp_banner_description"] = "Install local_mcp to expose Course Translate features to ChatGPT through native Moodle permissions.";
$string["mcp_banner_dismiss"] = "Do not show this again";
$string["mcp_banner_title"] = "Bring Course Translate into ChatGPT";
$string["mcpapplyoriginal"] = "Apply translated fields to original course";
$string["mcpapplyoriginaldesc"] = "Destructive operation: after explicit MCP confirmation, apply selected fresh translations to the original Moodle course using the native field allowlist; original quiz question-bank records are skipped.";
$string["mcpbatchlarge"] = "Select no more than eight items per AI translation call.";
$string["mcpcopyexists"] = "This translation job already has a translated course copy.";
$string["mcpcreatecopy"] = "Create hidden translated course copy";
$string["mcpcreatecopydesc"] = "After explicit confirmation and native backup/course-create permission checks, duplicate the source Moodle course as a hidden course and apply selected translated fields using stable locators.";
$string["mcpcreatejob"] = "Create course translation job";
$string["mcpcreatejobdesc"] = "Snapshot teacher-authored course fields for translation without calling AI or modifying the course. Requires confirmed MCP WRITE access.";
$string["mcpgetfield"] = "Read original and translated field text";
$string["mcpgetfielddesc"] = "Read a bounded portion of an authorized translation job field, including exact stored source/translated text.";
$string["mcpgetjob"] = "Get course translation job status";
$string["mcpgetjobdesc"] = "Inspect a translation job, live stale-field status, paginated field metadata and state hash for confirmed writes, without changing Moodle data.";
$string["mcpinvalidcopyname"] = "Enter valid full and short names for the translated course copy.";
$string["mcpinvaliditems"] = "Select distinct valid field IDs belonging to this translation job.";
$string["mcpinvalidlanguage"] = "Enter distinct valid source and target language codes.";
$string["mcpinvalidterms"] = "Terminology is too long.";
$string["mcplistjobs"] = "List course translation jobs";
$string["mcplistjobsdesc"] = "List translation jobs in an authorized Moodle course, with pagination; no AI or course writes.";
$string["mcpnofreshfields"] = "None of the selected fields has a fresh translation eligible for this operation.";
$string["mcpstalejob"] = "Translation job or original course fields changed. Read the job again to obtain a fresh state hash.";
$string["mcptranslateitems"] = "Translate selected job fields";
$string["mcptranslateitemsdesc"] = "Translate at most eight explicitly selected fields with local_ai_bridge after MCP confirmation. May consume AI credits and update translation snapshots, but never modifies course content.";
$string['missing'] = 'No longer resolvable';
$string['missingitem'] = 'This field can no longer be resolved in the current course structure.';
$string['missingtranslation'] = 'Translation missing from AI response.';
$string['newjob'] = 'New translation job';
$string['nonelected'] = 'Select at least one item.';
$string['onlycoursecontent'] = 'Only course-authored content is sent for translation. Student submissions, forum posts, quiz attempts, grades and other private learner data are never collected.';
$string['outdated'] = 'Changed after translation';
$string['pending'] = 'Pending';
$string['pluginname'] = 'Course translator';
$string['privacy:metadata:job'] = 'Stores who created a course translation job and its language/configuration metadata.';
$string['privacy:metadata:job:courseid'] = 'The course being translated.';
$string['privacy:metadata:job:sourcelang'] = 'Source language code.';
$string['privacy:metadata:job:targetlang'] = 'Target language code.';
$string['privacy:metadata:job:timecreated'] = 'When the translation job was created.';
$string['privacy:metadata:job:userid'] = 'The user who created the translation job.';
$string['privacy:path'] = 'Course translation jobs';
$string['protectionerror'] = 'Protected tokens or HTML structure changed during translation: {$a}';
$string['refreshhint'] = 'If a source field has changed, translating it again refreshes its snapshot before sending it to AI.';
$string['select'] = 'Select';
$string['source'] = 'Original';
$string['sourcelang'] = 'Source language';
$string['staleitem'] = 'This field changed after the translation snapshot and was not applied.';
$string['starttranslation'] = 'Create translation job';
$string['status'] = 'Status';
$string['summary:applied'] = 'Applied: {$a}';
$string['summary:failed'] = 'Failed: {$a}';
$string['summary:outdated'] = 'Outdated: {$a}';
$string['summary:pending'] = 'Pending: {$a}';
$string['summary:translated'] = 'Translated: {$a}';
$string['targetlang'] = 'Target language';
$string['terminology'] = 'Required terminology';
$string['terminology_help'] = 'One mapping per line in the form Source = Target. These terms are protected as tokens so the required target term is restored exactly.';
$string['translated'] = 'Translated';
$string['translatedcount'] = '{$a} fields translated.';
$string['translatefullname'] = 'Include course full name';
$string['translatepending'] = 'Translate pending fields';
$string['translateselected'] = 'Translate selected';
$string['translatesummary'] = 'Include course summary';
$string['translation'] = 'Translation';
$string['translationpartial'] = 'The provider returned only part of the requested JSON mapping. Available translations were saved and missing items remain pending or failed.';
$string['unsupportedfield'] = 'Refusing to write an unsupported table/field combination.';
