# Security Review: tool_wizards

## Verdict: No exploitable vulnerabilities found

The plugin is well-secured. Every data flow from untrusted input to output was traced and the following protections are in place.

---

## Threat Matrix

| Threat | Mitigation | Where |
|--------|-----------|-------|
| **XSS** | Every `text::get()` call in `form_builder` is wrapped in `s()` before reaching HTML. `card_html()` escapes title and desc. `html_writer` escapes attribute values. | `form_builder.php` |
| **SQL injection** | All DB queries use Moodle's parameterised `$DB` API. No raw SQL with interpolation. | All files |
| **RCE / code injection** | No `eval()`, `call_user_func()`, or dynamic class instantiation from user input. Extensions are validated via `is_subclass_of()` against known base classes. | `extensions.php`, `collect_extensions.php` |
| **Broken access control** | Both `wizard_form` and `content_wizard_form` call `require_login()` + `require_capability()` in `check_access_for_dynamic_submission()`. `module_creator::is_available()` and `content_creator::may_use()` add further capability checks. | `wizard_form.php`, `content_wizard_form.php` |
| **CSRF** | `dynamic_form` (Moodle core) includes a `sesskey` token. | `wizard_form.php`, `content_wizard_form.php` |
| **Arbitrary field injection** | `validator::field_name_ok()` restricts field names to `/^[a-zA-Z_][a-zA-Z0-9_]*(\[[a-zA-Z0-9_]*\])*$/`. `choicesfrom` is a closed whitelist. Actions and transforms must be registered. | `validator.php` |
| **Sensitive config leakage** | `engine::config_default_allowed()` blocks names matching `/pass\|secret\|key\|token\|salt\|cookie/`, blocks `config.php`-forced values, and restricts to course/activity defaults. | `engine.php` |
| **File access** | `first_draft_file()` is scoped to the current user's context. `as_posted()` verifies the draft file belongs to the current user before putting it in `$_POST`. Picture `pix:` references can't traverse (no `.` in the regex). `file:` references use `make_pluginfile_url`. | `engine.php`, `module_creator.php`, `form_builder.php` |
| **Form validation bypass** | `form_submission::browser_values()` replicates browser `disabledIf`/`hideIf` locking, and `form_submission::submit()` runs the form's own `get_data()` validation. | `form_submission.php` |
| **Definition injection** | `repository::save()` calls `validator::check()` before persisting. The validator rejects unknown keys, invalid field names, unregistered actions/transforms, and malformed conditions. | `repository.php`, `validator.php` |

---

## Detailed Findings

### 1. XSS — Mitigated

All wizard definition text passes through `text::get()` which returns plain text (not escaped). Every call site in `form_builder.php` wraps the result in `s()`:

- `open_screen()`: `s($question)` for the legend
- `add_item()`: `s(text::get($item['label']))` for labels, `s(text::get($item['text']))` for hints, `s(text::get($item['help']))` for help text
- `add_cards()`: `card_html()` calls `s($title)` and `s($desc)`
- `build()`: `s(text::get($doc['intro']))` for the intro paragraph

The `placeholder` attribute in `add_item()` passes `text::get($item['placeholder'])` directly to `$attributes`, but `html_writer` escapes attribute values, so this is safe.

The `question_categories::tree()` method returns `format_string(..., 'escape' => false)` (formatted HTML). In `engine::choices_for()`, these names become choice titles, which flow into `add_cards()` → `card_html()` → `s($title)`. Safe.

### 2. SQL Injection — Mitigated

All database access uses Moodle's `$DB` API with parameterised queries:
- `$DB->get_records(self::TABLE, null, 'sortorder ASC, id ASC')`
- `$DB->get_record(self::TABLE, ['wizardkey' => $doc['key']])`
- `$DB->record_exists('files', ['contextid' => $usercontext, ...])`
- `$DB->get_field_sql('SELECT MIN(id) FROM {course} WHERE id <> ?', [SITEID])`

No string interpolation into SQL.

### 3. RCE / Code Injection — Mitigated

- `extensions::hook()` only registers classes that pass `is_subclass_of()` checks in `collect_extensions`.
- `module_creator::factory()` uses `require_once($CFG->dirroot . '/mod/' . $modname . '/mod_form.php')` where `$modname` is validated by `is_available()` against `/^[a-z][a-z0-9_]*$/` and `\core_component::get_component_directory('mod_' . $modname)`.
- `repository::event()` constructs a class name from a fixed set of event names (`wizard_created`, `wizard_updated`, `wizard_deleted`), not from user input.
- No `eval()`, `assert()`, `create_function()`, or `call_user_func()` on user-controlled data.

### 4. Broken Access Control — Mitigated

**`wizard_form::check_access_for_dynamic_submission()`:**
1. `require_login($course, false, null, false, true)` — must be logged in
2. `require_capability('moodle/course:manageactivities', ...)` — must manage activities in the course
3. Preview mode: `require_capability('tool/wizards:managewizards', system)` — must be a wizard manager
4. Non-preview: checks `get_config('tool_wizards', 'enabled')` and `$record->status === STATUS_ENABLED`
5. `module_creator::is_available($course, $record->target)` — module must be installed, visible, and the user must have `moodle/course:manageactivities` + `course_allowed_module()`

**`content_wizard_form::check_access_for_dynamic_submission()`:**
1. `require_login($cm->course, false, $cm, false, true)` — must be logged in
2. Preview: `require_capability('tool/wizards:managewizards', system)`
3. Non-preview: checks enabled + status
4. `content_creator::may_use($doc, $cm)` — checks `moodle/course:manageactivities` + handler-specific capabilities + `is_available()`

