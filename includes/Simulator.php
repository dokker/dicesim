<?php
namespace diceSim;

/**
 * Runs battles between two combatants and collects the statistics
 */
class Simulator
{
	const SYSTEM_CODEX = 1;
	const SYSTEM_CX2 = 2;
	const SYSTEM_PASSIVE_DECREASING = 3;
	const SYSTEM_PASSIVE_FROM_DAMAGE = 4;
	const SYSTEM_PASSIVE = 5;
	const SYSTEM_ACTIVE = 6;

	/** A battle still going after this many rounds ends in a draw */
	const MAX_ROUNDS = 500;

	private $dice;
	/** @var Combatant[] */
	private $combatants;
	private $options;

	/**
	 * @param Dice        $dice       d10 used for every roll
	 * @param Combatant[] $combatants The two fighters
	 * @param array       $options    system, passive_percent, mod_loose_turn, mod_master_hit
	 */
	public function __construct(Dice $dice, array $combatants, array $options)
	{
		$this->dice = $dice;
		$this->combatants = array_values($combatants);
		$this->options = array_merge([
			'system' => self::SYSTEM_CODEX,
			'passive_percent' => 40,
			'mod_loose_turn' => false,
			'mod_master_hit' => false,
		], $options);
	}

	/**
	 * Run several battles
	 * @param  int   $repeat Number of battles
	 * @return array         Battles and summary statistics
	 */
	public function runTest($repeat)
	{
		$test = ['battles' => []];
		for ($battle = 1; $battle <= $repeat; $battle++) {
			$test['battles'][] = $this->runBattle();
		}
		$test['stats'] = $this->testStats($test['battles']);
		return $test;
	}

	/**
	 * Run one battle until one of the fighters dies or MAX_ROUNDS is reached
	 * @return array Rounds, winner (null on a draw) and the number of rounds
	 */
	public function runBattle()
	{
		foreach ($this->combatants as $combatant) {
			$combatant->reset();
		}

		$rounds = [];
		while (!$this->isOver() && count($rounds) < self::MAX_ROUNDS) {
			$rounds[] = $this->runRound(count($rounds) + 1);
		}

		$winner = null;
		if ($this->combatants[0]->died() xor $this->combatants[1]->died()) {
			$survivor = $this->combatants[0]->died() ? $this->combatants[1] : $this->combatants[0];
			$winner = [
				'id' => $survivor->getID(),
				'hp' => $survivor->getHP(),
				'name' => $survivor->getName(),
			];
		}

		return [
			'rounds' => $rounds,
			'winner' => $winner,
			'rounds_num' => count($rounds),
		];
	}

	/**
	 * Run one round: the fighters attack in turns until both used up their attacks
	 * @param  int   $num Round number
	 * @return array      Actions and the state of the fighters after the round
	 */
	public function runRound($num)
	{
		foreach ($this->combatants as $combatant) {
			$combatant->resetActions();
		}
		$active = $this->rollInitiative();

		$actions = [];
		while (!$this->isOver() && ($this->combatants[0]->hasAction() || $this->combatants[1]->hasAction())) {
			$attacker = $this->combatants[$active];
			if ($attacker->hasAction()) {
				$actions[] = $this->resolveAttack($attacker, $this->combatants[1 - $active]);
			}
			$active = 1 - $active;
		}

		return [
			'num' => sprintf("%02d", $num),
			'actions' => $actions,
			'stats' => [
				'c1_def' => $this->currentDef($this->combatants[0]),
				'c1_hp' => $this->combatants[0]->getHP(),
				'c1_name' => $this->combatants[0]->getName(),
				'c2_def' => $this->currentDef($this->combatants[1]),
				'c2_hp' => $this->combatants[1]->getHP(),
				'c2_name' => $this->combatants[1]->getName(),
			],
		];
	}

