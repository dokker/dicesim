<?php
namespace diceSim\Tests;

use diceSim\Combatant;
use diceSim\Dice;
use diceSim\Simulator;
use PHPUnit\Framework\TestCase;

class SimulatorTest extends TestCase
{
	private function fighter($id, array $overrides = [])
	{
		return new Combatant(array_merge([
			'id' => $id, 'name' => 'H' . $id, 'init' => 10, 'att' => 50,
			'def' => 40, 'hp' => 12, 'armor' => 0, 'x2' => 1,
		], $overrides));
	}

	/**
	 * Resolve a single attack of $attacker against $enemy with rigged dice
	 */
	private function attack(array $dice, array $options, Combatant $attacker, Combatant $enemy)
	{
		$rigged = new RiggedDice($dice);
		$sim = new Simulator($rigged, [$attacker, $enemy], $options);
		$attacker->resetActions();
		$result = $sim->resolveAttack($attacker, $enemy);
		$this->assertSame(0, $rigged->remaining(), 'Every rigged die should be used');
		return $result;
	}

	// --- dice helpers -------------------------------------------------------

	public function testNormalizeD100ReadsTenAsZero()
	{
		$sim = new Simulator(new Dice(), [$this->fighter(1), $this->fighter(2)], []);
		$this->assertSame(47, $sim->normalizeD100([4, 7]));
		$this->assertSame(5, $sim->normalizeD100([10, 5]));
		$this->assertSame(30, $sim->normalizeD100([3, 10]));
		$this->assertSame(0, $sim->normalizeD100([10, 10]));
	}

	public function testInitiativeTieIsRolledAgain()
	{
		// both 10 + 5 (tie), then 10 + 7 vs 10 + 3
		$sim = new Simulator(new RiggedDice([5, 5, 7, 3]), [$this->fighter(1), $this->fighter(2)], []);
		$this->assertSame(0, $sim->rollInitiative());
	}

	public function testInitiativeTenExplodes()
	{
		// fighter 1: 0 + 10 + 2 = 12, fighter 2: 3 + 8 = 11
		$sim = new Simulator(
			new RiggedDice([10, 2, 8]),
			[$this->fighter(1, ['init' => 0]), $this->fighter(2, ['init' => 3])],
			[]
		);
		$this->assertSame(0, $sim->rollInitiative());
	}

	// --- Codex ----------------------------------------------------------------

	public function testCodexHitAboveDefDamagesHpAndDef()
	{
		$enemy = $this->fighter(2, ['def' => 10, 'armor' => 1]);
		// 45: hit (att 50), above def 10; damage 5 - armor 1 = 4
		$r = $this->attack([4, 5], ['system' => 1], $this->fighter(1), $enemy);

		$this->assertTrue($r['success']);
		$this->assertSame(4, $r['enemy']['hpdamage']);
		$this->assertSame(4, $r['enemy']['defdamage']);
		$this->assertSame(8, $enemy->getHP());
		$this->assertSame(6, $enemy->getDef());
	}

	public function testCodexHitBelowDefOnlyLowersDef()
	{
		$enemy = $this->fighter(2, ['def' => 50]);
		$r = $this->attack([2, 6], ['system' => 1], $this->fighter(1), $enemy);

		$this->assertSame(0, $r['enemy']['hpdamage']);
		$this->assertSame(6, $r['enemy']['defdamage']);
		$this->assertSame(12, $enemy->getHP());
		$this->assertSame(44, $enemy->getDef());
	}

	public function testCodexReportsActualDefLoss()
	{
		$enemy = $this->fighter(2, ['def' => 5]);
		// 05 is not above def 5, damage is 10 but only 5 def is left
		$r = $this->attack([10, 5], ['system' => 1], $this->fighter(1), $enemy);

		$this->assertSame(5, $r['enemy']['defdamage']);
		$this->assertSame(0, $enemy->getDef());
	}

	public function testMissDealsNoDamage()
	{
		$enemy = $this->fighter(2);
		$r = $this->attack([9, 9], ['system' => 1], $this->fighter(1), $enemy);

		$this->assertFalse($r['success']);
		$this->assertSame('99', $r['attacker']['roll']);
		$this->assertSame(0, $r['enemy']['hpdamage']);
		$this->assertSame(0, $r['enemy']['defdamage']);
	}

	public function testMasterHitIgnoresDef()
	{
		$enemy = $this->fighter(2, ['def' => 90]);
		// 03 <= tens of att 45, damage 10
		$r = $this->attack([10, 3], ['system' => 1, 'mod_master_hit' => true], $this->fighter(1, ['att' => 45]), $enemy);
		$this->assertSame(10, $r['enemy']['hpdamage']);

		$enemy = $this->fighter(2, ['def' => 90]);
		$r = $this->attack([10, 3], ['system' => 1, 'mod_master_hit' => false], $this->fighter(1, ['att' => 45]), $enemy);
		$this->assertSame(0, $r['enemy']['hpdamage']);
	}

