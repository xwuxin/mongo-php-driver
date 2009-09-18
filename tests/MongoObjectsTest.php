<?php
require_once 'PHPUnit/Framework.php';

/**
 * Test class for Mongo.
 * Generated by PHPUnit on 2009-04-09 at 18:09:02.
 */
class MongoObjectsTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var    Mongo
     * @access protected
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    public function setUp() {
      ini_set('mongo.objects', 1);
      $this->object = $this->sharedFixture->selectCollection("phpunit", "c");
      $this->object->drop();
    }

    public function tearDown() {
      ini_set('mongo.objects', 0);
    }
    
    public function testObjects() {
      $c = $this->sharedFixture->selectCollection('phpunit', 'objs1');
      $c->drop();

      $obj = (object)array('x' => 1);
      $x = array('obj' => $obj);

      $c->insert($x);
      $x = $c->findOne();

      $this->assertTrue(is_array($x['obj']));
      $this->assertEquals(1, $x['obj']['x']);
    }

    public function testNested() {
      $c = $this->sharedFixture->selectCollection('phpunit', 'objs2');
      $c->drop();

      $obj2 = (object)array('x' => (object)array('foo' => (object)array()));
      $c->insert(array('obj' => $obj2));

      $x = $c->findOne();

      $this->assertTrue(is_array($x['obj']));
      $this->assertTrue(is_array($x['obj']['x']));
      $this->assertTrue(is_array($x['obj']['x']['foo']));
    }

    public function testClass() {
      $c = $this->sharedFixture->selectCollection('phpunit', 'objs3');
      $c->drop();

      $f = new Foo();
      $a = array('foo' => $f);
      $c->insert($a);

      $foo = $c->findOne();
      $this->assertTrue(is_array($foo['foo']));
      $this->assertEquals(1, $foo['foo']['x']);
      $this->assertEquals(2, $foo['foo']['y']);
      $this->assertEquals("hello", $foo['foo']['z']);
    }

    public function testMethods() {
      $c = $this->sharedFixture->selectCollection('phpunit', 'objs4');
      $c->drop();

      $f = new Foo();

      $c->insert($f);
      $f->x = 3;
      $c->save($f);
      $f->y = 7;
      $c->update(array('_id' => $f->_id), $f);
      $c->remove($f);
    }

    public function testDrop() {
        $ns = $this->object->db->selectCollection('system.namespaces');
        $obj = (object)array('x'=>1);
        $obj2 = (object)array('x'=>1);

        $this->object->insert($obj);
        $this->object->ensureIndex($obj2);

        $c = $ns->findOne((object)array('name' => 'phpunit.c'));
        $this->assertNotNull($c);

        $c = $ns->findOne(array('name' => 'phpunit.c'));
        $this->assertNotNull($c);

        $response = $this->object->drop();

        $c = $ns->findOne(array('name' => 'phpunit.c'));
        $this->assertEquals(null, $c);

        $c = $ns->findOne((object)array('name' => 'phpunit.c'));
        $this->assertEquals(null, $c);
    }

    public function testValidate() {
        $v = $this->object->validate();
        $this->assertEquals($v['ok'], 0);
        $this->assertEquals('ns not found', $v['errmsg']);
        
        $this->object->insert((object)array('a' => 'foo'));
        $v = $this->object->validate();
        $this->assertEquals($v['ok'], 1);
        $this->assertEquals($v['ns'], 'phpunit.c');
        $this->assertNotNull($v['result']);
    }

    public function testInsert() {
      $a = (object)array("n" => NULL,
                 "l" => 234234124,
                 "d" => 23.23451452,
                 "b" => true,
                 "a" => array("foo"=>"bar",
                              "n" => NULL,
                              "x" => new MongoId("49b6d9fb17330414a0c63102")),
                 "d2" => new MongoDate(1271079861),
                 "regex" => new MongoRegex("/xtz/g"),
                 "_id" => new MongoId("49b6d9fb17330414a0c63101"),
                 "string" => "string");
      
      $this->assertTrue($this->object->insert($a));
      $obj = $this->object->findOne();

      $this->assertEquals($obj['n'], null);
      $this->assertEquals($obj['l'], 234234124);
      $this->assertEquals($obj['d'], 23.23451452);
      $this->assertEquals($obj['b'], true);
      $this->assertEquals($obj['a']['foo'], 'bar');
      $this->assertEquals($obj['a']['n'], null);
      $this->assertNotNull($obj['a']['x']);
      $this->assertEquals($obj['d2']->sec, 1271079861);
      $this->assertEquals($obj['d2']->usec, 0);
      $this->assertEquals($obj['regex']->regex, 'xtz');
      $this->assertEquals($obj['regex']->flags, 'g');
      $this->assertNotNull($obj['_id']);
      $this->assertEquals($obj['string'], 'string');
    }

    public function testInsert2() {
      $this->assertTrue($this->object->insert((object)array(NULL)));
      $this->assertTrue($this->object->insert((object)array(NULL=>"1")));
      
      $this->assertEquals($this->object->count(), 2);
      $cursor = $this->object->find();

      $x = $cursor->getNext();
      $x = $cursor->getNext();
    }

    public function testInsertNonAssoc() {
        $nonassoc = (object)array("x" => array("x", "y", "z"));
        $this->object->insert($nonassoc);
        $x = $this->object->findOne();

        $this->assertEquals("x", $x['x'][0]);
        $this->assertEquals("y", $x['x'][1]);
        $this->assertEquals("z", $x['x'][2]);
        $this->assertEquals((string)$nonassoc->_id, (string)$x['_id']);
    }
    
    public function testBatchInsert() {
      $this->assertFalse($this->object->batchInsert(array()));
      $this->assertFalse($this->object->batchInsert(array(1,2,3)));
      $this->assertTrue($this->object->batchInsert(array('z'=>(object)array('foo'=>'bar'))));

      $a = array( (object)array( "x" => "y"), (object)array( "x"=> "z"), (object)array("x"=>"foo"));
      $this->object->batchInsert($a);
      $this->assertEquals(4, $this->object->count());

      $cursor = $this->object->find()->sort((object)array("x" => -1));
      $x = $cursor->getNext();
      $this->assertEquals('bar', $x['foo']);
      $x = $cursor->getNext();
      $this->assertEquals('z', $x['x']);
      $x = $cursor->getNext();
      $this->assertEquals('y', $x['x']);
      $x = $cursor->getNext();
      $this->assertEquals('foo', $x['x']);
    }

    public function testFind() {
        for ($i=0;$i<50;$i++) {
            $this->object->insert((object)array('x' => $i));
        }

        $c = $this->object->find();
        $this->assertEquals(iterator_count($c), 50);
        $c = $this->object->find(array());
        $this->assertEquals(iterator_count($c), 50);

        $this->object->insert((object)array("foo" => "bar",
                                            "a" => "b",
                                            "b" => "c"));

        $c = $this->object->find((object)array('foo' => 'bar'), (object)array('a'=>1, 'b'=>1));

        $this->assertTrue($c instanceof MongoCursor);
        $obj = $c->getNext();
        $this->assertEquals('b', $obj['a']);
        $this->assertEquals('c', $obj['b']);
        $this->assertEquals(false, array_key_exists('foo', $obj));
    }

    
    public function testFindOne() {
        $this->assertEquals(null, $this->object->findOne());
        $this->assertEquals(null, $this->object->findOne((object)array()));

        for ($i=0;$i<3;$i++) {
            $this->object->insert((object)array('x' => $i));
        }

        $obj = $this->object->findOne();
        $this->assertNotNull($obj);
        $this->assertEquals(0, $obj['x']);

        $obj = $this->object->findOne((object)array('x' => 1));
        $this->assertNotNull($obj);
        $this->assertEquals(1, $obj['x']);
    }

    public function testFindOneFields() {
        for ($i=0;$i<3;$i++) {
            $this->object->insert((object)array('x' => $i, 'y' => 4, 'z' => 6));
        }

        $obj = $this->object->findOne((object)array(), (object)array('y'=>1));
        $this->assertArrayHasKey('y', $obj, json_encode($obj));
        $this->assertArrayHasKey('_id', $obj, json_encode($obj));
        $this->assertArrayNotHasKey('x', $obj, json_encode($obj));
        $this->assertArrayNotHasKey('z', $obj, json_encode($obj));

        $obj = $this->object->findOne(array(), array('y'=>1, 'z'=>1));
        $this->assertArrayHasKey('y', $obj, json_encode($obj));
        $this->assertArrayHasKey('_id', $obj, json_encode($obj));
        $this->assertArrayNotHasKey('x', $obj, json_encode($obj));
        $this->assertArrayHasKey('z', $obj, json_encode($obj));
    }

    public function testUpdate() {
        $old = (object)array("foo"=>"bar", "x"=>"y");
        $new = (object)array("foo"=>"baz");
      
        $this->object->update((object)array("foo"=>"bar"), $old, true);
        $obj = $this->object->findOne();
        $this->assertEquals($obj['foo'], 'bar');      
        $this->assertEquals($obj['x'], 'y');      

        $this->object->update($old, $new);
        $obj = $this->object->findOne();
        $this->assertEquals($obj['foo'], 'baz');      
    }

    public function testRemove() {
        for($i=0;$i<15;$i++) {
            $this->object->insert((object)array("i"=>$i));
        }
        
        $this->assertEquals($this->object->count(), 15);
        $this->object->remove(array(), true);
        $this->assertEquals($this->object->count(), 14);

        $this->object->remove((object)array());
        $this->assertEquals($this->object->count(), 0);

        for($i=0;$i<15;$i++) {
            $this->object->insert((object)array("i"=>$i));
        }
        
        $this->assertEquals($this->object->count(), 15);
        $this->object->remove();      
        $this->assertEquals($this->object->count(), 0);
    }

    public function testEnsureIndex() {
        $this->object->ensureIndex('foo');

        $idx = $this->object->db->selectCollection('system.indexes');
        $index = $idx->findOne((object)array('name' => 'foo_1'));

        $this->assertNotNull($index);
        $this->assertEquals($index['key']['foo'], 1);
        $this->assertEquals($index['name'], 'foo_1');

        // get rid of indexes
        $this->object->drop();

        $this->object->ensureIndex((object)array('bar' => -1));
        $index = $idx->findOne((object)array('name' => 'bar_-1'));
        $this->assertNotNull($index);
        $this->assertEquals($index['key']['bar'], -1);
        $this->assertEquals($index['ns'], 'phpunit.c');
    }

    public function testEnsureUniqueIndex() {
      $unique = true;

      $this->object->ensureIndex((object)array('x'=>1), !$unique);
      $this->object->insert((object)array('x'=>0, 'z'=>1));
      $this->object->insert((object)array('x'=>0, 'z'=>2));
      $this->assertEquals($this->object->count(), 2);

      $this->object->ensureIndex((object)array('z'=>1), $unique);
      $this->object->insert((object)array('z'=>0));
      $this->object->insert((object)array('z'=>0));
      $err = $this->object->db->lastError();
      $this->assertEquals("E11000", substr($err['err'], 0, 6), json_encode($err));
    }

    public function testDeleteIndex() {
      $idx = $this->object->db->selectCollection('system.indexes');

      $this->object->ensureIndex('foo');
      $this->object->ensureIndex((object)array('foo' => -1));

      $num = iterator_count($idx->find((object)array('ns' => 'phpunit.c')));
      $this->assertEquals($num, 3);

      $this->object->deleteIndex(null);
      $num = iterator_count($idx->find((object)array('ns' => 'phpunit.c')));
      $this->assertEquals($num, 3);

      $this->object->deleteIndex((object)array('foo' => 1)); 
      $num = iterator_count($idx->find((object)array('ns' => 'phpunit.c')));
      $this->assertEquals($num, 2);

      $this->object->deleteIndex('foo');
      $num = iterator_count($idx->find((object)array('ns' => 'phpunit.c')));
      $this->assertEquals($num, 2);

      $this->object->deleteIndex((object)array('foo' => -1));
      $num = iterator_count($idx->find((object)array('ns' => 'phpunit.c')));
      $this->assertEquals($num, 1);
    }

    public function testDeleteIndexes() {
      $idx = $this->object->db->selectCollection('system.indexes');

      $this->object->ensureIndex((object)array('foo' => 1));
      $this->object->ensureIndex((object)array('foo' => -1));
      $this->object->ensureIndex((object)array('bar' => 1, 'baz' => -1));

      $num = iterator_count($idx->find((object)array('ns' => 'phpunit.c')));
      $this->assertEquals($num, 4);

      $this->object->deleteIndexes();
      $num = iterator_count($idx->find((object)array('ns' => 'phpunit.c')));
      $this->assertEquals($num, 1);
    }

    public function testGetIndexInfo() {
      $info = $this->object->getIndexInfo();
      $this->assertEquals(count($info), 0);

      $this->object->ensureIndex((object)array('foo' => 1));
      $this->object->ensureIndex((object)array('foo' => -1));
      $this->object->ensureIndex((object)array('bar' => 1, 'baz' => -1));

      $info = $this->object->getIndexInfo();
      $this->assertEquals(4, count($info), json_encode($info));
      $this->assertEquals($info[1]['key']['foo'], 1);
      $this->assertEquals($info[1]['name'], 'foo_1');
      $this->assertEquals($info[2]['key']['foo'], -1);
      $this->assertEquals($info[2]['name'], 'foo_-1');
      $this->assertEquals($info[3]['key']['bar'], 1);
      $this->assertEquals($info[3]['key']['baz'], -1);
      $this->assertEquals($info[3]['name'], 'bar_1_baz_-1');
    }
    
    public function testCount() {
      $this->assertEquals($this->object->count(), 0);

      $this->object->insert((object)array(6));

      $this->assertEquals($this->object->count(), 1);

      $this->assertEquals(0, $this->object->count((object)array('z'=>1)));
      $this->assertEquals(1, $this->object->count((object)array('0'=>6)));
      $this->assertEquals(1, $this->object->count((object)array(), (object)array('0'=>1)));
    }
    

    public function testSave() {
      $this->object->save((object)array('x' => 1));

      $a = $this->object->findOne();
      $id1 = $a['_id'];

      $a['x'] = 2;
      $this->object->save($a);
      $id2 = $a['_id'];

      $this->assertEquals($id1, $id2);
      $a['y'] = 3;
      $this->object->save($a);

      $this->assertEquals($this->object->count(), 1);

      $a = $this->object->findOne();
      $this->assertEquals($a['x'], 2);


    }

    public function testGetDBRef() {
        for($i=0;$i<50;$i++) {
            $this->object->insert((object)array('x' => rand()));
        }
        $obj = $this->object->findOne();

        $ref = $this->object->createDBRef($obj);
        $obj2 = $this->object->getDBRef($ref);

        $this->assertNotNull($obj2);
        $this->assertEquals($obj['x'], $obj2['x']);
    }

    public function testCreateDBRef() {
        $arr = (object)array('_id' => new MongoId());
        $ref = $this->object->createDBRef($arr);
        $this->assertNotNull($ref);
        $this->assertTrue(is_array($ref));

        $arr = (object)array('_id' => 1);
        $ref = $this->object->createDBRef($arr);
        $this->assertNotNull($ref);
        $this->assertTrue(is_array($ref));

        $ref = $this->object->createDBRef(new MongoId());
        $this->assertNotNull($ref);
        $this->assertTrue(is_array($ref));
    }


    public function testToIndexString() {
        $this->assertEquals(TestToIndexString::test((object)array('x' => 1)), 'x_1');
        $this->assertEquals(TestToIndexString::test((object)array('x' => -1)), 'x_-1');
        $this->assertEquals(TestToIndexString::test((object)array('x' => 1, 'y' => -1)), 'x_1_y_-1');
    }
}

class Foo {
  public function __construct() {
    $this->x = 1;
    $this->y = 2;
    $this->z = "hello";
  }
}

?>
