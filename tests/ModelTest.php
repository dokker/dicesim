<?php
namespace diceSim\Tests;

use diceSim\Model;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
	private $model;

	protected function setUp(): void
	{
		$this->model = new Model();
	}

	public function testValidInputIsConvertedToInt()
	{
		$c = $this->model->sanitize_combatant([
			'name' => ' Mester ', 'init' => '12', 'att' => '55', 'def' => '30',
			'hp' => '15', 'armor' => '2', 'x2' => '2',
		], 1);

		$this->assertSame([
			'id' => 1, 'name' => 'Mester', 'init' => 12, 'att' => 55, 'def' => 30,
			'hp' => 15, 'armor' => 2, 'x2' => 2,
		], $c);
	}

	public function testEmptyFieldsGetFallbackValues()
	{
		$c = $this->model->sanitize_combatant(['name' => '', 'att' => '', 'def' => ''], 2);

		$this->assertSame('Harcos 2', $c['name']);
		$this->assertSame(1, $c['init']);
		$this->assertSame(10, $c['att']);
		$this->assertSame(0, $c['def']);
		$this->assertSame(1, $c['hp']);
		$this->assertSame(0, $c['armor']);
		$this->assertSame(1, $c['x2']);
	}

	public function testNonNumericAndMissingInput()
	{
		$this->assertSame(10, $this->model->sanitize_combatant(['att' => 'abc'], 1)['att']);
		$this->assertSame('Harcos 1', $this->model->sanitize_combatant(null, 1)['name']);
	}

	public function testValuesAreClampedToValidRange()
	{
		$c = $this->model->sanitize_combatant([
			'att' => '150', 'def' => '-5', 'hp' => '0', 'armor' => '-1', 'x2' => '0',
		], 1);

		$this->assertSame(100, $c['att']);
		$this->assertSame(0, $c['def']);
		$this->assertSame(1, $c['hp']);
		$this->assertSame(0, $c['armor']);
		$this->assertSame(1, $c['x2']);
	}

	public function testZeroIsKeptWhereItIsValid()
	{
		$c = $this->model->sanitize_combatant(['def' => '0', 'att' => '0'], 1);
		$this->assertSame(0, $c['def']);
		$this->assertSame(0, $c['att']);
	}

	public function testOptions()
	{
		$o = $this->model->sanitize_options([
			'battles' => '250', 'system' => '6', 'passive_percent' => '60', 'mod_master_hit' => 'on',
		]);

		$this->assertSame([
			'battles' => 250, 'system' => 6, 'passive_percent' => 60,
			'mod_loose_turn' => false, 'mod_master_hit' => true,
		], $o);
	}

	public function testInvalidOptionsFallBackOrAreClamped()
	{
		$o = $this->model->sanitize_options(['battles' => '0', 'system' => '9', 'passive_percent' => '']);
		$this->assertSame(1, $o['battles']);
		$this->assertSame(1, $o['system']);
		$this->assertSame(40, $o['passive_percent']);

		$o = $this->model->sanitize_options(['battles' => '999999', 'passive_percent' => '300']);
		$this->assertSame(Model::MAX_BATTLES, $o['battles']);
		$this->assertSame(100, $o['passive_percent']);
	}

	public function testDefaultFormData()
	{
		$fd = $this->model->default_formdata();
		$this->assertSame('Mester', $fd['c1']['name']);
		$this->assertSame('Tanítvány', $fd['c2']['name']);
		$this->assertSame(100, $fd['battles']);
		$this->assertSame(1, $fd['system']);
	}
}
