<?php
namespace diceSim;

class Combatant
{
	private $attributes;
	private $temp_hp;
	private $temp_def;
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
	public function injure(int $amount)
	{
		$hp = $this->temp_hp;
		$hp = $hp - $amount;
		if ($hp < 1) {
			$hp = 0;
		}
		$difference = $this->temp_hp - $hp;
		$this->temp_hp = $hp;
		return $difference;
	}

	public function injureDef(int $amount)
	{
		$origdef = $this->temp_def;
		$newdef = $this->temp_def - $amount;
		if ($origdef > 0) {
			if ($newdef > 0) {
				$this->temp_def = $newdef;
			} else {
				$this->temp_def = 0;
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

	public function getArmor()
	{
		return $this->attributes['armor'];
	}

	public function getHP()
	{
		return $temp_hp;
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

	public function reset()
	{
		$this->temp_hp = $this->attributes['hp'];
		$this->temp_def = $this->attributes['def'];
	}
}