	/**
	 * Resolve one attack according to the selected system
	 * @param  Combatant $attacker
	 * @param  Combatant $enemy
	 * @return array     Attack data for the battle log
	 */
	public function resolveAttack(Combatant $attacker, Combatant $enemy)
	{
		$system = $this->options['system'];
		$looseturn = $this->options['mod_loose_turn'];
		$masterhit = $this->options['mod_master_hit'];
		$percent = $this->options['passive_percent'];

		$attack = $this->rollD100Dice();
		$percentile_att = $this->normalizeD100($attack);
		$hpdamage = 0;
		$defdamage = 0;
		$success = false;

		if ($attacker->attack($percentile_att)) {
			$damage = $this->getHighest($attack);
			$net = max(0, $damage - $enemy->getArmor());
			$is_masterhit = $attacker->getMasterHit($percentile_att, $masterhit);

			switch ($system) {
				case self::SYSTEM_CODEX:
				case self::SYSTEM_CX2:
					if ($percentile_att > $enemy->getDef() || $is_masterhit) {
						$hpdamage = $enemy->injure($net, $looseturn);
						$defdamage = $enemy->injureDef($hpdamage);
					} else {
						$defdamage = $enemy->injureDef($net);
					}
					$success = true;
					break;

				case self::SYSTEM_PASSIVE_DECREASING:
					if ($percentile_att > $enemy->getPassiveDef($percent) || $is_masterhit) {
						$hpdamage = $enemy->injure($net, $looseturn);
						$defdamage = $enemy->injureDef($hpdamage);
					} else {
						$defdamage = $enemy->injureDef($net);
					}
					$success = true;
					break;

				case self::SYSTEM_PASSIVE_FROM_DAMAGE:
					$reduced = $damage - $enemy->getArmor() - (int)floor($enemy->getDef() / 10);
					$hpdamage = $enemy->injure(max(0, $reduced), $looseturn);
					$success = true;
					break;

				case self::SYSTEM_PASSIVE:
					if ($percentile_att > $enemy->getPassiveDef($percent) || $is_masterhit) {
						$hpdamage = $enemy->injure($net, $looseturn);
					}
					$success = true;
					break;

				case self::SYSTEM_ACTIVE:
					$percentile_def = $this->normalizeD100($this->rollD100Dice());
					if (!$enemy->defense($percentile_def) || $is_masterhit) {
						$hpdamage = $enemy->injure($net, $looseturn);
						$success = true;
					}
					break;
			}
		} elseif ($system == self::SYSTEM_CX2 && $percentile_att > $enemy->getDef()) {
			// CX2: a missed attack rolled above the defense still lowers it by the lower die
			$defdamage = $enemy->injureDef($this->getLowest($attack));
			$success = true;
		}

		return [
			'success' => $success,
			'attacker' => [
				'name' => $attacker->getName(),
				'roll' => sprintf("%02d", $percentile_att),
			],
			'enemy' => [
				'name' => $enemy->getName(),
				'hpdamage' => $hpdamage,
				'defdamage' => $defdamage,
			],
		];
	}

	/**
	 * Roll initiative: init + d10 for both fighters, a 10 is rolled again and added.
	 * Ties are rolled again.
	 * @return int Index of the fighter who acts first
	 */
	public function rollInitiative()
	{
		do {
			$init1 = $this->combatants[0]->getInit() + $this->rollExploding();
			$init2 = $this->combatants[1]->getInit() + $this->rollExploding();
		} while ($init1 == $init2);

		return $init1 > $init2 ? 0 : 1;
	}

	/**
	 * Read two d10 as a percentile roll, a 10 counts as 0
	 * @param  int[] $dice Tens die and ones die
	 * @return int         0-99
	 */
	public function normalizeD100(array $dice)
	{
		return ($dice[0] % 10) * 10 + ($dice[1] % 10);
	}

	public function getHighest(array $rolls)
	{
		return max($rolls[0], $rolls[1]);
	}

	public function getLowest(array $rolls)
	{
		return min($rolls[0], $rolls[1]);
	}

	private function rollD100Dice()
	{
		return [$this->dice->roll(), $this->dice->roll()];
	}

	private function rollExploding()
	{
		$sum = 0;
		do {
			$roll = $this->dice->roll();
			$sum += $roll;
		} while ($roll == 10);
		return $sum;
	}

	private function isOver()
	{
		return $this->combatants[0]->died() || $this->combatants[1]->died();
	}

	/**
	 * Defense value shown in the round summary
	 */
	private function currentDef(Combatant $combatant)
	{
		$system = $this->options['system'];
		if ($system == self::SYSTEM_PASSIVE || $system == self::SYSTEM_PASSIVE_DECREASING) {
			return $combatant->getPassiveDef($this->options['passive_percent']);
		}
		return $combatant->getDef();
	}

	/**
	 * Summary of a test
	 * @param  array $battles Battle data from runBattle()
	 * @return array
	 */
	private function testStats(array $battles)
	{
		$count = count($battles);
		if ($count == 0) {
			return ['average_rounds' => 0, 'most_winner' => [], 'draws' => 0];
		}

		$round_sum = 0;
		$draws = 0;
		$winners = [];
		foreach ($battles as $battle) {
			$round_sum += $battle['rounds_num'];
			$winner = $battle['winner'];
			if ($winner === null) {
				$draws++;
				continue;
			}
			if (!isset($winners[$winner['id']])) {
				$winners[$winner['id']] = ['wins' => 0, 'hp_sum' => 0, 'name' => $winner['name']];
			}
			$winners[$winner['id']]['wins']++;
			$winners[$winner['id']]['hp_sum'] += $winner['hp'];
		}
		ksort($winners);

		foreach ($winners as $id => $winner) {
			$winners[$id]['hp_avg'] = round($winner['hp_sum'] / $winner['wins']);
			$winners[$id]['wins_avg'] = round($winner['wins'] / $count * 100);
		}

		return [
			'average_rounds' => round($round_sum / $count, 2),
			'most_winner' => $winners,
			'draws' => $draws,
		];
	}
}
