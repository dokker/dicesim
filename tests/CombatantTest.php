<?php
namespace diceSim\Tests;

use diceSim\Combatant;
use PHPUnit\Framework\TestCase;

class CombatantTest extends TestCase
{
	private function combatant(array $overrides = [])
	{
		return new Combatant(array_merge([
			'id' => 1, 'name' => 'Teszt', 'init' => 10, 'att' => 45,
			'def' => 50, 'hp' => 12, 'armor' => 0, 'x2' => 2,
		], $overrides));
	}

	public function testInjureLowersHpAndNeverGoesBelowZero()
	{
		$c = $this->combatant(['hp' => 5]);
		$this->assertSame(3, $c->injure(3));
		$this->assertSame(2, $c->getHP());
		$this->assertSame(2, $c->injure(10));
		$this->assertSame(0, $c->getHP());
		$this->assertTrue($c->died());
	}

	public function testNegativeInjuryDoesNotHeal()
	{
		$c = $this->combatant(['hp' => 5]);
		$this->assertSame(0, $c->injure(-3));
		$this->assertSame(5, $c->getHP());
	}

	public function testLooseTurnRemovesAnActionOnlyWhenWounded()
	{
		$c = $this->combatant(['x2' => 2]);
		$c->resetActions();
		$c->injure(0, true);
		$this->assertSame(2, $c->getActions());
		$c->injure(3, true);
		$this->assertSame(1, $c->getActions());
		$c->injure(3, false);
		$this->assertSame(1, $c->getActions());
	}

	public function testInjureDefReturnsTheActualLoss()
	{
		$c = $this->combatant(['def' => 5]);
		$this->assertSame(5, $c->injureDef(10));
		$this->assertSame(0, $c->getDef());
		$this->assertSame(0, $c->injureDef(3));
	}

	public function testPassiveDefIsAPercentOfDef()
	{
		$this->assertSame(20, $this->combatant(['def' => 50])->getPassiveDef(40));
		$this->assertSame(3, $this->combatant(['def' => 5])->getPassiveDef(50)); // 2.5 rounds up
	}

	public function testInjureDefLowersPassiveDefWhenInUse()
	{
		$c = $this->combatant(['def' => 50]);
		$c->getPassiveDef(40);
		$this->assertSame(5, $c->injureDef(5));
		$this->assertSame(15, $c->getPassiveDef(40));
		$this->assertSame(50, $c->getDef());
	}

	public function testPassiveDefStaysAtZero()
	{
		$c = $this->combatant(['def' => 5]);
		$this->assertSame(2, $c->getPassiveDef(40));
		$c->injureDef(5);
		$this->assertSame(0, $c->getPassiveDef(40));
	}

	public function testResetRestoresHpDefAndPassiveDef()
	{
		$c = $this->combatant(['def' => 50, 'hp' => 12]);
		$c->getPassiveDef(40);
		$c->injureDef(10);
		$c->injure(5);
		$c->reset();
		$this->assertSame(12, $c->getHP());
		$this->assertSame(50, $c->getDef());
		$this->assertSame(20, $c->getPassiveDef(40));
	}

	public function testAttackUsesAnActionAndComparesWithAtt()
	{
		$c = $this->combatant(['att' => 45, 'x2' => 2]);
		$c->resetActions();
		$this->assertTrue($c->attack(45));
		$this->assertFalse($c->attack(46));
		$this->assertFalse($c->hasAction());
		$this->assertFalse($c->attack(1), 'No attack without actions');
	}

	public function testMasterHitUsesTheTensOfAtt()
	{
		$c = $this->combatant(['att' => 45]);
		$this->assertTrue($c->getMasterHit(4, true));
		$this->assertFalse($c->getMasterHit(5, true));
		$this->assertFalse($c->getMasterHit(4, false));
	}

	public function testDefenseComparesWithDef()
	{
		$c = $this->combatant(['def' => 50]);
		$this->assertTrue($c->defense(50));
		$this->assertFalse($c->defense(51));
	}
}
