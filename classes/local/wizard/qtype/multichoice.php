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
 * Multiple choice, with one right answer or several.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class multichoice extends qtype {
    /** @var array Number of right (or wrong) answers => each one's share of the marks, as the form offers it. */
    const SHARES = [1 => '1.0', 2 => '0.5', 3 => '0.3333333', 4 => '0.25', 5 => '0.2', 6 => '0.1666667',
        7 => '0.1428571', 8 => '0.125', 9 => '0.1111111', 10 => '0.1'];

    /**
     * The fields of a multiple-choice question.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'single' => 'multiple choice: 1 one right answer, 0 several right answers',
            'choice' => 'multiple choice: the answers to choose from, choice[1] to choice[10] (plain text)',
            'rightchoice' => 'multiple choice with one right answer: the number of the right choice',
            'right' => 'multiple choice with several right answers: right[n] = 1 when choice[n] is right',
        ];
    }

    /**
     * At least two choices, and a right one among them.
     *
     * @param array $fields the wizard's fields
     * @return array
     */
    public static function check(array $fields): array {
        $choices = self::slots($fields['choice'] ?? []);
        $rights = self::right_choices($fields);
        $field = self::is_single($fields) ? 'rightchoice' : 'right';
        if (count($choices) < 2) {
            return ['choice' => get_string('error_twochoices', 'tool_wizards')];
        } else if (array_diff($rights, array_keys($choices))) {
            return [$field => get_string('error_correctchoice', 'tool_wizards')];
        } else if (!$rights) {
            return [$field => get_string('question_error_noright', 'tool_wizards')];
        }
        return [];
    }

    /**
     * The choices and their marks.
     *
     * @param array $fields the wizard's fields
     * @param array|null $right feedback for a right answer
     * @param array|null $wrong feedback for a wrong answer
     * @return array
     */
    public static function form_fields(array $fields, ?array $right, ?array $wrong): array {
        // As qtype_multichoice_edit_form: one right answer gets 100%; with several, the right
        // ones share 100% and the wrong ones share -100%, so ticking everything scores nothing.
        $single = self::is_single($fields);
        $choices = self::slots($fields['choice'] ?? []);
        $rights = array_values(array_intersect(self::right_choices($fields), array_keys($choices)));
        $nright = count($rights);
        $nwrong = count($choices) - $nright;
        $form = ['single' => $single ? '1' : '0'];
        $slot = 0;
        foreach ($choices as $n => $choice) {
            $form['answer'][$slot] = ['text' => s($choice), 'format' => FORMAT_HTML];
            if (in_array($n, $rights, true)) {
                $form['fraction'][$slot] = $single ? '1.0' : self::SHARES[$nright];
            } else {
                $form['fraction'][$slot] = $single || !$nwrong ? '0.0' : '-' . self::SHARES[$nwrong];
            }
            $slot++;
        }
        $form['noanswers'] = (string) max(5, QUESTION_NUMANS_START, $slot);
        self::combined_feedback($form, $right, $wrong);
        return $form;
    }

    /**
     * Whether a multiple-choice question has one right answer (the default).
     *
     * @param array $fields the wizard's fields
     * @return bool
     */
    protected static function is_single(array $fields): bool {
        return (int) ($fields['single'] ?? 1) === 1;
    }

    /**
     * The numbers of the right choices.
     *
     * @param array $fields the wizard's fields
     * @return int[]
     */
    protected static function right_choices(array $fields): array {
        if (self::is_single($fields)) {
            $n = (int) ($fields['rightchoice'] ?? 0);
            return $n > 0 ? [$n] : [];
        }
        $out = [];
        foreach ((array) ($fields['right'] ?? []) as $n => $on) {
            if (!empty($on)) {
                $out[] = (int) $n;
            }
        }
        sort($out);
        return $out;
    }
}
