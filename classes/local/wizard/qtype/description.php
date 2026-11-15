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
 * Description: text students read, with nothing to answer.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class description extends qtype {
    /**
     * Not marked.
     *
     * @return bool
     */
    public static function graded(): bool {
        return false;
    }

    /**
     * Nothing beyond the text.
     *
     * @param array $fields the wizard's fields
     * @param array|null $right feedback for a right answer (not used)
     * @param array|null $wrong feedback for a wrong answer (not used)
     * @return array
     */
    public static function form_fields(array $fields, ?array $right, ?array $wrong): array {
        return [];
    }
}
