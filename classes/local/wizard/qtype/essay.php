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
 * Essay: a written answer, or an uploaded file, marked by a teacher.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class essay extends qtype {
    /** @var string[] How students may answer: wizard value => the essay's response format. */
    const RESPONSE_FORMATS = ['editor' => 'editor', 'plain' => 'plain', 'file' => 'noinline'];

    /**
     * The fields of an essay question.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'responseformat' => 'essay: editor (text with formatting), plain (plain text) or file (upload a file)',
            'responselines' => 'essay: the height of the answer box in lines (2, 3, 5, 10, ... 40)',
            'maxwords' => 'essay: the most words students may write (0 or empty: no limit)',
            'graderinfo' => 'essay: notes for whoever marks it (plain text)',
        ];
    }

    /**
     * A known way of answering, and a whole word limit.
     *
     * @param array $fields the wizard's fields
     * @return array
     */
    public static function check(array $fields): array {
        $errors = [];
        $format = $fields['responseformat'] ?? 'editor';
        if (!is_string($format) || !isset(self::RESPONSE_FORMATS[$format])) {
            $errors['responseformat'] = get_string('question_error_responseformat', 'tool_wizards');
        }
        if (!self::is_blank($fields['maxwords'] ?? '')) {
            $words = self::number($fields['maxwords']);
            if ($words === null || $words < 0 || $words != (int) $words) {
                $errors['maxwords'] = get_string('question_error_maxwords', 'tool_wizards');
            }
        }
        return $errors;
    }

    /**
     * How students answer, and the notes for whoever marks it.
     *
     * @param array $fields the wizard's fields
     * @param array|null $right feedback for a right answer (not used)
     * @param array|null $wrong feedback for a wrong answer (not used)
     * @return array
     */
    public static function form_fields(array $fields, ?array $right, ?array $wrong): array {
        $format = self::RESPONSE_FORMATS[$fields['responseformat'] ?? 'editor'] ?? 'editor';
        $form = ['responseformat' => $format];
        if ($format === 'noinline') {
            // Students upload one file; the essay form requires it to be required.
            $form['attachments'] = '1';
            $form['attachmentsrequired'] = '1';
        } else {
            $form['responserequired'] = '1';
            if (!self::is_blank($fields['responselines'] ?? '')) {
                $form['responsefieldlines'] = (string) (int) $fields['responselines'];
            }
            $words = (int) self::number($fields['maxwords'] ?? '');
            if ($words > 0) {
                $form['maxwordenabled'] = '1';
                $form['maxwordlimit'] = (string) $words;
            }
        }
        $graderinfo = \tool_wizards\local\question_creator::editor_value($fields['graderinfo'] ?? '');
        if ($graderinfo) {
            $form['graderinfo'] = $graderinfo;
        }
        return $form;
    }
}