	// --- CX2 ------------------------------------------------------------------

	public function testCx2MissAboveDefLowersDefByLowerDie()
	{
		$enemy = $this->fighter(2, ['def' => 10]);
		// 53: miss (att 20) but above def 10
		$r = $this->attack([5, 3], ['system' => 2], $this->fighter(1, ['att' => 20]), $enemy);

		$this->assertTrue($r['success']);
		$this->assertSame(3, $r['enemy']['defdamage']);
		$this->assertSame(7, $enemy->getDef());
	}

	public function testCodexMissAboveDefDoesNothing()
	{
		$enemy = $this->fighter(2, ['def' => 10]);
		$r = $this->attack([5, 3], ['system' => 1], $this->fighter(1, ['att' => 20]), $enemy);

		$this->assertFalse($r['success']);
		$this->assertSame(10, $enemy->getDef());
	}

	// --- passive systems ------------------------------------------------------

	public function testPassiveFromDamageNeverHeals()
	{
		$enemy = $this->fighter(2, ['def' => 50, 'hp' => 12]);
		// damage 2 - floor(50 / 10) = -3
		$r = $this->attack([1, 2], ['system' => 4], $this->fighter(1, ['att' => 99]), $enemy);

		$this->assertSame(0, $r['enemy']['hpdamage']);
		$this->assertSame(12, $enemy->getHP());
	}

	public function testPassiveFromDamageReducesDamage()
	{
		$enemy = $this->fighter(2, ['def' => 30, 'armor' => 1]);
		// damage 9 - armor 1 - 3 = 5
		$r = $this->attack([1, 9], ['system' => 4], $this->fighter(1), $enemy);
		$this->assertSame(5, $r['enemy']['hpdamage']);
		$this->assertSame(0, $r['enemy']['defdamage']);
	}

	public function testPassiveDefenseBlocksWithoutDefLoss()
	{
		$enemy = $this->fighter(2, ['def' => 50]); // passive 20
		$r = $this->attack([1, 5], ['system' => 5, 'passive_percent' => 40], $this->fighter(1), $enemy);

		$this->assertTrue($r['success']);
		$this->assertSame(0, $r['enemy']['hpdamage']);
		$this->assertSame(0, $r['enemy']['defdamage']);
		$this->assertSame(20, $enemy->getPassiveDef(40));

		$r = $this->attack([3, 5], ['system' => 5, 'passive_percent' => 40], $this->fighter(1), $enemy);
		$this->assertSame(5, $r['enemy']['hpdamage']);
	}

	public function testPassiveDecreasingLowersPassiveDefAndResetsBetweenBattles()
	{
		$enemy = $this->fighter(2, ['def' => 50]); // passive 20
		$r = $this->attack([1, 5], ['system' => 3, 'passive_percent' => 40], $this->fighter(1), $enemy);

		$this->assertSame(5, $r['enemy']['defdamage']);
		$this->assertSame(15, $enemy->getPassiveDef(40));

		$enemy->reset();
		$this->assertSame(20, $enemy->getPassiveDef(40));
	}

	// --- active defense -------------------------------------------------------

	public function testActiveDefenseReadsTheDefenseDiceCorrectly()
	{
		$enemy = $this->fighter(2, ['def' => 40]);
		// attack 34 hits, defense dice [10, 2] = 02 <= 40: blocked
		$r = $this->attack([3, 4, 10, 2], ['system' => 6], $this->fighter(1), $enemy);

		$this->assertFalse($r['success']);
		$this->assertSame(12, $enemy->getHP());
	}

	public function testActiveDefenseFailedDealsHpDamageWithoutDefLoss()
	{
		$enemy = $this->fighter(2, ['def' => 40]);
		// attack 34 hits, defense 77 fails, damage 4
		$r = $this->attack([3, 4, 7, 7], ['system' => 6], $this->fighter(1), $enemy);

		$this->assertTrue($r['success']);
		$this->assertSame(4, $r['enemy']['hpdamage']);
		$this->assertSame(0, $r['enemy']['defdamage']);
		$this->assertSame(40, $enemy->getDef());
	}

	// --- rounds and battles ---------------------------------------------------

