<?php
namespace diceSim;

class Controller
{

	// private $combatant1;
	// private $combatant2;
	private $combatants;
	private $init;
	private $attack;
	private $def;
	private $round = 1;
	private $rounds_total = 0;
	private $battle = 1;
	private $active_combatant;
	private $battle_wins = [];

	private $battle_data;
	private $actions_data;
	private $test_data;

	public function __construct()
	{
		$this->dice = new \diceSim\Dice(['sides' => 10]);

		$loader = new \Twig_Loader_Filesystem('templates');
		$this->twig = new \Twig_Environment($loader, ['debug' => true]);
		$this->twig->addExtension(new \Twig_Extension_Debug());

		$this->handle_requests();
	}

	private function handle_requests()
	{
		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			$model = new \diceSim\Model();

			$c1 = filter_input(INPUT_POST, c1, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY);
			$c2 = filter_input(INPUT_POST, c2, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY);
			$c1 = $model->sanitize_combatant($c1);
			$c2 = $model->sanitize_combatant($c2);

			$this->combatants[] = new \diceSim\Combatant($c1);
			$this->combatants[] = new \diceSim\Combatant($c2);

			$battles = filter_input(INPUT_POST, 'battles', FILTER_VALIDATE_INT, 1);

			$this->formdata['c1'] = $c1;
			$this->formdata['c2'] = $c2;
			$this->formdata['battles'] = $battles;
			$this->formdata['system'] = filter_input(INPUT_POST, 'system', FILTER_VALIDATE_INT, 1);

			$this->formdata['mod_loose_turn'] = !empty($_POST['mod_loose_turn']);
			$this->formdata['mod_master_hit'] = !empty($_POST['mod_master_hit']);

			$this->startTest($battles);
		} else {
			echo $this->twig->render('form.html.twig', ['test' => false]);
		}
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
		if ($rolls[0] > $rolls[1]) {
			return $rolls[1];
		} else {
			return $rolls[0];
		}
	}

	private function startBattle()
	{
		$this->battleReset();
		$this->execRound();
		$this->battle_data['winner'] = [
			'id' => $this->combatants[$this->active_combatant]->getID(),
			'hp' => $this->combatants[$this->active_combatant]->getHP(),
		];

		// negate by 1 because execRound has already incremented it
		$this->battle_data['rounds_num'] = $this->round - 1;
		$this->battleStats();
	}

	public function startTest($repeat)
	{
		for ($battle = 1; $battle <= $repeat; $battle++) {
			$this->startBattle();
			$this->test_data['battles'][] = $this->battle_data;
			$this->battle++;
		}
		$this->testStats();
		// echo $this->twig->render('base.html.twig', ['rounds' => $this->battle_data]);
		echo $this->twig->render('form.html.twig', ['test' => $this->test_data, 'fd' => $this->formdata]);
	}

	private function execRound()
	{
		foreach ($this->combatants as $combatant) {
			$combatant->resetActions();
		}
		$this->active_combatant = $this->getInit();
		$this->activateCombatant();

		$round_stats = [
			'c1_def' => $this->combatants[0]->getDef(),
			'c1_hp'	=>	$this->combatants[0]->getHP(),
			'c1_name' => $this->combatants[0]->getName(),
			'c2_def' => $this->combatants[1]->getDef(),
			'c2_hp'	=>	$this->combatants[1]->getHP(),
			'c2_name' => $this->combatants[1]->getName(),
		];

		$data = [
			'num' => sprintf("%02d", $this->round),
			'actions' => $this->actions_data,
			'stats'	=>	$round_stats,
		];

		$this->actions_data = [];
		$this->battle_data['rounds'][] = $data;

		$this->round++;

		if (!$this->combatants[0]->died() && !$this->combatants[1]->died()) {
			$this->execRound();
		}
	}

	private function activateCombatant()
	{
		// get active combatant
		switch ($this->active_combatant) {
			case 0:
				$attacker = $this->combatants[0];
				$enemy = $this->combatants[1];
				break;
			case 1:
				$attacker = $this->combatants[1];
				$enemy = $this->combatants[0];
				break;
		}

		$data = $this->handleAttack($this->formdata['system'], $attacker, $enemy);

		$this->actions_data[] = $data;

		if (($this->combatants[0]->hasAction() || $this->combatants[1]->hasAction()) && !$enemy->died()) {
			$this->changeActive();
			$this->activateCombatant();
		}
	}

	private function handleAttack($system, $attacker, $enemy)
	{
		$looseturn = $this->formdata['mod_loose_turn'];
		$masterhit = $this->formdata['mod_master_hit'];

		switch ($system) {
			case 1: // CX
				// roll attack
				$this->attack = [
					$this->dice->roll(),
					$this->dice->roll()
				];

				// Handle attack
				$success = false;
				$percentile_att = $this->normalizeD100($this->attack);
				if ($attacker->attack($percentile_att)) {
					$damage = $this->getHighest($this->attack);

					if ($percentile_att > $enemy->getDef() || $attacker->getMasterHit($percentile_att, $masterhit)) {
						$hpdamage = ($damage - $enemy->getArmor()) > 0 ? ($damage - $enemy->getArmor()) : 0;
						$hpdamage = $enemy->injure($hpdamage, $looseturn);
						$defdamage = $enemy->injureDef($hpdamage);
						/* Doubles def loss at HP injury
						$defdamage = $enemy->injureDef($hpdamage * 2);
						*/
					} else {
						$defdamage = ($damage - $enemy->getArmor()) > 0 ? ($damage - $enemy->getArmor()) : 0;
						$defdamage = $enemy->injureDef($defdamage);
						if ($defdamage < 0) {
							$hpdamage = $enemy->injure(abs($defdamage), $looseturn);
						}
					}
					$success = true;
				}
			break;
			case 2: //CX2
				// roll attack
				$this->attack = [
					$this->dice->roll(),
					$this->dice->roll()
				];

				// Handle attack
				$success = false;
				$percentile_att = $this->normalizeD100($this->attack);
				if ($attacker->attack($percentile_att)) {
					$damage = $this->getHighest($this->attack);

					if ($percentile_att > $enemy->getDef() || $attacker->getMasterHit($percentile_att, $masterhit)) {
						$hpdamage = ($damage - $enemy->getArmor()) > 0 ? ($damage - $enemy->getArmor()) : 0;
						$hpdamage = $enemy->injure($hpdamage, $looseturn);
						$defdamage = $enemy->injureDef($hpdamage);
						/* Doubles def loss at HP injury
						$defdamage = $enemy->injureDef($hpdamage * 2);
						*/
					} else {
						$defdamage = ($damage - $enemy->getArmor()) > 0 ? ($damage - $enemy->getArmor()) : 0;
						$defdamage = $enemy->injureDef($defdamage);
						if ($defdamage < 0) {
							$hpdamage = $enemy->injure(abs($defdamage), $looseturn);
						}
					}
					$success = true;
				/* CX2 mod */
				} elseif($percentile_att > $enemy->getdef()) {
					$damage = $this->getlowest($this->attack);
					$defdamage = $enemy->injuredef($damage);
					$success = true;
				}
			break;
			case 4: // Passive Def from Damage
				// roll attack
				$this->attack = [
					$this->dice->roll(),
					$this->dice->roll()
				];

				// Handle attack
				$success = false;
				$percentile_att = $this->normalizeD100($this->attack);
				if ($attacker->attack($percentile_att)) {
					$damage = $this->getHighest($this->attack);

						$hpdamage = ($damage - $enemy->getArmor()) > 0 ? ($damage - $enemy->getArmor() - floor($enemy->getDef() / 10)) : 0;
						$hpdamage = $enemy->injure($hpdamage, $looseturn);
						$defdamage = 0;
					$success = true;
				}
			break;
			case 5: // Passive Def
				// roll attack
				$this->attack = [
					$this->dice->roll(),
					$this->dice->roll()
				];

				// Handle attack
				$success = false;
				$percentile_att = $this->normalizeD100($this->attack);
				if ($attacker->attack($percentile_att)) {
					$damage = $this->getHighest($this->attack);

					if ($percentile_att > $enemy->getPassiveDef() || $attacker->getMasterHit($percentile_att, $masterhit)) {
						$hpdamage = ($damage - $enemy->getArmor()) > 0 ? ($damage - $enemy->getArmor()) : 0;
						$hpdamage = $enemy->injure($hpdamage, $looseturn);
						$defdamage = 0;
					}
					$success = true;
				}
			break;
			case 6: // Active Def
				// roll attack
				$this->attack = [
					$this->dice->roll(),
					$this->dice->roll()
				];
				// roll attack
				$this->def = [
					$this->dice->roll(),
					$this->dice->roll()
				];

				// Handle attack
				$success = false;
				$percentile_att = $this->normalizeD100($this->attack);
				$percentile_def = $this->normalizeD100($this->def);
				if ($attacker->attack($percentile_att)) {
					$damage = $this->getHighest($this->attack);

					if (!$enemy->defense($percentile_def) || $attacker->getMasterHit($percentile_att, $masterhit)) {
						$hpdamage = ($damage - $enemy->getArmor()) > 0 ? ($damage - $enemy->getArmor()) : 0;
						$hpdamage = $enemy->injure($hpdamage, $looseturn);
						$defdamage = $enemy->injureDef($hpdamage);
						$success = true;
					}
				}
			break;
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

		return $data;
	}

	private function changeActive()
	{
		if ($this->active_combatant == 0) {
			$this->active_combatant = 1;
		} else {
			$this->active_combatant = 0;
		}
	}

	private function getInit()
	{
		$init1 = $this->combatants[0]->getInit();
		do {
			$roll = $this->dice->roll();
			$init1 += $roll;
		} while($roll == 10);

		$init2 = $this->combatants[1]->getInit();
		do {
			$roll = $this->dice->roll();
			$init2 += $roll;
		} while($roll == 10);

		// GLITCH: Doesn't handle the equation in init
		if ($init1 > $init2) {
			return 0;
		} else {
			return 1;
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

	private function battleStats()
	{
		$this->battle_data['stats'] = [];
	}

	private function getAverageRounds($battles)
	{
		$round_sum = 0;
		foreach ($battles as $battle) {
			$round_sum += $battle['rounds_num'];
		}
		return $round_sum / count($battles);


	}

	private function getMostWinner($battles)
	{
		foreach ($battles as $battle) {
			$this->battle_wins[] = $battle['winner'];
		}
		$winners = [];
		foreach ($this->battle_wins as $winner) {
			if (!array_key_exists($winner['id'], $winners)) {
				$winners[$winner['id']] = [
					'wins' => 1,
					'hp_sum' => $winner['hp'],
					'name' => $this->combatants[$winner['id'] - 1]->getName(),
				];
			} else {
				$winners[$winner['id']]['wins'] = $winners[$winner['id']]['wins'] + 1;
				$winners[$winner['id']]['hp_sum'] = $winners[$winner['id']]['hp_sum'] + $winner['hp'];
			}
		}

		foreach ($winners as $key => $winner) {
			$winners[$key]['hp_avg'] = round($winner['hp_sum'] / $winner['wins']);
			$winners[$key]['wins_avg'] = round($winner['wins'] / ($this->battle - 1) * 100);
		}
		return $winners;
	}

	private function testStats()
	{
		$this->test_data['stats'] = [
			'average_rounds' => $this->getAverageRounds($this->test_data['battles']),
			'most_winner' => $this->getMostWinner($this->test_data['battles']),
		];
	}

	private function battleReset()
	{
		$this->battle_data = [];
		$this->round = 1;
		$this->combatants[0]->reset();
		$this->combatants[1]->reset();
	}
}
