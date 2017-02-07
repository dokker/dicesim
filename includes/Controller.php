<?php
namespace diceSim;

class Controller
{

	private $combatant1;
	private $combatant2;
	private $init;
	private $attack;
	private $round;
	private $active_combatant;

	public function __construct(array $combatants)
	{
		$this->combatant1 = new \diceSim\Combatant($combatants[0]);
		$this->combatant2 = new \diceSim\Combatant($combatants[1]);
		$this->dice = new \diceSim\Dice(['sides' => 10]);
	}

	public function getHighest(array $rolls)
	{
		if ($rolls[0] > $rolls[1]) {
			return $rolls[0];
		} else {
			return $rolls[1];
		}
	}

	public function getLowest(array $rolls)
	{
	}

	public function start()
	{
		$this->execRound();
		$this->stats();
	}

	private function execRound()
	{
		$this->round++;
		$this->combatant1->resetActions();
		$this->combatant2->resetActions();
		$this->active_combatant = $this->getInit();
		$this->activateCombatant();
	}

	private function activateCombatant()
	{
		// get active combatant
		switch ($this->active_combatant) {
			case 1:
				$combatant = $this->combatant1;
				$enemy = $this->combatant2;
				break;
			case 2:
				$combatant = $this->combatant2;
				$enemy = $this->combatant1;
				break;
		}

		// roll attack
		$this->attack = [
			$this->dice->roll(),
			$this->dice->roll()
		];

		$percentile_att = $this->normalizeD100($this->attack);
		if ($combatant->attack($percentile_att)) {
			if ($percentile_att > $enemy->getDef()) {
				$enemy->injure($this->getHighest($this->attack));
				$enemy->injureDef($this->getHighest($this->attack));
			} else {
				$enemy->injureDef($this->getHighest($this->attack));
			}
		}

		if ($this->combatant1->hasAction || $this->combatant2->hasAction() || $enemy->died()) {
			$this->changeActive();
			$this->activateCombatant();
		}
	}

	private function changeActive()
	{
		if ($this->active_combatant == 1) {
			$this->active_combatant = 2;
		} else {
			$this->active_combatant = 1;
		}
	}

	private function getInit()
	{
		$init1 = $this->combatant1->getInit();
		do {
			$roll = $this->dice->roll();
			$init1 += $roll;
		} while($roll == 10);

		$init2 = $this->combatant2->getInit();
		do {
			$roll = $this->dice->roll();
			$init2 += $roll;
		} while($roll == 10);

		// GLITCH: Doesn't handle the equation in init
		if ($init1 > $init2) {
			return 1;
		} else {
			return 2;
		}
	}

	private function normalizeD100($attack)
	{
		if ($this->attack[0] == 10) {
			$roll = '0';
		} else {
			$roll = (string)$attack[0];
		}
		if ($this->attack[1] == 10) {
			$roll .= '0';
		} else {
			$roll .= (string)$attack[1];
		}
		return (int)$roll;
	}

	private function stats()
	{
	}
}