	public function testFighterWithoutActionsIsSkipped()
	{
		$c1 = $this->fighter(1, ['att' => 0, 'x2' => 1]);
		$c2 = $this->fighter(2, ['att' => 0, 'x2' => 3]);
		// initiative 10+9 vs 10+1, then four missed attacks (99)
		$dice = new RiggedDice([9, 1, 9, 9, 9, 9, 9, 9, 9, 9]);
		$sim = new Simulator($dice, [$c1, $c2], ['system' => 1]);

		$round = $sim->runRound(1);

		$this->assertSame(['H1', 'H2', 'H2', 'H2'], array_map(function ($a) {
			return $a['attacker']['name'];
		}, $round['actions']));
		$this->assertSame(0, $dice->remaining());
	}

	public function testLooseTurnTakesAwayAnAttack()
	{
		$c1 = $this->fighter(1, ['att' => 99, 'x2' => 1]);
		$c2 = $this->fighter(2, ['att' => 0, 'x2' => 2, 'def' => 0]);
		// initiative: H1 first; H1 hits 55 for 5; H2 has one attack left: miss 99
		$dice = new RiggedDice([9, 1, 5, 5, 9, 9]);
		$sim = new Simulator($dice, [$c1, $c2], ['system' => 1, 'mod_loose_turn' => true]);

		$round = $sim->runRound(1);

		$this->assertCount(2, $round['actions']);
		$this->assertSame(0, $dice->remaining());
	}

	public function testRoundStopsWhenAFighterDies()
	{
		$c1 = $this->fighter(1, ['att' => 99, 'x2' => 3]);
		$c2 = $this->fighter(2, ['hp' => 3, 'def' => 0, 'x2' => 3]);
		$dice = new RiggedDice([9, 1, 5, 5]);
		$sim = new Simulator($dice, [$c1, $c2], ['system' => 1]);

		$round = $sim->runRound(1);

		$this->assertCount(1, $round['actions']);
		$this->assertTrue($c2->died());
		$this->assertSame(0, $round['stats']['c2_hp']);
	}

	public function testBattleWinnerIsTheSurvivor()
	{
		$c1 = $this->fighter(1, ['init' => 0, 'hp' => 1, 'def' => 0, 'att' => 0]);
		$c2 = $this->fighter(2, ['init' => 10, 'def' => 0, 'att' => 99]);
		$sim = new Simulator(new RiggedDice([5], true), [$c1, $c2], ['system' => 1]);

		$battle = $sim->runBattle();

		$this->assertSame(['id' => 2, 'hp' => 12, 'name' => 'H2'], $battle['winner']);
		$this->assertSame(1, $battle['rounds_num']);
	}

	public function testBattleWithoutHitsEndsInADraw()
	{
		$c1 = $this->fighter(1, ['init' => 10, 'att' => 0]);
		$c2 = $this->fighter(2, ['init' => 0, 'att' => 0]);
		$sim = new Simulator(new RiggedDice([9], true), [$c1, $c2], ['system' => 1]);

		$battle = $sim->runBattle();

		$this->assertNull($battle['winner']);
		$this->assertSame(Simulator::MAX_ROUNDS, $battle['rounds_num']);
	}

	public function testTestStats()
	{
		$c1 = $this->fighter(1, ['init' => 10, 'hp' => 1, 'att' => 99]);
		$c2 = $this->fighter(2, ['init' => 0, 'hp' => 3, 'def' => 0]);
		$sim = new Simulator(new RiggedDice([5], true), [$c1, $c2], ['system' => 1]);

		$test = $sim->runTest(3);

		$this->assertCount(3, $test['battles']);
		$this->assertSame(1.0, (float)$test['stats']['average_rounds']);
		$this->assertSame(0, $test['stats']['draws']);
		$this->assertSame([1], array_keys($test['stats']['most_winner']));
		$this->assertEquals(3, $test['stats']['most_winner'][1]['wins']);
		$this->assertEquals(100, $test['stats']['most_winner'][1]['wins_avg']);
		$this->assertEquals(1, $test['stats']['most_winner'][1]['hp_avg']);
	}

	/**
	 * Every system with random dice: battles end, results add up, equal fighters win about equally
	 */
	public function testRandomBattlesInEverySystem()
	{
		foreach ([1, 2, 3, 4, 5, 6] as $system) {
			$sim = new Simulator(new Dice(), [$this->fighter(1), $this->fighter(2)], [
				'system' => $system, 'mod_master_hit' => true, 'mod_loose_turn' => true,
			]);
			$test = $sim->runTest(400);

			$wins = array_sum(array_column($test['stats']['most_winner'], 'wins'));
			$this->assertSame(400, $wins + $test['stats']['draws'], "System $system");
			foreach ($test['stats']['most_winner'] as $winner) {
				$this->assertGreaterThan(25, $winner['wins_avg'], "System $system");
				$this->assertLessThan(75, $winner['wins_avg'], "System $system");
			}
		}
	}
}
