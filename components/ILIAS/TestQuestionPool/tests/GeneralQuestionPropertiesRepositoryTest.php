<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

use ILIAS\TestQuestionPool\Questions\GeneralQuestionPropertiesRepository;

/**
* Unit tests for GeneralQuestionPropertiesRepository
*
* @author Test Suite
*
* @ingroup components\ILIASTestQuestionPool
*
* Tests the isQuestionTypeAvailable method behavior, especially for the fix
* in PR #10494 where empty plugin names should be recognized as available question types.
*/
class GeneralQuestionPropertiesRepositoryTest extends assBaseTestCase
{
    protected $backupGlobals = false;

    private GeneralQuestionPropertiesRepository $repository;
    private \ilDBInterface $db_mock;
    private \ilComponentFactory $component_factory_mock;
    private \ilComponentRepository $component_repository_mock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db_mock = $this->createMock(\ilDBInterface::class);
        $this->component_factory_mock = $this->createMock(\ilComponentFactory::class);
        $this->component_repository_mock = $this->createMock(\ilComponentRepository::class);

        $this->repository = new GeneralQuestionPropertiesRepository(
            $this->db_mock,
            $this->component_factory_mock,
            $this->component_repository_mock
        );
    }

    /**
     * Test that non-plugin question types (is_plugin = false) are recognized as available.
     * This is the main fix from PR #10494 - empty plugin names should result in available question types.
     */
    public function testNonPluginQuestionTypeIsAvailable(): void
    {
        $question_id = 123;
        $type_tag = 'assSingleChoice';

        // Mock database query result
        $db_record = new \stdClass();
        $db_record->question_id = $question_id;
        $db_record->original_id = null;
        $db_record->external_id = null;
        $db_record->obj_fi = 1;
        $db_record->oq_obj_fi = null;
        $db_record->question_type_fi = 1;
        $db_record->type_tag = $type_tag;
        $db_record->is_plugin = 0; // is_plugin = false (from SQL: qt.plugin as is_plugin)
        $db_record->plugin_name = ''; // empty plugin name
        $db_record->owner = 6;
        $db_record->title = 'Test Question';
        $db_record->description = '';
        $db_record->question_text = 'Test';
        $db_record->points = 1.0;
        $db_record->nr_of_tries = 0;
        $db_record->lifecycle = 'draft';
        $db_record->author = 'Test Author';
        $db_record->tstamp = time();
        $db_record->created = time();
        $db_record->complete = 1;
        $db_record->add_cont_edit_mode = 0;

        $query_result_mock = $this->createMock(\ilDBStatement::class);
        $query_result_mock->expects($this->once())
            ->method('fetchObject')
            ->willReturnOnConsecutiveCalls($db_record, false);

        $this->db_mock->expects($this->once())
            ->method('query')
            ->with($this->callback(function ($sql) use ($question_id) {
                return strpos($sql, 'q.question_id=' . $question_id) !== false;
            }))
            ->willReturn($query_result_mock);

        // Since is_plugin is false, component_repository should NOT be called
        $this->component_repository_mock->expects($this->never())
            ->method('getComponentByTypeAndName');

        $result = $this->repository->getForQuestionId($question_id);

        $this->assertNotNull($result);
        $this->assertEquals($question_id, $result->getQuestionId());
    }

    /**
     * Test that plugin question types that exist and are active are recognized as available.
     */
    public function testActivePluginQuestionTypeIsAvailable(): void
    {
        $question_id = 456;
        $type_tag = 'assCustomPlugin';
        $plugin_name = 'CustomPlugin';

        // Mock database query result
        $db_record = new \stdClass();
        $db_record->question_id = $question_id;
        $db_record->original_id = null;
        $db_record->external_id = null;
        $db_record->obj_fi = 1;
        $db_record->oq_obj_fi = null;
        $db_record->question_type_fi = 2;
        $db_record->type_tag = $type_tag;
        $db_record->is_plugin = 1; // is_plugin = true (from SQL: qt.plugin as is_plugin)
        $db_record->plugin_name = $plugin_name;
        $db_record->owner = 6;
        $db_record->title = 'Plugin Question';
        $db_record->description = '';
        $db_record->question_text = 'Test';
        $db_record->points = 1.0;
        $db_record->nr_of_tries = 0;
        $db_record->lifecycle = 'draft';
        $db_record->author = 'Test Author';
        $db_record->tstamp = time();
        $db_record->created = time();
        $db_record->complete = 1;
        $db_record->add_cont_edit_mode = 0;

        $query_result_mock = $this->createMock(\ilDBStatement::class);
        $query_result_mock->expects($this->once())
            ->method('fetchObject')
            ->willReturnOnConsecutiveCalls($db_record, false);

        $this->db_mock->expects($this->once())
            ->method('query')
            ->with($this->callback(function ($sql) use ($question_id) {
                return strpos($sql, 'q.question_id=' . $question_id) !== false;
            }))
            ->willReturn($query_result_mock);

        // Mock component repository chain for active plugin
        $component_info_mock = $this->createMock(\ilComponentInfo::class);
        $plugin_slot_mock = $this->createMock(\ilPluginSlotInfo::class);
        $plugin_mock = $this->createMock(\ilPluginInfo::class);

        $this->component_repository_mock->expects($this->once())
            ->method('getComponentByTypeAndName')
            ->with(\ilComponentInfo::TYPE_COMPONENT, 'TestQuestionPool')
            ->willReturn($component_info_mock);

        $component_info_mock->expects($this->once())
            ->method('getPluginSlotById')
            ->with('qst')
            ->willReturn($plugin_slot_mock);

        $plugin_slot_mock->expects($this->once())
            ->method('hasPluginName')
            ->with($type_tag)
            ->willReturn(true);

        $plugin_slot_mock->expects($this->once())
            ->method('getPluginByName')
            ->with($type_tag)
            ->willReturn($plugin_mock);

        $plugin_mock->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        $result = $this->repository->getForQuestionId($question_id);

        $this->assertNotNull($result);
        $this->assertEquals($question_id, $result->getQuestionId());
    }

    /**
     * Test that plugin question types that don't exist are NOT recognized as available.
     */
    public function testNonExistentPluginQuestionTypeIsNotAvailable(): void
    {
        $question_id = 789;
        $type_tag = 'assNonExistentPlugin';

        // Mock database query result
        $db_record = new \stdClass();
        $db_record->question_id = $question_id;
        $db_record->original_id = null;
        $db_record->external_id = null;
        $db_record->obj_fi = 1;
        $db_record->oq_obj_fi = null;
        $db_record->question_type_fi = 3;
        $db_record->type_tag = $type_tag;
        $db_record->plugin = 1; // is_plugin = true
        $db_record->plugin_name = 'NonExistentPlugin';
        $db_record->owner = 6;
        $db_record->title = 'Non-existent Plugin Question';
        $db_record->description = '';
        $db_record->question_text = 'Test';
        $db_record->points = 1.0;
        $db_record->nr_of_tries = 0;
        $db_record->lifecycle = 'draft';
        $db_record->author = 'Test Author';
        $db_record->tstamp = time();
        $db_record->created = time();
        $db_record->complete = 1;
        $db_record->add_cont_edit_mode = 0;

        $query_result_mock = $this->createMock(\ilDBStatement::class);
        $query_result_mock->expects($this->once())
            ->method('fetchObject')
            ->willReturnOnConsecutiveCalls($db_record, false);

        $this->db_mock->expects($this->once())
            ->method('query')
            ->with($this->callback(function ($sql) use ($question_id) {
                return strpos($sql, 'q.question_id=' . $question_id) !== false;
            }))
            ->willReturn($query_result_mock);

        // Mock component repository chain for non-existent plugin
        $component_info_mock = $this->createMock(\ilComponentInfo::class);
        $plugin_slot_mock = $this->createMock(\ilPluginSlotInfo::class);

        $this->component_repository_mock->expects($this->once())
            ->method('getComponentByTypeAndName')
            ->with(\ilComponentInfo::TYPE_COMPONENT, 'TestQuestionPool')
            ->willReturn($component_info_mock);

        $component_info_mock->expects($this->once())
            ->method('getPluginSlotById')
            ->with('qst')
            ->willReturn($plugin_slot_mock);

        $plugin_slot_mock->expects($this->once())
            ->method('hasPluginName')
            ->with($type_tag)
            ->willReturn(false);

        // getPluginByName should not be called if hasPluginName returns false
        $plugin_slot_mock->expects($this->never())
            ->method('getPluginByName');

        $result = $this->repository->getForQuestionId($question_id);

        // Question should be filtered out (not available)
        $this->assertNull($result);
    }

    /**
     * Test that plugin question types that exist but are inactive are NOT recognized as available.
     */
    public function testInactivePluginQuestionTypeIsNotAvailable(): void
    {
        $question_id = 101112;
        $type_tag = 'assInactivePlugin';

        // Mock database query result
        $db_record = new \stdClass();
        $db_record->question_id = $question_id;
        $db_record->original_id = null;
        $db_record->external_id = null;
        $db_record->obj_fi = 1;
        $db_record->oq_obj_fi = null;
        $db_record->question_type_fi = 4;
        $db_record->type_tag = $type_tag;
        $db_record->plugin = 1; // is_plugin = true
        $db_record->plugin_name = 'InactivePlugin';
        $db_record->owner = 6;
        $db_record->title = 'Inactive Plugin Question';
        $db_record->description = '';
        $db_record->question_text = 'Test';
        $db_record->points = 1.0;
        $db_record->nr_of_tries = 0;
        $db_record->lifecycle = 'draft';
        $db_record->author = 'Test Author';
        $db_record->tstamp = time();
        $db_record->created = time();
        $db_record->complete = 1;
        $db_record->add_cont_edit_mode = 0;

        $query_result_mock = $this->createMock(\ilDBStatement::class);
        $query_result_mock->expects($this->once())
            ->method('fetchObject')
            ->willReturnOnConsecutiveCalls($db_record, false);

        $this->db_mock->expects($this->once())
            ->method('query')
            ->with($this->callback(function ($sql) use ($question_id) {
                return strpos($sql, 'q.question_id=' . $question_id) !== false;
            }))
            ->willReturn($query_result_mock);

        // Mock component repository chain for inactive plugin
        $component_info_mock = $this->createMock(\ilComponentInfo::class);
        $plugin_slot_mock = $this->createMock(\ilPluginSlotInfo::class);
        $plugin_mock = $this->createMock(\ilPluginInfo::class);

        $this->component_repository_mock->expects($this->once())
            ->method('getComponentByTypeAndName')
            ->with(\ilComponentInfo::TYPE_COMPONENT, 'TestQuestionPool')
            ->willReturn($component_info_mock);

        $component_info_mock->expects($this->once())
            ->method('getPluginSlotById')
            ->with('qst')
            ->willReturn($plugin_slot_mock);

        $plugin_slot_mock->expects($this->once())
            ->method('hasPluginName')
            ->with($type_tag)
            ->willReturn(true);

        $plugin_slot_mock->expects($this->once())
            ->method('getPluginByName')
            ->with($type_tag)
            ->willReturn($plugin_mock);

        $plugin_mock->expects($this->once())
            ->method('isActive')
            ->willReturn(false);

        $result = $this->repository->getForQuestionId($question_id);

        // Question should be filtered out (not available)
        $this->assertNull($result);
    }

    /**
     * Test that multiple question types are filtered correctly when using getForQuestionIds.
     * This tests the behavior when mixing plugin and non-plugin question types.
     */
    public function testMultipleQuestionTypesFiltering(): void
    {
        $question_ids = [1, 2, 3];

        // Question 1: Non-plugin (should be available)
        $db_record1 = $this->createDbRecord(1, 'assSingleChoice', 0, '');
        // Question 2: Active plugin (should be available)
        $db_record2 = $this->createDbRecord(2, 'assActivePlugin', 1, 'ActivePlugin');
        // Question 3: Inactive plugin (should NOT be available)
        $db_record3 = $this->createDbRecord(3, 'assInactivePlugin', 1, 'InactivePlugin');

        $query_result_mock = $this->createMock(\ilDBStatement::class);
        $query_result_mock->expects($this->exactly(4))
            ->method('fetchObject')
            ->willReturnOnConsecutiveCalls($db_record1, $db_record2, $db_record3, false);

        $this->db_mock->expects($this->once())
            ->method('in')
            ->with('q.question_id', $question_ids, false, \ilDBConstants::T_INTEGER)
            ->willReturn('q.question_id IN (1,2,3)');

        $this->db_mock->expects($this->once())
            ->method('query')
            ->with($this->stringContains('q.question_id IN (1,2,3)'))
            ->willReturn($query_result_mock);

        // Mock component repository - will be called twice (for question 2 and 3, both plugins)
        $component_info_mock = $this->createMock(\ilComponentInfo::class);
        $plugin_slot_mock_active = $this->createMock(\ilPluginSlotInfo::class);
        $plugin_slot_mock_inactive = $this->createMock(\ilPluginSlotInfo::class);
        $active_plugin_mock = $this->createMock(\ilPluginInfo::class);
        $inactive_plugin_mock = $this->createMock(\ilPluginInfo::class);

        $this->component_repository_mock->expects($this->exactly(2))
            ->method('getComponentByTypeAndName')
            ->with(\ilComponentInfo::TYPE_COMPONENT, 'TestQuestionPool')
            ->willReturn($component_info_mock);

        $component_info_mock->expects($this->exactly(2))
            ->method('getPluginSlotById')
            ->with('qst')
            ->willReturnOnConsecutiveCalls($plugin_slot_mock_active, $plugin_slot_mock_inactive);

        // Question 2: active plugin
        $plugin_slot_mock_active->expects($this->once())
            ->method('hasPluginName')
            ->with('assActivePlugin')
            ->willReturn(true);

        $plugin_slot_mock_active->expects($this->once())
            ->method('getPluginByName')
            ->with('assActivePlugin')
            ->willReturn($active_plugin_mock);

        $active_plugin_mock->expects($this->once())
            ->method('isActive')
            ->willReturn(true);

        // Question 3: inactive plugin
        $plugin_slot_mock_inactive->expects($this->once())
            ->method('hasPluginName')
            ->with('assInactivePlugin')
            ->willReturn(true);

        $plugin_slot_mock_inactive->expects($this->once())
            ->method('getPluginByName')
            ->with('assInactivePlugin')
            ->willReturn($inactive_plugin_mock);

        $inactive_plugin_mock->expects($this->once())
            ->method('isActive')
            ->willReturn(false);

        $results = $this->repository->getForQuestionIds($question_ids);

        // Should return only questions 1 and 2 (question 3 is filtered out)
        $this->assertCount(2, $results);
        $this->assertArrayHasKey(1, $results);
        $this->assertArrayHasKey(2, $results);
        $this->assertArrayNotHasKey(3, $results);
    }

    /**
     * Helper method to create a database record mock
     */
    private function createDbRecord(
        int $question_id,
        string $type_tag,
        int $is_plugin,
        string $plugin_name
    ): \stdClass {
        $db_record = new \stdClass();
        $db_record->question_id = $question_id;
        $db_record->original_id = null;
        $db_record->external_id = null;
        $db_record->obj_fi = 1;
        $db_record->oq_obj_fi = null;
        $db_record->question_type_fi = $question_id;
        $db_record->type_tag = $type_tag;
        $db_record->is_plugin = $is_plugin; // from SQL: qt.plugin as is_plugin
        $db_record->plugin_name = $plugin_name;
        $db_record->owner = 6;
        $db_record->title = 'Test Question ' . $question_id;
        $db_record->description = '';
        $db_record->question_text = 'Test';
        $db_record->points = 1.0;
        $db_record->nr_of_tries = 0;
        $db_record->lifecycle = 'draft';
        $db_record->author = 'Test Author';
        $db_record->tstamp = time();
        $db_record->created = time();
        $db_record->complete = 1;
        $db_record->add_cont_edit_mode = 0;

        return $db_record;
    }
}
