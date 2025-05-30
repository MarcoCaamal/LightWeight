<?php

namespace LightWeight\Tests\Database\ORM;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use LightWeight\Database\Contracts\DatabaseDriverContract;
use LightWeight\Database\ORM\Model;
use LightWeight\Tests\Database\RefreshDatabase;

class MockModel extends Model
{
}
class MockModelFillable extends Model
{
    protected ?string $table = "mock_models";
    protected array $fillable = ['test', 'name'];
}

class ModelTest extends TestCase
{
    use RefreshDatabase;
    protected ?DatabaseDriverContract $driver = null;
    private function createTestTable($name, $columns, $withTimestamps = true)
    {
        $sql = "CREATE TABLE $name (id INT AUTO_INCREMENT PRIMARY KEY, "
            . implode(", ", array_map(fn ($c) => "$c VARCHAR(256)", $columns));
        if ($withTimestamps) {
            $sql .= ", created_at DATETIME, updated_at DATETIME NULL";
        }
        $sql .= ")";
        $this->driver->statement($sql);
    }
    public function testSaveBasicModelWithAttributes()
    {
        $this->createTestTable("mock_models", ["test", "name"]);
        $model = new MockModel();
        $model->test = "Test";
        $model->name = "Name";
        $model->save();
        $rows = $this->driver->statement("SELECT * FROM mock_models");
        $expected = [
            "id" => 1,
            "name" => "Name",
            "test" => "Test",
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => null,
        ];
        $this->assertEquals($expected, $rows[0]);
        $this->assertEquals(1, count($rows));
    }
    #[Depends('testSaveBasicModelWithAttributes')]
    public function testFindModel()
    {
        $this->createTestTable("mock_models", ["test", "name"]);

        $expected = [
            [
                "id" => 1,
                "test" => "Test",
                "name" => "Name",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => null,
            ],
            [
                "id" => 2,
                "test" => "Foo",
                "name" => "Bar",
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => null,
            ],
        ];
        foreach ($expected as $columns) {
            $model = new MockModel();
            $model->test = $columns["test"];
            $model->name = $columns["name"];
            $model->save();
        }
        foreach ($expected as $columns) {
            $model = new MockModel();
            foreach ($columns as $column => $value) {
                $model->{$column} = $value;
            }
            $actual = MockModel::find($columns["id"]);
            $this->assertEquals($model, $actual);
        }
        $this->assertNull(MockModel::find(5));
    }
    #[Depends('testSaveBasicModelWithAttributes')]
    public function testCreateModelWithNoFillableAttributesThrowsError()
    {
        $this->expectException(\Error::class);
        MockModel::create(["test" => "test"]);
    }
    #[Depends('testCreateModelWithNoFillableAttributesThrowsError')]
    public function testCreateModel()
    {
        $this->createTestTable("mock_models", ["test", "name"]);
        $model = MockModelFillable::create(["test" => "Test", "name" => "Name"]);
        $this->assertEquals(1, count($this->driver->statement("SELECT * FROM mock_models")));
        $this->assertEquals("Name", $model->name);
        $this->assertEquals("Test", $model->test);
    }
    #[Depends('testCreateModel')]
    public function testAll()
    {
        $this->createTestTable("mock_models", ["test", "name"]);
        MockModelFillable::create(["test" => "Test", "name" => "Name"]);
        MockModelFillable::create(["test" => "Test", "name" => "Name"]);
        MockModelFillable::create(["test" => "Test", "name" => "Name"]);
        $models = MockModelFillable::all();
        $this->assertEquals(3, count($models));
        foreach ($models as $model) {
            $this->assertEquals("Test", $model->test);
            $this->assertEquals("Name", $model->name);
        }
    }
    #[Depends('testCreateModel')]
    public function testFirstWhereAndAllWhere()
    {
        $this->createTestTable("mock_models", ["test", "name"]);
        MockModelFillable::create(["test" => "First", "name" => "Name"]);
        MockModelFillable::create(["test" => "Where", "name" => "Foo"]);
        MockModelFillable::create(["test" => "Where", "name" => "Foo"]);
        $firstWhere = MockModelFillable::where("test", "=", 'First')
            ->first();
        $allWhere = MockModelFillable::where('test', '=', 'Where')->get();

        // Tests of First Where
        $this->assertInstanceOf(MockModelFillable::class, $firstWhere);
        $this->assertIsObject($firstWhere);
        $this->assertEquals($firstWhere->test, 'First');

        //Tests of All Where
        $this->assertIsArray($allWhere);
        $this->assertCount(2, $allWhere);
        $this->assertContainsOnlyInstancesOf(MockModelFillable::class, $allWhere);
    }
    #[Depends('testCreateModel')]
    #[Depends('testFindModel')]
    public function testUpdate()
    {
        $this->createTestTable("mock_models", ["test", "name"]);
        MockModelFillable::create(["test" => "test", "name" => "name"]);
        // The create method doesn't return the ID of the model.
        // Check https://www.php.net/manual/es/pdo.lastinsertid.php to implement that feature.
        $model = MockModelFillable::find(1);
        $model->test = "UPDATED test";
        $model->name = "UPDATED name";
        $model->update();
        $rows = $this->driver->statement("SELECT test, name FROM mock_models");
        $this->assertEquals(1, count($rows));
        $this->assertEquals(["test" => "UPDATED test", "name" => "UPDATED name"], $rows[0]);
    }
    #[Depends('testCreateModel')]
    #[Depends('testFindModel')]
    public function test_delete()
    {
        $this->createTestTable("mock_models", ["test", "name"]);
        MockModelFillable::create(["test" => "test", "name" => "name"]);
        $model = MockModelFillable::find(1);
        $model->delete();
        $rows = $this->driver->statement("SELECT test, name FROM mock_models");
        $this->assertEquals(0, count($rows));
    }
}
