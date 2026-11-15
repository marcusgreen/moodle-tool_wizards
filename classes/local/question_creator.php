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

namespace tool_wizards\local;

use core_question\local\bank\question_edit_contexts;
use tool_wizards\local\wizard\extensions;
use tool_wizards\local\wizard\qtype;

/**
 * Creates questions the way the question bank's "Create a new question" does, in a quiz's own
 * question bank (then adding them to the quiz) or in a shared question bank.
 *
 * The question type's own editing form is built as question/bank/editquestion/question.php
 * builds it for a new question, and submitted as a teacher would who filled in only the
 * wizard's answers ({@see form_submission}). The question is saved with the type's
 * save_question(), and a quiz question is then added to the quiz as mod/quiz/edit.php adds a
 * newly created question.
 *
 * The in-activity question wizards describe a question with a few simple fields
 * ({@see self::fields()}); {@see self::form_fields()} turns them into the type's own form fields
 * and {@see self::check_fields()} checks them first. The fields every question has are handled
 * here; those of each question type by its handler ({@see qtype}), this plugin's own or one
 * another plugin adds through \tool_wizards\hook\collect_extensions.
 *
 * Adapted from Moodle core question/bank/editquestion/question.php and mod/quiz/edit.php.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_creator {
    /** @var string[] The question types the quiz mini-wizard can add. */
    const QTYPES = ['multichoice', 'truefalse'];

    /**
     * @var string[] The question types this plugin's own handlers cover. Other plugins may add more:
     *     {@see self::wizard_qtypes()} lists them all.
     */
    const WIZARD_QTYPES = ['multichoice', 'truefalse', 'shortanswer', 'numerical', 'match', 'essay', 'description'];

    /** @var int The most numbered answer slots a wizard question has. */
    const MAXSLOTS = 10;

    /**
     * @var array The simple fields every question has, for the content handlers' fields(). Each
     *     question type's handler adds its own ({@see self::fields()}).
     */
    const FIELDS = [
        'qtype' => 'question type',
        'name' => 'name in the question bank (optional; the start of the question text when empty)',
        'questiontext' => 'the question, or for a description the text students read (editor, or plain text)',
        'feedbackright' => 'feedback for a right answer (plain text, optional)',
        'feedbackwrong' => 'feedback for a wrong answer (plain text, optional)',
        'mark' => 'the marks the question is worth (default 1; not for a description)',
    ];

    /**
     * Whether the current user can add a first question of this type to the quiz.
     *
     * @param \cm_info $cm the quiz
     * @param string $qtype the question type
     * @return bool
     */
    public static function can_add(\cm_info $cm, string $qtype): bool {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        if (!in_array($qtype, self::QTYPES, true) || !\question_bank::qtype_enabled($qtype)) {
            return false;
        }
        $context = \core\context\module::instance($cm->id);
        return has_all_capabilities(['mod/quiz:manage', 'moodle/question:add'], $context);
    }

    /**
     * The question types the wizards have a handler for: this plugin's own, then those other
     * plugins add.
     *
     * @return string[]
     */
    public static function wizard_qtypes(): array {
        return array_keys(extensions::qtypes());
    }

    /**
     * The question types the wizards can add that the site has switched on.
     *
     * @return string[]
     */
    public static function enabled_qtypes(): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        return array_values(array_filter(self::wizard_qtypes(), fn($qtype) => \question_bank::qtype_enabled($qtype)));
    }

    /**
     * The handler of a question type the wizards can add, if the site has it switched on.
     *
     * @param string $qtype the question type
     * @return string|null the handler class, a subclass of {@see qtype}
     */
    public static function handler(string $qtype): ?string {
        return in_array($qtype, self::enabled_qtypes(), true) ? extensions::qtypes()[$qtype] : null;
    }

    /**
     * The simple fields of the question wizards: those every question has, then those of each
     * question type the wizards have a handler for.
     *
     * @return array field => description
     */
    public static function fields(): array {
        $fields = self::FIELDS;
        $fields['qtype'] = 'question type: ' . implode(', ', self::wizard_qtypes());
        foreach (extensions::qtypes() as $class) {
            $fields += $class::fields();
        }
        return $fields;
    }

    /**
     * Whether question banks can be used yet (after the upgrade to Moodle 5.0, once the
     * question bank migration has finished).
     *
     * @return bool
     */
    public static function banks_ready(): bool {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        return \core_question\local\bank\question_bank_helper::has_bank_migration_task_completed_successfully();
    }

    /**
     * Create the question and add it to the quiz.
     *
     * @param \cm_info $cm the quiz
     * @param string $qtype one of {@see self::wizard_qtypes()}
     * @param array $answers submitted field => value for the question type's own form
     * @param int $page the quiz page to add it to (0: the last page, as the quiz does by default)
     * @return \stdClass the saved question
     * @throws \moodle_exception when the user may not, or the question form refuses the answers
     */
    public static function add_to_quiz(\cm_info $cm, string $qtype, array $answers, int $page = 0): \stdClass {
        global $CFG, $DB;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $context = \core\context\module::instance($cm->id);
        if (
            !self::handler($qtype) || !has_all_capabilities(['mod/quiz:manage', 'moodle/question:add'], $context)
        ) {
            throw new \moodle_exception('error_noquestion', 'tool_wizards');
        }
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $quiz->cmid = $cm->id;
        $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
        // Checked before the question is created, so a quiz with attempts gets no stray question.
        $quizobj->get_structure()->check_can_be_edited();

        $question = self::create($cm, $qtype, $answers, 0, '/mod/quiz/edit.php?cmid=' . $cm->id, true);

        // And what mod/quiz/edit.php does with the new question.
        quiz_require_question_use($question->id);
        quiz_add_quiz_question($question->id, $quiz, $page);
        quiz_delete_previews($quiz);
        $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();

        return $question;
    }

    /**
     * Create the question in a shared question bank.
     *
     * @param \cm_info $cm the question bank (mod_qbank)
     * @param string $qtype one of {@see self::wizard_qtypes()}
     * @param array $answers submitted field => value for the question type's own form
     * @param int $categoryid a category of this bank, or 0 for its default category
     * @return \stdClass the saved question
     * @throws \moodle_exception when the user may not, or the question form refuses the answers
     */
    public static function add_to_bank(\cm_info $cm, string $qtype, array $answers, int $categoryid = 0): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $context = \core\context\module::instance($cm->id);
        if (
            !self::handler($qtype) || !has_capability('moodle/question:add', $context)
        ) {
            throw new \moodle_exception('error_noquestion', 'tool_wizards');
        }
        return self::create($cm, $qtype, $answers, $categoryid, '/question/edit.php?cmid=' . $cm->id, false);
    }

    /**
     * Create a question in the question bank of an activity (a quiz's own bank, or a shared bank),
     * as question/bank/editquestion/question.php does.
     *
     * @param \cm_info $cm the activity whose bank it goes in
     * @param string $qtype the question type
     * @param array $answers submitted field => value for the question type's own form
     * @param int $categoryid a category in that bank, or 0 for its default category
     * @param string $returnurl where the question form would return to
     * @param bool $forquiz whether the question is being added to the quiz straight away
     * @return \stdClass the saved question
     * @throws \moodle_exception when the bank cannot be used, or the question form refuses the answers
     */
    protected static function create(
        \cm_info $cm,
        string $qtype,
        array $answers,
        int $categoryid,
        string $returnurl,
        bool $forquiz
    ): \stdClass {
        global $CFG, $DB, $USER, $PAGE;
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/editlib.php');

        if (!self::banks_ready()) {
            throw new \moodle_exception('error_noquestion', 'tool_wizards');
        }
        $context = \core\context\module::instance($cm->id);
        $PAGE->set_context($context);
        if (!$PAGE->has_set_url()) {
            // The question form's editors read the page URL, as on question/bank/editquestion/question.php.
            $PAGE->set_url(new \moodle_url($returnurl));
        }

        // As question/bank/editquestion/question.php prepares a new question, in the bank's default
        // category as the "Create a new question" button does, or in a category of that bank.
        $contexts = new question_edit_contexts($context);
        if ($categoryid) {
            $category = $DB->get_record('question_categories', ['id' => $categoryid, 'contextid' => $context->id]);
            if (!$category) {
                throw new \moodle_exception('error_noquestion', 'tool_wizards');
            }
        } else {
            $category = question_get_default_category($context->id, true);
        }
        $question = new \stdClass();
        $question->category = $category->id;
        $question->qtype = $qtype;
        $question->createdby = $USER->id;
        $question->contextid = $category->contextid;
        $question->formoptions = (object) [
            'canedit' => question_has_capability_on($question, 'edit'),
            'canmove' => question_has_capability_on($question, 'move') && has_capability('moodle/question:add', $context),
            'cansaveasnew' => false,
            'repeatelements' => true,
            'mustbeusable' => $forquiz,
        ];
        require_capability('moodle/question:add', $context);

        $qtypeobj = \question_bank::get_qtype($qtype);
        $toform = fullclone($question);
        $toform->category = "{$category->id},{$category->contextid}";
        $toform->mdlscrollto = 0;
        $toform->appendqnumstring = $forquiz ? 'addquestion' : '';
        $toform->returnurl = $returnurl;
        $toform->makecopy = 0;
        $toform->idnumber = null;
        $toform->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $toform->cmid = $cm->id;
        $toform->courseid = $cm->course;
        $toform->inpopup = 0;
        $customfieldhandler = \qbank_customfields\customfield\question_handler::create();
        $customfieldhandler->instance_form_before_set_data($toform);

        // The form builds as many answer slots as the request's "noanswers" says (moodleform::repeat_elements()
        // reads it with optional_param(), which falls back to $_POST), as when a teacher presses "Blanks for more
        // choices". Without it, answers beyond the form's first few would arrive half filled in.
        $noanswers = isset($answers['noanswers']) ? (int) $answers['noanswers'] : null;
        $factory = function () use ($qtypeobj, $question, $category, $contexts, $toform, $noanswers) {
            $saved = $_POST['noanswers'] ?? null;
            if ($noanswers !== null) {
                $_POST['noanswers'] = $noanswers;
            }
            try {
                $form = $qtypeobj->create_editing_form('question.php', clone $question, $category, $contexts, true);
            } finally {
                if ($saved === null) {
                    unset($_POST['noanswers']);
                } else {
                    $_POST['noanswers'] = $saved;
                }
            }
            $form->set_data(clone $toform);
            return $form;
        };
        $values = form_submission::browser_values($factory(), $answers);
        [, $fromform, $errors] = form_submission::submit($factory, $values);
        if (!$fromform) {
            throw new \moodle_exception('error_questionform', 'tool_wizards', '', null, json_encode($errors));
        }

        // The rest of question.php's saving, for a new question.
        if ($forquiz) {
            $fromform->modulename = 'mod_quiz';
        }
        if (empty($fromform->usecurrentcat) && !empty($fromform->categorymoveto)) {
            $fromform->category = $fromform->categorymoveto;
        }
        if (!empty($CFG->questiondefaultssave)) {
            $qtypeobj->save_defaults_for_new_questions($fromform);
        }
        $question = $qtypeobj->save_question($question, $fromform);
        $customfieldhandler->instance_form_save($fromform);
        \question_bank::notify_question_edited($question->id);

        return $question;
    }

    /**
     * The question type of the wizard's fields.
     *
     * @param array $fields the wizard's fields ({@see self::fields()})
     * @return string
     */
    public static function qtype_of(array $fields): string {
        return is_string($fields['qtype'] ?? null) ? $fields['qtype'] : '';
    }

    /**
     * Check the wizard's fields before anything is saved.
     *
     * @param array $fields the wizard's fields ({@see self::fields()})
     * @return array field => error message
     */
    public static function check_fields(array $fields): array {
        $handler = self::handler(self::qtype_of($fields));
        if (!$handler) {
            return ['qtype' => get_string('error_noquestion', 'tool_wizards')];
        }
        $errors = [];
        if (!self::editor_value($fields['questiontext'] ?? '')) {
            $errors['questiontext'] = get_string(
                $handler::graded() ? 'error_questiontext' : 'question_error_text',
                'tool_wizards'
            );
        }
        if ($handler::graded() && !qtype::is_blank($fields['mark'] ?? '')) {
            $mark = qtype::number($fields['mark']);
            if ($mark === null || $mark < 0) {
                $errors['mark'] = get_string('question_error_mark', 'tool_wizards');
            }
        }
        return $errors + $handler::check($fields);
    }

    /**
     * The question type's own form fields for the wizard's fields, as a teacher would fill in
     * that form. Fields the wizard does not set keep the form's defaults.
     *
     * @param array $fields the wizard's fields ({@see self::fields()}), already checked
     * @return array form field => value, nested like $_POST
     */
    public static function form_fields(array $fields): array {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');
        $handler = self::handler(self::qtype_of($fields));
        if (!$handler) {
            throw new \moodle_exception('error_noquestion', 'tool_wizards');
        }
        $text = self::editor_value($fields['questiontext'] ?? '') ?? ['text' => '', 'format' => FORMAT_HTML];
        $form = ['name' => self::name_for($fields, $text), 'questiontext' => $text];
        if ($handler::graded()) {
            // Always say: the question form would otherwise start from the mark this teacher last used.
            $form['defaultmark'] = qtype::is_blank($fields['mark'] ?? '') ? '1' : qtype::number_string($fields['mark']);
        }
        $right = self::editor_value($fields['feedbackright'] ?? '');
        $wrong = self::editor_value($fields['feedbackwrong'] ?? '');
        return $form + $handler::form_fields($fields, $right, $wrong);
    }

    /**
     * The question's name: the one given, or the start of its text.
     *
     * @param array $fields the wizard's fields
     * @param array $text the question text as an editor value
     * @return string
     */
    public static function name_for(array $fields, array $text): string {
        $name = is_scalar($fields['name'] ?? null) ? trim((string) $fields['name']) : '';
        if ($name === '') {
            $plain = trim(preg_replace('/\s+/u', ' ', html_to_text((string) $text['text'], 0, false)));
            $name = shorten_text($plain, 60);
        }
        if ($name === '') {
            $qtype = self::qtype_of($fields);
            $name = isset(extensions::qtypes()[$qtype]) && \question_bank::qtype_exists($qtype)
                ? get_string('pluginname', 'qtype_' . $qtype)
                : get_string('question_defaultname', 'tool_wizards');
        }
        return \core_text::substr($name, 0, 255);
    }

    /**
     * A plain-text or editor value as an editor value, or null when it is empty.
     *
     * @param mixed $value plain text, or ['text' => ..., 'format' => ..., 'itemid' => ...]
     * @return array|null
     */
    public static function editor_value($value): ?array {
        if (is_array($value)) {
            $text = (string) ($value['text'] ?? '');
            if (html_is_blank($text)) {
                return null;
            }
            $out = ['text' => $text, 'format' => (int) ($value['format'] ?? FORMAT_HTML)];
            if (!empty($value['itemid'])) {
                // The wizard's own draft area: the question form saves its files from there.
                $out['itemid'] = (int) $value['itemid'];
            }
            return $out;
        }
        $text = is_scalar($value) ? trim((string) $value) : '';
        if ($text === '') {
            return null;
        }
        return ['text' => '<p>' . nl2br(s($text), false) . '</p>', 'format' => FORMAT_HTML];
    }
}
