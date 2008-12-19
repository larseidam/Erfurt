<?php
require_once 'test_base.php';
require_once 'Erfurt/Versioning.php';

require_once 'Erfurt/Versioning/StoreStub.php';
require_once 'Erfurt/Versioning/AuthStub.php';

/**
 * Test class for Erfurt_Versioning.
 * Generated by PHPUnit on 2008-12-18 at 21:54:10.
 */
class Erfurt_VersioningTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Erfurt_Versioning
     * @access protected
     */
    protected $_object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        $this->_object    = new Erfurt_Versioning();
        $this->_storeStub = new Erfurt_Versioning_StoreStub(); 
        $this->_authStub  = new Erfurt_Versioning_AuthStub();
        
    }
    
    protected function _getMockedVersioning()
    {
        $versioning = $this->getMock('Erfurt_Versioning',
            array('_getStore', '_getAuth')
        );
        
        $versioning->expects($this->any())
                   ->method('_getStore')
                   ->will($this->returnValue($this->_storeStub));
        
        $versioning->expects($this->any())
                   ->method('_getAuth')
                   ->will($this->returnValue($this->_authStub));
        
        return $versioning;
    }
    
    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
    }

    public function testIsVersioningEnabledByDefault() 
    {
        $this->assertTrue($this->_object->isVersioningEnabled());
    }
    
    public function testIsVersioningEnabledAfterDisabled()
    {
        $this->_object->enableVersioning(false);
        $this->assertFalse($this->_object->isVersioningEnabled());
    }
    
    public function testIsVersioningEnabledAfterEnabledWithNoParam() 
    {
        $this->_object->enableVersioning();
        $this->assertTrue($this->_object->isVersioningEnabled());
    }
    
    public function testIsVersioningEnabledAfterEnabledWithParamTrue()
    {
        $this->_object->enableVersioning(true);
        $this->assertTrue($this->_object->isVersioningEnabled());
    }
    
    public function testIsActionStartedByDefault()
    {
        $this->assertFalse($this->_object->isActionStarted());
    }
    
    public function testIsActionStartedAfterStartAction()
    {
        $this->_object->startAction(Erfurt_Versioning::STATEMENT_ADDED);
        
        $this->assertTrue($this->_object->isActionStarted());
    }
    
    public function testIsActionStartedAfterStartAndEndAction()
    {
        $this->_object->startAction(Erfurt_Versioning::STATEMENT_REMOVED);
        $this->_object->endAction();
        
        $this->assertFalse($this->_object->isActionStarted());
    }
    
    public function testEndActionWithoutStartAction()
    {
        try {
            $this->_object->endAction();
            
            // Should not happen if exception is thrown as expected.
            $this->fail();
        } catch (Exception $e) {
            // If we are here everything went right... so we do nothing.
        }
    }
    
    public function testStartActionWhileActionIsRunning()
    {
        try {
            $this->_object->startAction(Erfurt_Versioning::STATEMENT_ADDED);
            
            // The following should fail.
            $this->_object->startAction(Erfurt_Versioning::STATEMENT_REMOVED);
            $this->fail();
        } catch (Exception $e) {
            // If we are here everything went right... so we do nothing.
        }
    }
    
    public function testGetLastModifiedForResource() 
    {    
        // We need a mocked versioning object here.
        $this->_object = $this->_getMockedVersioning();
        
        $result = $this->_object->getLastModifiedForResource('http://example.org/resource1', 'http://example.org/');
        
        // Result should contain the following keys.
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('tstamp', $result);
        $this->assertArrayHasKey('action_type', $result);
    }

    public function testGetHistoryForGraph() 
    {
        // We need a mocked versioning object here.
        $this->_object = $this->_getMockedVersioning();
        
        $result = $this->_object->getHistoryForGraph('http://example.org/');
        
        // Result should contain maximum n elements, where n is the limit set for the object.
        $this->assertLessThanOrEqual($this->_object->getLimit(), count($result));
        
        // Each result row should contain the following keys.
        foreach ($result as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('user', $row);
            $this->assertArrayHasKey('resource', $row);
            $this->assertArrayHasKey('tstamp', $row);
            $this->assertArrayHasKey('action_type', $row);
        }
    }

    public function testGetHistoryForResource() 
    {
        // We need a mocked versioning object here.
        $this->_object = $this->_getMockedVersioning();
        
        $result = $this->_object->getHistoryForResource('http://example.org/resource1', 'http://example.org/');
    
        // Result should contain maximum n elements, where n is the limit set for the object.
        $this->assertLessThanOrEqual($this->_object->getLimit(), count($result));
    
        // Each result row should contain the following keys.
        foreach ($result as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('user', $row);
            $this->assertArrayHasKey('tstamp', $row);
            $this->assertArrayHasKey('action_type', $row);
        }
    }

    public function testGetHistoryForUser() 
    {
        // We need a mocked versioning object here.
        $this->_object = $this->_getMockedVersioning();
        
        $result = $this->_object->getHistoryForUser('http://example.org/user1/');
    
        // Result should contain maximum n elements, where n is the limit set for the object.
        $this->assertLessThanOrEqual($this->_object->getLimit(), count($result));
    
        // Each result row should contain the following keys.
        foreach ($result as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('resource', $row);
            $this->assertArrayHasKey('tstamp', $row);
            $this->assertArrayHasKey('action_type', $row);
        }
    }

    public function testSetAndGetLimit() 
    {
        // By default the limit value should be 10.
        $this->assertEquals(10, $this->_object->getLimit());
        
        $this->_object->setLimit(100);
        $this->assertEquals(100, $this->_object->getLimit());
        
        // Try to set an invalid value
        try {
            $this->_object->setLimit(0);
            
            $this->fail();
        } catch (Exception $e) {
            // If we are here everything is fine.
        }
    }

    public function testOnAddStatement() 
    {
        // We need a mocked versioning object here.
        $this->_object = $this->_getMockedVersioning();
        
        require_once 'Erfurt/Event.php';
        $event = new Erfurt_Event('onAddStatement');
        $event->graphUri = 'http://example.org/';
        $event->statement = array('http://example.org/resource1/' => array(
            'http://example.org/property1/' => array(
                    array(
                        'type'  => 'literal',
                        'value' => 'Value1'
                    )
            )
        ));
        
        try {
            $this->_object->onAddStatement($event);
        } catch (Exception $e) {
            $this->fail();
        }
    }

    public function testOnAddMultipleStatements() 
    {
        // We need a mocked versioning object here.
        $this->_object = $this->_getMockedVersioning();
        
        require_once 'Erfurt/Event.php';
        $event = new Erfurt_Event('onAddMultipleStatements');
        $event->graphUri = 'http://example.org/';
        $event->statements = array('http://example.org/resource1/' => array(
            'http://example.org/property1/' => array(
                    array(
                        'type'  => 'literal',
                        'value' => 'Value1'
                    )
            )
        ),
        'http://example.org/resource2/' => array(
            'http://example.org/property2/' => array(
                    array(
                        'type'  => 'uri',
                        'value' => 'http://example.org/object/'
                    )
            )
        ));
        
        try {
            $this->_object->onAddMultipleStatements($event);
        } catch (Exception $e) {
            $this->fail();
        }
    }

    public function testOnDeleteMatchingStatements() 
    {
        // We need a mocked versioning object here.
        $this->_object = $this->_getMockedVersioning();
        
        require_once 'Erfurt/Event.php';
        $event = new Erfurt_Event('onDeleteMatchingStatements');
        $event->graphUri = 'http://example.org/';
        $event->resource = 'http://example.org/resource1/';
        
        // First try method without a payload (happens on really large sets of matching statements).
        try {
            $this->_object->onDeleteMatchingStatements($event);
        } catch (Exception $e) {
            $this->fail();
        }
        
        unset($event->resource);
        $event->statements = array('http://example.org/resource1/' => array(
            'http://example.org/property1/' => array(
                    array(
                        'type'  => 'literal',
                        'value' => 'Value1'
                    )
            )
        ),
        'http://example.org/resource2/' => array(
            'http://example.org/property2/' => array(
                    array(
                        'type'  => 'uri',
                        'value' => 'http://example.org/object/'
                    )
            )
        ));
        
        // Not try the same with a payload.
        try {
            $this->_object->onDeleteMatchingStatements($event);
        } catch (Exception $e) {
            $this->fail();
        }
    }

    public function testOnDeleteMultipleStatements()
    {
        // We need a mocked versioning object here.
        $this->_object = $this->_getMockedVersioning();
        
        require_once 'Erfurt/Event.php';
        $event = new Erfurt_Event('onDeleteMultipleStatements');
        $event->graphUri = 'http://example.org/';
        
        $event->statements = array('http://example.org/resource1/' => array(
            'http://example.org/property1/' => array(
                    array(
                        'type'  => 'literal',
                        'value' => 'Value1'
                    )
            )
        ),
        'http://example.org/resource2/' => array(
            'http://example.org/property2/' => array(
                    array(
                        'type'  => 'uri',
                        'value' => 'http://example.org/object/'
                    )
            )
        ));
        
        try {
            $this->_object->onDeleteMultipleStatements($event);
        } catch (Exception $e) {
            $this->fail();
        }
    }

    public function testRollbackAction() 
    {
        // We need a mocked versioning object here.
        $this->_object = $this->_getMockedVersioning();
        
        // First we try to rollback an existing action with stored payload. This should work.
        $this->_object->rollbackAction(1); // Add action
        $this->_object->rollbackAction(2); // Delete action
        
        // Now we try the cases that should not work.
        try {
            $this->_object->rollbackAction(3); // No payload for given payload id.
            
            $this->fail();
        } catch (Exception $e) {
            // Everything went fine...
        }
        
        try {
            $this->_object->rollbackAction(4); // Payload id is null.
            
            $this->fail();
        } catch (Exception $e) {
            // Everything went fine...
        }
        
        try {
            $this->_object->rollbackAction(5); // No action with that id.
            
            $this->fail();
        } catch (Exception $e) {
            // Everything went fine...
        }
    }
}
