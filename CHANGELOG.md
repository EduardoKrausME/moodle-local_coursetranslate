# Changelog

This changelog records **only the two latest published GitHub releases** of `local_coursetranslate`. Each entry is based on the Git diff between its tag and the immediately preceding **published** release tag. Commits made after release `1.1.4` are excluded.

## [1.1.4] - 2026-10-19

Published: 2026-10-19 14:56:01 UTC.

Comparison: [1.1.3...1.1.4](https://github.com/EduardoKrausME/moodle-local_coursetranslate/compare/1.1.3...1.1.4)

### Added: optional Moodle MCP integration

- Added optional discovery through `local_coursetranslate\mcp\provider` for the `local_mcp` plugin, **without making `local_mcp` a required dependency** in `version.php`.
- Added three read tools:
  - `coursetranslate_list_jobs`: list translation jobs for a course, with bounded pagination.
  - `coursetranslate_get_job`: inspect a job's state, field statuses, metadata and current `state_hash`.
  - `coursetranslate_get_field`: read a selected field's original and translated content in bounded chunks.
- Added four write tools, using the `local_mcp` preview/confirmation flow:
  - `coursetranslate_create_job`: create a course translation snapshot with language and content options.
  - `coursetranslate_translate_items`: translate up to eight selected fields using the existing AI Bridge integration.
  - `coursetranslate_apply_original`: apply selected fresh translations to the original course using the existing safety restrictions; the operation is marked destructive.
  - `coursetranslate_create_copy`: create a hidden translated course copy through Moodle backup/restore rather than direct database cloning.
- Added course-context permission checks for `local/coursetranslate:translate`; course copies additionally require the relevant Moodle course-creation and backup permissions.
- Added validation of explicit selected item IDs and `expected_state_hash` to reject stale job data for job-based writes. The preview phase reports intended effects without persisting writes or contacting AI providers.
- Added bounded response and request sizes (including 40 jobs/items per page, 12,000 characters per field read and eight translated fields per confirmed translation request).
- Added `tests/mcp_tools_test.php` with regression tests covering state hashes, permission isolation, previews and MCP operations. These tests can skip MCP-dependent execution when the optional plugin is unavailable.

### Added: administrative notice

- Added an informational, dismissible MCP integration banner to the course translation page, rendered only for site administrators when `local_mcp` is not installed and the user has not dismissed it.
- Persisted dismissal as a per-user preference, protected by a POST request and Moodle sesskey. Added a Mustache template, responsive/dark-mode CSS, and English/Brazilian Portuguese language strings for the notice and integration features.

### Documentation, release automation and metadata

- Expanded `README.md` with the seven MCP tools, authorization and confirmation requirements, source-state checks, item limits and safe course-copy behavior.
- Added `concurrency` configuration to `.github/workflows/release.yml` with a release-scoped group, `cancel-in-progress: false` and `queue: max`.
- Updated `version.php` from plugin version `2026101500` / release `1.1.3` to version `2026101900` / release `1.1.4`.

### Scope

The tag comparison contains eight commits and changes the release workflow, README, new MCP integration classes, banner/template/styles, language files, the new MCP test file and version metadata. No separate MCP server or database migration is introduced by this comparison.

## [1.1.3] - 2026-10-15

Published: 2026-10-15 20:31:07 UTC.

Comparison: [1.1.2...1.1.3](https://github.com/EduardoKrausME/moodle-local_coursetranslate/compare/1.1.2...1.1.3)

### Changed: continuous integration

- Updated the GitHub Actions CI PostgreSQL image from `postgres:16` to **`postgres:17`**.
- Updated the MariaDB CI image from `mariadb:10` to **`mariadb:11.4`**.
- Replaced the previous MariaDB empty-password environment configuration with `MARIADB_ALLOW_EMPTY_ROOT_PASSWORD` and changed its health check from `mysqladmin ping` to `healthcheck.sh --connect --innodb_initialized`, including adjusted retries.
- Updated `version.php` from plugin version `2026101200` / release `1.1.2` to version `2026101500` / release `1.1.3`.

### Scope

The tag comparison contains four commits and changes **only** `.github/workflows/ci.yml` and `version.php`. No runtime application source files or user-facing behaviors changed between these tags. Updating CI database images is not a change to the plugin's minimum supported database or Moodle version.

---

All entries describe verified differences between published tags; they do not claim that tests were executed here or include commits after the latest published release.
