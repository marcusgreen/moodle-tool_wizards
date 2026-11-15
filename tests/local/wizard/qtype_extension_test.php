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

use tool_wizards\hook\collect_extensions;
use tool_wizards\local\question_creator;
use tool_wizards\local\wizard\content\qbank_question;
use tool_wizards\local\wizard\content\quiz_question;
use tool_wizards\tests\fixtures\qtype\ordering_handler;

/**
 * Tests for question types other plugins add through the collect_extensions hook.
 *
 * @package    tool_wizards
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(collect_extensions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(extensions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(qtype::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(question_creator::class)]
final class qtype_extension_test extends \advanced_testcase {
    /**
     * A teacher in a course, and the fixture plugin's hook callback in place.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->libdir . '/questionlib.php');
        require_once(__DIR__ . '/../../fixtures/qtype/ordering_handler.php');
        $this->resetAfterTest();
        \core\di::set(
            \core\hook\manager::class,
            \core\hook\manager::phpunit_get_instance(['tool_wizards' => __DIR__ . '/../../fixtures/qtype/hooks.php'])
        );
        extensions::reset();
    }

    /**
     * Forget the collected extensions, so other tests see only the plugin's own.
     */
    protected function tearDown(): void {
        extensions::reset();
        parent::tearDown();
    }

    /**
     * The handler is offered next to the built-in ones; bad registrations are ignored.
     */
    public function test_registration(): void {
        $qtypes = extensions::qtypes();
        $this->assertDebuggingCalledCount(2);
        $this->assertSame(ordering_handler::class, $qtypes['ordering']);
        $this->assertSame(extensions::BUILTIN_QTYPES, array_slice($qtypes, 0, count(extensions::BUILTIN_QTYPES)));
        $this->assertArrayNotHasKey('Bad name', $qtypes);
        $this->assertArrayNotHasKey('gapselect', $qtypes, 'Not a qtype handler.');

        // Registered, but the site has no such question type.
        $this->assertContains('notinstalled', question_creator::wizard_qtypes());
        $this->assertNotContains('notinstalled', question_creator::enabled_qtypes());
        $this->assertNull(question_creator::handler('notinstalled'));
        $this->assertSame(ordering_handler::class, question_creator::handler('ordering'));

        // Its fields can be set by wizard definitions.
        $this->assertArrayHasKey('order', quiz_question::fields());
        $this->assertArrayHasKey('order', qbank_question::fields());
        $this->assertStringContainsString('ordering', quiz_question::fields()['qtype']);
    }

    /**
     * A question of the added type is checked and saved as the built-in ones are.
     */
    public function test_add_to_quiz(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->setUser($generator->create_and_enrol($course, 'editingteacher'));
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($quiz->cmid);
        extensions::qtypes();
        $this->resetDebugging();

        $this->assertContains('ordering', quiz_question::offered($cm)['qtype']);
        $this->assertSame(['order' => 'Two items, please'], quiz_question::check($cm, [
            'qtype' => 'ordering', 'questiontext' => 'Put these in order', 'order' => [1 => 'One'],
        ]));
        $this->assertArrayHasKey('qtype', quiz_question::check($cm, [
            'qtype' => 'notinstalled', 'questiontext' => 'Put these in order',
        ]));

        $fields = [
            'qtype' => 'ordering', 'questiontext' => 'Put the planets in order from the Sun',
            'order' => [1 => 'Mercury', 2 => 'Venus', 3 => 'Earth'], 'feedbackright' => 'Well done', 'mark' => '3',
        ];
        $this->assertSame([], quiz_question::check($cm, $fields));
        quiz_question::save($cm, $fields);

        $q = $DB->get_record('question', ['name' => 'Put the planets in order from the Sun'], '*', MUST_EXIST);
        $this->assertSame('ordering', $q->qtype);
        $this->assertEquals(3, $q->defaultmark);
        $answers = $DB->get_records('question_answers', ['question' => $q->id], 'id');
        $items = array_map(fn($a) => html_to_text($a->answer, 0, false), array_values($answers));
        $this->assertSame(['Mercury', 'Venus', 'Earth'], $items);
        $options = $DB->get_record('qtype_ordering_options', ['questionid' => $q->id], '*', MUST_EXIST);
        $this->assertStringContainsString('Well done', $options->correctfeedback);
        $this->assertTrue($DB->record_exists('quiz_slots', ['quizid' => $quiz->id]));
    }
}
