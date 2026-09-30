<?php
// This file is part of Moodle - http://moodle.org/

/**
 * English language strings.
 *
 * @package   local_coursetranslate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Course translator';
$string['coursetranslate:translate'] = 'Translate course content';
$string['starttranslation'] = 'Create translation job';
$string['sourcelang'] = 'Source language';
$string['targetlang'] = 'Target language';
$string['translatefullname'] = 'Include course full name';
$string['translatesummary'] = 'Include course summary';
$string['includeglossary'] = 'Include teacher-managed glossary entries';
$string['terminology'] = 'Required terminology';
$string['terminology_help'] = 'One mapping per line in the form Source = Target. These terms are protected as tokens so the required target term is restored exactly.';
$string['jobcreated'] = 'Translation job created with {$a} fields.';
$string['job'] = 'Translation job';
$string['source'] = 'Original';
$string['translation'] = 'Translation';
$string['status'] = 'Status';
$string['field'] = 'Field';
$string['select'] = 'Select';
$string['translatepending'] = 'Translate pending fields';
$string['translateselected'] = 'Translate selected';
$string['applyselected'] = 'Apply selected to original course';
$string['createcopy'] = 'Create translated course copy';
$string['confirmapply'] = 'Confirm applying translations';
$string['confirmapplytext'] = 'The selected translated fields will be written to the original course. Fields changed since translation are rejected.';
$string['confirmcopy'] = 'Confirm translated copy';
$string['confirmcopytext'] = 'A hidden copy of the course will be created, then the selected translations will be applied to the copy. The original course is not modified.';
$string['copyfullname'] = 'Copy full name';
$string['copyshortname'] = 'Copy short name';
$string['copycreated'] = 'Translated course copy created.';
$string['applied'] = '{$a} translated fields applied.';
$string['translatedcount'] = '{$a} fields translated.';
$string['translationpartial'] = 'The provider returned only part of the requested JSON mapping. Available translations were saved and missing items remain pending or failed.';
$string['nonelected'] = 'Select at least one item.';
$string['pending'] = 'Pending';
$string['translated'] = 'Translated';
$string['failed'] = 'Failed';
$string['outdated'] = 'Changed after translation';
$string['appliedstatus'] = 'Applied';
$string['current'] = 'Current';
$string['missing'] = 'No longer resolvable';
$string['invalidresponse'] = 'The AI provider returned an invalid JSON response.';
$string['missingtranslation'] = 'Translation missing from AI response.';
$string['bridgeerror'] = 'AI Bridge could not complete the translation: {$a}';
$string['protectionerror'] = 'Protected tokens or HTML structure changed during translation: {$a}';
$string['staleitem'] = 'This field changed after the translation snapshot and was not applied.';
$string['missingitem'] = 'This field can no longer be resolved in the current course structure.';
$string['unsupportedfield'] = 'Refusing to write an unsupported table/field combination.';
$string['copyapplywarning'] = 'The copy was created, but {$a} translated fields could not be mapped or applied.';
$string['applyskipwarning'] = '{$a} selected fields were skipped because they changed, disappeared, are not safely writable, or belong to versioned question-bank content.';
$string['copyhidden'] = 'The translated copy is created hidden so it can be reviewed before publication.';
$string['backtojob'] = 'Back to translation job';
$string['newjob'] = 'New translation job';
$string['items'] = 'Fields';
$string['privacy:metadata:job'] = 'Stores who created a course translation job and its language/configuration metadata.';
$string['privacy:metadata:job:userid'] = 'The user who created the translation job.';
$string['privacy:metadata:job:courseid'] = 'The course being translated.';
$string['privacy:metadata:job:sourcelang'] = 'Source language code.';
$string['privacy:metadata:job:targetlang'] = 'Target language code.';
$string['privacy:metadata:job:timecreated'] = 'When the translation job was created.';
$string['privacy:path'] = 'Course translation jobs';
$string['summary:pending'] = 'Pending: {$a}';
$string['summary:translated'] = 'Translated: {$a}';
$string['summary:failed'] = 'Failed: {$a}';
$string['summary:outdated'] = 'Outdated: {$a}';
$string['summary:applied'] = 'Applied: {$a}';
$string['refreshhint'] = 'If a source field has changed, translating it again refreshes its snapshot before sending it to AI.';
$string['onlycoursecontent'] = 'Only course-authored content is sent for translation. Student submissions, forum posts, quiz attempts, grades and other private learner data are never collected.';
$string['emptycourse'] = 'No supported translatable fields were found with the selected options.';
$string['error:samelanguage'] = 'Source and target languages must be different.';
$string['error:invalidterminology'] = 'Invalid terminology line: {$a}. Use Source = Target.';
$string['error:bridgeunavailable'] = 'The required local_ai_bridge API is unavailable.';
