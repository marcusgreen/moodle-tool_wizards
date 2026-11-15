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
 * Short answer: a word or phrase, compared with the answers accepted.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shortanswer extends qtype {
    /**
     * The fields of a short-answer question.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'accept' => 'short answer: the answers accepted as right, accept[1] to accept[10] (* matches anything)',
            'casesensitive' => 'short answer: 1 when capital letters must match',
        ];
    }

    /**
     * At least one accepted answer.
     *
     * @param array $fields the wizard's fields
     * @return array
     */
    public static function check(array $fields): array {
        if (!self::slots($fields['accept'] ?? [])) {
            return ['accept' => get_string('question_error_noaccept', 'tool_wizards')];
        }
        return [];
    }

    /**
     * The accepted answers, and a last "*" answer for the feedback to a wrong one.
     *
     * @param array $fields the wizard's fields
     * @param array|null $right feedback for a right answer
     * @param array|null $wrong feedback for a wrong answer
     * @return array
     */
    public static function form_fields(array $fields, ?array $right, ?array $wrong): array {
        $form = [];
        $slot = 0;
        foreach (self::slots($fields['accept'] ?? []) as $accept) {
            $form['answer'][$slot] = $accept;
            $form['fraction'][$slot] = '1.0';
            if ($right) {
                $form['feedback'][$slot] = $right;
            }
            $slot++;
        }
        if ($wrong) {
            // A last answer "*" matches anything else: the usual way to give feedback for wrong answers.
            $form['answer'][$slot] = '*';
            $form['fraction'][$slot] = '0.0';
            $form['feedback'][$slot] = $wrong;
            $slot++;
        }
        $form['usecase'] = empty($fields['casesensitive']) ? '0' : '1';
        $form['noanswers'] = (string) max(QUESTION_NUMANS_START, $slot);
        return $form;
    }
}
