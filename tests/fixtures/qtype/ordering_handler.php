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

namespace tool_wizards\tests\fixtures\qtype;

use tool_wizards\hook\collect_extensions;
use tool_wizards\local\wizard\qtype;

/**
 * A question type handler as another plugin would add it: ordering (qtype_ordering), which this
 * plugin has no handler of its own for.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ordering_handler extends qtype {
    /**
     * The items, in their right order.
     *
     * @return array
     */
    public static function fields(): array {
        return ['order' => 'ordering: the items in their right order, order[1] to order[10]'];
    }

    /**
     * At least two items.
     *
     * @param array $fields the wizard's fields
     * @return array
     */
    public static function check(array $fields): array {
        return count(self::slots($fields['order'] ?? [])) < 2 ? ['order' => 'Two items, please'] : [];
    }

    /**
     * The items as the ordering form's draggable items.
     *
     * @param array $fields the wizard's fields
     * @param array|null $right feedback for a right answer
     * @param array|null $wrong feedback for a wrong answer
     * @return array
     */
    public static function form_fields(array $fields, ?array $right, ?array $wrong): array {
        $form = [];
        $slot = 0;
        foreach (self::slots($fields['order'] ?? []) as $item) {
            $form['answer'][$slot++] = ['text' => s($item), 'format' => FORMAT_HTML];
        }
        self::combined_feedback($form, $right, $wrong);
        return $form;
    }

    /**
     * The hook callback: add this handler, and some that must be ignored.
     *
     * @param collect_extensions $hook the hook
     */
    public static function callback(collect_extensions $hook): void {
        $hook->add_qtype('ordering', self::class);
        $hook->add_qtype('notinstalled', self::class);
        $hook->add_qtype('Bad name', self::class);
        $hook->add_qtype('gapselect', \stdClass::class);
    }
}
