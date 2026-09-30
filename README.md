# local_coursetranslate

`local_coursetranslate` translates the textual structure of a Moodle course while treating Moodle markup and identifiers as data that must not be changed. It is designed for Moodle 4.5+ and delegates every AI request to `local_ai_bridge`.

## Required dependency

Install and configure:

- https://github.com/EduardoKrausME/moodle-local_ai_bridge/
- minimum version `2026093001`
- purpose idnumber: `coursetranslate-translate`

The plugin never stores provider API keys and contains no OpenAI, Gemini, Claude, Ollama or other provider-specific client. The only AI call is:

```php
\local_ai_bridge\api::generate('coursetranslate-translate', $messages);
```

The current user must also be allowed to use AI Bridge and the tenant must expose a route for the `coursetranslate-translate` purpose.

## Supported content in the first version

- optional course full name and summary;
- section names and summaries;
- Page name, intro and content;
- Book name, intro, chapter titles and chapter content;
- Label/Text name and content;
- Assignment name and description;
- Forum name and description, but **never posts**;
- Quiz name, description and quiz section headings;
- fixed quiz questions of types multichoice, truefalse, shortanswer, numerical, essay and matching;
- common question text, general feedback, answer feedback, hints, multichoice combined feedback, essay grader information/response template and matching subquestions;
- textual multichoice/short-answer answers where appropriate; numerical/boolean answer values are not translated;
- Glossary entries optionally, but only entries authored by users who have `mod/glossary:manageentries` in that glossary.

Random quiz question-set references are intentionally not translated because a random slot does not resolve to one stable question definition.

## Workflow

1. Open **Course translator** from course navigation.
2. Choose source and target languages.
3. Choose whether course full name/summary and teacher-managed glossary entries are included.
4. Optionally provide required terminology using `Source = Target`, one mapping per line.
5. Create the job. This only snapshots supported course fields; it does not call AI yet.
6. Translate pending fields or select specific fields and translate them.
7. Review original, translation and status side by side.
8. Either create a translated copy or explicitly confirm selected updates to the original course.

The original course is never overwritten by default. A translated duplicate is created **hidden** so it can be reviewed before publication.

## Stable mapping across course copies

Moodle backup/restore changes database IDs, so the plugin does not use original IDs as the mapping key for a copied course. Each field receives a stable locator based on course structure:

- section number;
- section + position + module type, with course-module `idnumber` added as an extra anchor when available;
- Book chapter order;
- Quiz slot number;
- nested answer/subquestion order.

After Moodle duplicates the course using `core_course_external::duplicate_course()`, the copy is scanned again and translations are applied only where the stable locator resolves. Missing mappings are skipped and reported rather than guessed.

## Token protection

Before sending a field to AI, the plugin replaces non-translatable structures with immutable tokens. Protected material includes:

- every HTML tag including its attributes;
- `http://` and `https://` URLs outside tags;
- `@@PLUGINFILE@@` paths;
- Moodle `[[...]]` constructs and `{mlang ...}` markers;
- Mustache-like tokens;
- `$a`, `$a->property`, printf placeholders and common `{placeholder}` forms;
- `<pre>`, `<code>`, Markdown code blocks and inline code;
- common LaTeX/math blocks;
- common filenames;
- required terminology.

The model receives small identified blocks and must return JSON mapping local item ID to translated string. On return, the plugin verifies that every token exists exactly once, rejects unknown tokens, requires the HTML tag-token order to be unchanged, rejects newly introduced raw markup/URLs, restores the protected bytes and compares the final HTML tag signature to the source.

Required terminology is stronger than a prompt hint: occurrences are tokenised with the source term but restored using the required target term. For example, `Learner = Aluno` deterministically restores `Aluno`.

## Change detection

Each item stores a SHA-256 hash of the exact source field. The course is rescanned before preview status, retranslation and application. A field is shown as changed/outdated when its current hash no longer matches the translation snapshot.

This rescan matters especially for the question bank, where editing a question can create a new question version with a different database ID. The plugin resolves the current quiz slot again instead of trusting the old question ID.

Selecting an outdated field for translation refreshes its source snapshot first. Applying an outdated translation to the original course is refused.

### Question-bank safety

Question content is fully available for preview and for the translated course copy. Directly applying translated question fields back into the original course is intentionally skipped, because Moodle 4.5 question edits are versioned and a raw table update could mutate a definition already referenced by attempts. The translated-copy workflow is safe here because Moodle first duplicates the course/question bank into an isolated hidden course and the plugin then updates those restored records. Non-question course fields can still be selectively applied to the original after explicit confirmation.

## Security

Capability:

```text
local/coursetranslate:translate
```

The plugin operates in course context and requires the capability before job creation, viewing, translating, copying or applying content. Course copy additionally relies on Moodle's standard course-create/backup/restore checks.

Writes go through an explicit table/field allowlist. Stored table/field names are not accepted as arbitrary update targets.

All state-changing actions require a Moodle session key. Updating the original course and creating a copy both have an explicit confirmation step.

## Privacy

The collector deliberately does **not** read or send:

- assignment submissions;
- forum posts;
- quiz attempts/responses;
- grades;
- private messages;
- comments authored by learners;
- other learner-generated private content.

The translation job stores the creator user ID for attribution. The Privacy API exports that metadata and anonymises the user ID on deletion while preserving the course translation work. AI Bridge has its own privacy/accounting behavior.

## Tables

- `local_coursetranslate_job`: course, creator, language pair, terminology, options and copy reference.
- `local_coursetranslate_item`: stable key, source snapshot/hash, translated text/hash, state, locator and metadata.

## Tests

PHPUnit tests cover the high-risk pure logic:

- HTML/token round-trip;
- pluginfile paths;
- URLs;
- placeholders and Mustache-like tokens;
- mandatory terminology;
- question-like code/formula/filename content;
- UTF-8;
- missing token rejection;
- new HTML injection rejection;
- valid, fenced, invalid and partial AI JSON responses;
- terminology parsing.

The GitHub Actions workflow installs the plugin together with `local_ai_bridge`, tests Moodle 4.5 and Moodle 5.2 against PostgreSQL and MariaDB, runs PHP lint, `moodle-plugin-ci validate`, PHPUnit and `EduardoKrausME/moodle-plugin-validate`.

## Validation

Useful local checks:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
xmllint --noout db/install.xml
```

CI installs the real `local_ai_bridge` dependency as an extra plugin and then runs the Moodle test environment plus the static validator:

```yaml
uses: EduardoKrausME/moodle-plugin-validate@main
with:
  plugin: ./plugin
```

## License

GNU GPL v3 or later.
