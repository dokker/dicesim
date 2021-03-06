<?php
namespace diceSim;

class Combatant
{
	private $attributes;
	private $temp_hp;
	private $temp_def;
	private $passive_def = null;
	private $actions;
	private $attacks;

	public function __construct(array $attributes)
	{
		$this->attributes = $attributes;
		$this->reset();
	}

	/**
	 * Handle injuries
	 * @param  int    $amount Amount of injury
	 * @return int         Actual state of HP
	 */
	public function injure(int $amount, $loose_turn = false)
	{
		$hp = $this->temp_hp;
		$hp = $hp - $amount;
		if ($hp < 1) {
			$hp = 0;
		}
		$difference = $this->temp_hp - $hp;
		$this->temp_hp = $hp;

		// If loose turn is active
		if ($loose_turn && ($amount > 0) && $this->hasAction()) {
			$this->actions--;
		}

		return $difference;
	}

	public function injureDef(int $amount)
	{
		if ($this->passive_def == null) {
			$tempdef = &$this->temp_def;
		} else {
			$tempdef = &$this->passive_def;
		}
		$origdef = $tempdef;
		$newdef = $tempdef - $amount;
		if ($origdef > 0) {
			if ($newdef > 0) {
				$tempdef = $newdef;
			} else {
				$tempdef = 0;
			}
			return $origdef - $newdef;
		} else {
			return 0;
		}
	}

	/**
	 * Get init value
	 * @return int Defense value
	 */
	public function getInit()
	{
		return $this->attributes['init'];
	}

	/**
	 * Get actual defense value
	 * @return int Defense value
	 */
	public function getDef()
	{
		return $this->temp_def;
	}

	/**
	 * Get passive defense value
	 * @return int Defense value
	 */
	public function getPassiveDef($percent = 40)
	{
		if ($this->passive_def == null) {
			$this->passive_def =  round($this->attributes['def'] * ($percent / 100), 0, PHP_ROUND_HALF_UP);
		}
		return $this->passive_def;
	}

	public function getArmor()
	{
		return $this->attributes['armor'];
	}

	public function getHP()
	{
		return $this->temp_hp;
	}

	/**
	 * Execute an attack
	 * @param  int $value Random attack value
	 * @return bool        Attack success
	 */
	public function attack($value)
	{
		if ($this->hasAction()) {
			$this->actions--;
			if ($value <= $this->attributes['att']) {
				$this->attacks[] = true;
				return true;
			}
			$this->attacks[] = false;
		}
		return false;
	}

	/**
	 * Execute a defense
	 * @param  int $value Random defense value
	 * @return bool        Defense success
	 */
	public function defense($value)
	{
		if ($value <= $this->attributes['def']) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if attack lower than master hit value
	 * @param  int $value Roll value to compare
	 * @param bool $masterhit Masterhit mode enabled
	 * @return bool        Master hit successful
	 */
	public function getMasterHit($value, $enabled)
	{
		if ($enabled) {
			$tens = floor($this->attributes['att'] / 10);
			if ($value <= $tens) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	public function hasAction()
	{
		if ($this->actions > 0) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Reset actions to default state
	 */
	public function resetActions()
	{
		$this->actions = $this->attributes['x2'];
	}

	public function died()
	{
		if ($this->temp_hp > 0) {
			return false;
		} else {
			return true;
		}
	}

	public function getName()
	{
		return $this->attributes['name'];
	}

	public function getID()
	{
		return $this->attributes['id'];
	}

	public function reset()
	{
		$this->temp_hp = $this->attributes['hp'];
		$this->temp_def = $this->attributes['def'];
	}
}
