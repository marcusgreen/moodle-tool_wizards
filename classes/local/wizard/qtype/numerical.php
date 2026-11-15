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

namespace tool_wizards\local\wizard\qtype;

use tool_wizards\local\wizard\qtype;

/**
 * Numerical: a number, right within a tolerance.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class numerical extends qtype {
    /**
     * The fields of a numerical question.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'number' => 'numerical: the right number',
            'tolerance' => 'numerical: also accept numbers this far either side of it (default 0)',
        ];
    }

    /**
     * A right number, and a tolerance that is not negative.
     *
     * @param array $fields the wizard's fields
     * @return array
     */
    public static function check(array $fields): array {
        $errors = [];
        if (self::is_blank($fields['number'] ?? '') || self::number($fields['number']) === null) {
            $errors['number'] = get_string('question_error_number', 'tool_wizards');
        }
        if (!self::is_blank($fields['tolerance'] ?? '')) {
            $tolerance = self::number($fields['tolerance']);
            if ($tolerance === null || $tolerance < 0) {
                $errors['tolerance'] = get_string('question_error_tolerance', 'tool_wizards');
            }
        }
        return $errors;
    }

    /**
     * The right number, a last "*" answer for the feedback to a wrong one, and no units.
     *
     * @param array $fields the wizard's fields
     * @param array|null $right feedback for a right answer
     * @param array|null $wrong feedback for a wrong answer
     * @return array
     */
    public static function form_fields(array $fields, ?array $right, ?array $wrong): array {
        \question_bank::get_qtype('numerical');
        $form = [];
        $form['answer'][0] = self::number_string($fields['number'] ?? '');
        $form['tolerance'][0] = self::is_blank($fields['tolerance'] ?? '') ? '0'
            : self::number_string($fields['tolerance']);
        $form['fraction'][0] = '1.0';
        if ($right) {
            $form['feedback'][0] = $right;
        }
        if ($wrong) {
            $form['answer'][1] = '*';
            $form['tolerance'][1] = '0';
            $form['fraction'][1] = '0.0';
            $form['feedback'][1] = $wrong;
        }
        // No units (the form's default may be a teacher's saved preference).
        $form['unitrole'] = (string) \qtype_numerical::UNITNONE;
        return $form;
    }
}