**`course_creator::create()`:**
- `require_capability('moodle/course:create', \core\context\coursecat::instance($answers->category))`

**`course_creator::get_start_category()`:**
- Uses `core_course_category::make_categories_list('moodle/course:create')` which only returns categories where the user has the capability.

### 5. CSRF — Mitigated

Both forms extend `core_form\dynamic_form`, which includes a `sesskey` hidden field and validates it on submission. The `definition()` methods add their own hidden fields (`courseid`, `wizard`, `section`, `beforemod`, `preview`, `cmid`) with appropriate `PARAM_*` types.

### 6. Arbitrary Field Injection — Mitigated

- `validator::field_name_ok()` enforces `/^[a-zA-Z_][a-zA-Z0-9_]*(\[[a-zA-Z0-9_]*\])*$/` on all field names in `sets` mappings.
- `engine::set_field()` parses field names with `preg_match_all('/[^\[\]]+|\[\]/', $name, $m)` — the validator ensures only safe names reach this.
- `choicesfrom` is a closed whitelist: `course:formats`, `course:ltitools`, `cm:questioncategories`, `cm:questioncategoryparents`.
- Actions and transforms must be registered via the `collect_extensions` hook and validated by `is_subclass_of()`.
- The validator rejects unknown keys at every level (`known_keys()`).

### 7. Sensitive Config Leakage — Mitigated

`engine::config_default_allowed()`:
- Blocks any name matching `/pass|secret|key|token|salt|cookie/`
- For `core` plugin: blocks values in `$CFG->config_php_settings` (forced in `config.php`)
- For `core` plugin: only allows `gradepointdefault` or settings prefixed with an installed module name
- For other plugins: blocks values in `$CFG->forced_plugin_settings`
- Only allows `moodlecourse` settings or settings from `mod_*` plugins (and their subplugins)

The validator (`validator::config_reference()`) calls this at definition-save time, so a malicious definition cannot be stored.

### 8. File Access — Mitigated

- `engine::first_draft_file()` queries `get_file_storage()->get_area_files()` with `\core\context\user::instance($USER->id)->id` — only the current user's draft files.
- `module_creator::as_posted()` verifies `$DB->record_exists('files', ['contextid' => $usercontext, 'component' => 'user', 'filearea' => 'draft', 'itemid' => $value])` before putting a draft itemid into `$_POST`.
- `form_builder::picture()` for `pix:` references uses `/^pix:([a-z0-9_\/]+)$/` (no dots, no `..`) and checks `is_readable()` on the resolved path.
- `form_builder::picture()` for `file:` references uses `/^file:([A-Za-z0-9._-]+)$/` and `make_pluginfile_url()` which enforces the file area's access rules.
- `validator::picture()` validates picture references at save time.

### 9. Form Validation Bypass — Mitigated

`form_submission::browser_values()`:
- Renders the form and reads all controls via DOM
- Applies `disabledIf()`/`hideIf()` rules via `getLockOptionObject()` and `locks()`
- Locked controls are excluded from the submitted values
- The result is then passed to `form_submission::submit()` which calls `$form->get_data()` — the form's own validation runs

This means a wizard cannot submit values for fields that the module's form would disable, and the module's own `validation()` still runs on the final data.

### 10. Definition Injection — Mitigated

`repository::save()`:
1. Calls `validator::check($doc)` — rejects any structural problems
2. Checks for key collisions
3. Only then persists

The validator is strict:
- Unknown keys are errors at every level
- Field names must match `field_name_ok()`
- Actions/transforms must be registered
- Conditions must reference existing questions
- `choicesfrom` must be in the whitelist
- Picture references must be valid
- Config references must pass `config_default_allowed()`

---

## Minor Observations (Not Vulnerabilities)

1. **`repository::get_by_key()`** does a linear scan over all wizards. Not a security issue, but a performance concern if the number of wizards grows large.

2. **`form_submission::submit()`** temporarily replaces `$_POST` and `$_FILES`. The `try/finally` block ensures restoration, but if an exception is thrown between the save and the `try`, the originals would be lost. In practice, `$savedpost = $_POST` and `$savedfiles = $_FILES` are the last statements before the `try`, so this is safe.

3. **`module_creator::factory()`** temporarily sets `$_GET['typeid']` for LTI tools. The `try/finally` restores it. Safe.

4. **`engine::describe()`** outputs field values as plain text for the "Try it" preview. These are rendered by the caller (the form's response handler) which should escape them. The values come from the user's own answers, so this is not a cross-user XSS risk.

5. **`builder::key_for()`** generates question keys from form field names. The keys are validated by `validator::field_name_ok()` at save time, so a generated key cannot inject arbitrary field names.

---

## Files Reviewed

- `classes/form/wizard_form.php`
- `classes/form/content_wizard_form.php`
- `classes/local/wizard/engine.php`
- `classes/local/wizard/form_builder.php`
- `classes/local/wizard/repository.php`
- `classes/local/wizard/validator.php`
- `classes/local/wizard/extensions.php`
- `classes/local/wizard/text.php`
- `classes/local/wizard/builder.php`
- `classes/local/form_submission.php`
- `classes/local/module_creator.php`
- `classes/local/course_creator.php`
- `classes/local/content_creator.php`
- `classes/local/question_categories.php`
- `classes/hook/collect_extensions.php`
