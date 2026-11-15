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

namespace tool_wizards\local\wizard;

use tool_wizards\local\question_creator;

/**
 * A question type the question wizards can add: how the wizard's simple fields become that
 * type's own editing form fields.
 *
 * The fields every question has (qtype, name, questiontext, feedbackright, feedbackwrong and
 * mark) are handled by {@see question_creator}; a handler adds the fields of its own type, checks
 * them, and turns them into the type's form fields. The question is then created with the type's
 * own editing form and save_question(), as for the built-in types.
 *
 * Other plugins add handlers through \tool_wizards\hook\collect_extensions::add_qtype().
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype {
    /**
     * The fields of this question type, beyond those every question has.
     *
     * Names must not be those of the shared fields ({@see question_creator::FIELDS}); a field
     * another type already uses is shared with it, so it should mean the same thing.
     *
     * @return array field => description, for the content handlers' fields()
     */
    public static function fields(): array {
        return [];
    }

    /**
     * Whether the question is marked (has a mark, and its text is a question).
     *
     * @return bool
     */
    public static function graded(): bool {
        return true;
    }

    /**
     * Check this type's fields before anything is saved. The shared fields are already checked.
     *
     * @param array $fields the wizard's fields
     * @return array field => error message
     */
    public static function check(array $fields): array {
        return [];
    }

    /**
     * The question type's own form fields for this type's wizard fields, as a teacher would fill
     * in that form. The name, question text and mark are already set; fields not set keep the
     * form's defaults.
     *
     * @param array $fields the wizard's fields, already checked
     * @param array|null $right feedback for a right answer, as an editor value
     * @param array|null $wrong feedback for a wrong answer, as an editor value
     * @return array form field => value, nested like $_POST
     */
    abstract public static function form_fields(array $fields, ?array $right, ?array $wrong): array;

    /**
     * Set the combined feedback for a right and a wrong answer, keeping the form's defaults otherwise.
     *
     * @param array $form the form fields, changed in place
     * @param array|null $right feedback for a right answer
     * @param array|null $wrong feedback for a wrong answer
     */
    protected static function combined_feedback(array &$form, ?array $right, ?array $wrong): void {
        if ($right) {
            $form['correctfeedback'] = $right;
        }
        if ($wrong) {
            $form['incorrectfeedback'] = $wrong;
        }
    }

    /**
     * The filled-in numbered slots of a field (choice[1], choice[2], ...), in order.
     *
     * @param mixed $value the field's value
     * @return array slot number => trimmed text
     */
    public static function slots($value): array {
        $out = [];
        foreach (is_array($value) ? $value : [] as $n => $text) {
            if (
                is_scalar($text) && trim((string) $text) !== ''
                    && (int) $n >= 1 && (int) $n <= question_creator::MAXSLOTS
            ) {
                $out[(int) $n] = trim((string) $text);
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * Whether a value is empty.
     *
     * @param mixed $value the value
     * @return bool
     */
    public static function is_blank($value): bool {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * A number from a field (a number, or a string in the user's or the plain format).
     *
     * @param mixed $value the value
     * @return float|null null when it is not a number
     */
    public static function number($value): ?float {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $number = unformat_float(trim($value), true);
        return $number === false || $number === null ? null : (float) $number;
    }

    /**
     * A number as the question forms take it.
     *
     * @param mixed $value the value
     * @return string
     */
    public static function number_string($value): string {
        $number = self::number($value);
        return $number === null ? (is_scalar($value) ? trim((string) $value) : '') : format_float($number, -1, false, true);
    }
}
