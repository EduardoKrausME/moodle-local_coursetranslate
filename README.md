# local_coursetranslate

`local_coursetranslate` translates the textual structure of a Moodle course while treating Moodle markup and identifiers
as data that must not be changed. It is designed for Moodle and delegates every AI request to `local_ai_bridge`.

## Content translated

- optional course full name and summary;
- section names and summaries;
- Page name, intro and content;
- Book name, intro, chapter titles and chapter content;
- Label/Text name and content;
- Assignment name and description;
- Forum name and description, but **never posts**;
- Quiz name, description and quiz section headings;
- fixed quiz questions of types multichoice, truefalse, shortanswer, numerical, essay and matching;
- common question text, general feedback, answer feedback, hints, multichoice combined feedback, essay grader
  information/response template and matching subquestions;
- textual multichoice/short-answer answers where appropriate; numerical/boolean answer values are not translated;
- Glossary entries optionally, but only entries authored by users who have `mod/glossary:manageentries` in that
  glossary.

Random quiz question-set references are intentionally not translated because a random slot does not resolve to one
stable question definition.

## Workflow

1. Open **Course translator** from course navigation.
2. Choose source and target languages.
3. Choose whether course full name/summary and teacher-managed glossary entries are included.
4. Optionally provide required terminology using `Source = Target`, one mapping per line.
5. Create the job. This only snapshots selected course fields; it does not call AI yet.
6. Translate pending fields or select specific fields and translate them.
7. Review original, translation and status side by side.
8. Either create a translated copy or explicitly confirm selected updates to the original course.

The original course is never overwritten by default. A translated duplicate is created **hidden** so it can be reviewed
before publication.

## Stable mapping across course copies

Moodle backup/restore changes database IDs, so the plugin does not use original IDs as the mapping key for a copied
course. Each field receives a stable locator based on course structure:

- section number;
- section + position + module type, with course-module `idnumber` added as an extra anchor when available;
- Book chapter order;
- Quiz slot number;
- nested answer/subquestion order.

After Moodle duplicates the course using `core_course_external::duplicate_course()`, the copy is scanned again and
translations are applied only where the stable locator resolves. Missing mappings are skipped and reported rather than
guessed.

## Token protection

Before sending a field to AI, the plugin replaces non-translatable structures with immutable tokens. Protected material
includes:

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

The model receives small identified blocks and must return JSON mapping local item ID to translated string. On return,
the plugin verifies that every token exists exactly once, rejects unknown tokens, requires the HTML tag-token order to
be unchanged, rejects newly introduced raw markup/URLs, restores the protected bytes and compares the final HTML tag
signature to the source.

Required terminology is stronger than a prompt hint: occurrences are tokenised with the source term but restored using
the required target term. For example, `Learner = Aluno` deterministically restores `Aluno`.

## Change detection

Each item stores a SHA-256 hash of the exact source field. The course is rescanned before preview status, retranslation
and application. A field is shown as changed/outdated when its current hash no longer matches the translation snapshot.

This rescan matters especially for the question bank, where editing a question can create a new question revision with a
different database ID. The plugin resolves the current quiz slot again instead of trusting the old question ID.

Selecting an outdated field for translation refreshes its source snapshot first. Applying an outdated translation to the
original course is refused.

### Question-bank safety

Question content is fully available for preview and for the translated course copy. Directly applying translated
question fields back into the original course is intentionally skipped, because Moodle question edits are versioned
and a raw table update could mutate a definition already referenced by attempts. The translated-copy workflow is safe
here because Moodle first duplicates the course/question bank into an isolated hidden course and the plugin then updates
those restored records. Non-question course fields can still be selectively applied to the original after explicit
confirmation.

## Security

Capability:

```text
local/coursetranslate:translate
```

The plugin operates in course context and requires the capability before job creation, viewing, translating, copying or
applying content. Course copy additionally relies on Moodle's standard course-create/backup/restore checks.

Writes go through an explicit table/field allowlist. Stored table/field names are not accepted as arbitrary update
targets.

All state-changing actions require a Moodle session key. Updating the original course and creating a copy both have an
explicit confirmation step.

## Privacy

The collector deliberately does **not** read or send:

- assignment submissions;
- forum posts;
- quiz attempts/responses;
- grades;
- private messages;
- comments authored by learners;
- other learner-generated private content.

The translation job stores the creator user ID for attribution. The Privacy API exports that metadata and anonymises the
user ID on deletion while preserving the course translation work. AI Bridge has its own privacy/accounting behavior.

## Tables

- `local_coursetranslate_job`: course, creator, language pair, terminology, options and copy reference.
- `local_coursetranslate_item`: stable key, source snapshot/hash, translated text/hash, state, locator and metadata.


## Optional MCP integration (ChatGPT)

When [local_mcp](https://github.com/EduardoKrausME/moodle-local_mcp) is enabled, it discovers
`local_coursetranslate\mcp\provider` from `classes/mcp/provider.php` automatically.
This is an optional integration without a hard `version.php` dependency,
an additional MCP server, or a legacy `lib.php` callback.

The provider exposes seven tools:

| Tool | Scope | Behavior |
| --- | --- | --- |
| `coursetranslate_list_jobs` | READ | List course jobs and their status, with pagination. |
| `coursetranslate_get_job` | READ | Inspect a job, all live field statuses, a current state hash, and paginated field metadata. |
| `coursetranslate_get_field` | READ | Inspect the original and translated text of one item in character-bounded chunks. |
| `coursetranslate_create_job` | WRITE | Create a new snapshot with a source/target language pair, optional course fields, glossary entries, and required terminology. |
| `coursetranslate_translate_items` | WRITE | Translate up to eight explicitly selected item IDs in one confirmed call using `local_ai_bridge`. |
| `coursetranslate_apply_original` | WRITE (destructive) | Apply explicitly selected, current translations to the original course using the native allowlist and skip versioned question-bank records. |
| `coursetranslate_create_copy` | WRITE | Create a hidden translated copy through Moodle backup/restore, preserving the original. |

The Moodle capability `local/coursetranslate:translate` is required in the corresponding
course context for **all** tools, alongside the MCP READ or WRITE scope.
Course-copy actions additionally require Moodle course creation and backup capabilities.
Authorized teachers can view existing jobs in the course, as with the native Moodle job list;
the tools never provide student submissions, grades, forum posts or quiz attempts.

All WRITE operations use `local_mcp`'s one-time confirmation flow. Previews never
change the database, contact an AI provider or create a backup. The creation preview
shows the proposed source/target languages and field count; the translation preview
identifies selected fields and potential AI-credit usage; application and duplication
previews explicitly identify the target course and affected items.

Before calling any job-based WRITE tool, call `coursetranslate_get_job` and pass its
`state_hash` as `expected_state_hash`. The server rejects stale hashes both during
preview and execution, including edits to source course fields or saved translations.
All selected `itemids` must belong to the job; unexpected or duplicate IDs are refused.
Review item contents with `coursetranslate_get_field` before applying them, because
the result of machine translation still requires a human quality check.

Original-course application retains the existing safety restrictions and skips changed
or question-bank fields rather than bypassing Moodle question versioning. Translated
course copies are always created hidden for review before publication, and the MCP copy
tool refuses to create a second copy from a job already linked to a translated copy.

Responses are bounded to 40 job items per page, 12,000 characters per field read and
eight AI-translated items per confirmed request. No database migration is required to
enable this integration. After updating the Moodle plugin, refresh the ChatGPT MCP
connector to discover the new tools.
