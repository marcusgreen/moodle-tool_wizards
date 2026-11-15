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
 * True or false.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class truefalse extends qtype {
    /**
     * The fields of a true/false question.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'correctanswer' => 'true/false: 1 when the statement is true, 0 when false',
        ];
    }

    /**
     * The right answer, and the feedback for each answer.
     *
     * @param array $fields the wizard's fields
     * @param array|null $right feedback for a right answer
     * @param array|null $wrong feedback for a wrong answer
     * @return array
     */
    public static function form_fields(array $fields, ?array $right, ?array $wrong): array {
        $true = (int) ($fields['correctanswer'] ?? 1) === 1;
        $form = ['correctanswer' => $true ? '1' : '0'];
        if ($right) {
            $form[$true ? 'feedbacktrue' : 'feedbackfalse'] = $right;
        }
        if ($wrong) {
            $form[$true ? 'feedbackfalse' : 'feedbacktrue'] = $wrong;
        }
        return $form;
    }
}
