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

		$loader = new \Twig_Loader_Filesystem('templates');
		$this->twig = new \Twig_Environment($loader);
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
				$attacker = $this->combatant1;
				$enemy = $this->combatant2;
				break;
			case 2:
				$attacker = $this->combatant2;
				$enemy = $this->combatant1;
				break;
		}

		// roll attack
		$this->attack = [
			$this->dice->roll(),
			$this->dice->roll()
		];

		// Handle attack
		$success = false;
		$percentile_att = $this->normalizeD100($this->attack);
		if ($attacker->attack($percentile_att)) {
			if ($percentile_att > $enemy->getDef()) {
				$hpdamage = $enemy->injure($this->getHighest($this->attack));
				$defdamage = $enemy->injureDef($this->getHighest($this->attack));
			} else {
				$defdamage = $enemy->injureDef($this->getHighest($this->attack));
			}
			$success = true;
		}

		// Structure attack data
		$data = [
			'success' => $success,
			'attacker' => [
				'name' => $attacker->getName(),
				'roll' => sprintf("%02d", $percentile_att),
			],
			'enemy' => [
				'name' => $enemy->getName(),
				'hpdamage' => sprintf("%01d", $hpdamage),
				'defdamage' => sprintf("%01d", $defdamage),
			],
		];
		echo $this->twig->render('attack.html', $data);

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