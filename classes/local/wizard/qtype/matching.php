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
 * Matching (qtype_match; "match" is a reserved word in PHP): items, each matched with an answer, plus extra wrong answers.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matching extends qtype {
    /**
     * The fields of a matching question.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'item' => 'matching: the items students match, item[1] to item[10]',
            'match' => 'matching: what each item matches, match[1] to match[10]; a match without an item is an extra wrong answer',
        ];
    }

    /**
     * A match for every item, at least two pairs, and at least three answers.
     *
     * @param array $fields the wizard's fields
     * @return array
     */
    public static function check(array $fields): array {
        $items = self::slots($fields['item'] ?? []);
        $matches = self::slots($fields['match'] ?? []);
        if (array_diff_key($items, $matches)) {
            return ['match' => get_string('question_error_matchmissing', 'tool_wizards')];
        } else if (count($items) < 2) {
            return ['item' => get_string('question_error_twopairs', 'tool_wizards')];
        } else if (count($matches) < 3) {
            return ['match' => get_string('question_error_threematches', 'tool_wizards')];
        }
        return [];
    }

    /**
     * The pairs first, then the extra answers that match no item.
     *
     * @param array $fields the wizard's fields
     * @param array|null $right feedback for a right answer
     * @param array|null $wrong feedback for a wrong answer
     * @return array
     */
    public static function form_fields(array $fields, ?array $right, ?array $wrong): array {
        $items = self::slots($fields['item'] ?? []);
        $form = [];
        $slot = 0;
        foreach ([true, false] as $paired) {
            foreach (self::slots($fields['match'] ?? []) as $n => $match) {
                if (isset($items[$n]) !== $paired) {
                    continue;
                }
                $form['subquestions'][$slot] = ['text' => $paired ? s($items[$n]) : '', 'format' => FORMAT_HTML];
                $form['subanswers'][$slot] = $match;
                $slot++;
            }
        }
        $form['noanswers'] = (string) max(QUESTION_NUMANS_START, $slot);
        self::combined_feedback($form, $right, $wrong);
        return $form;
    }
}
