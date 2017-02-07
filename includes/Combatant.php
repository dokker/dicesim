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
		$this->temp_hp = $attributes['hp'];
		$this->temp_def = $attributes['def'];
	}

	/**
	 * Handle injuries
	 * @param  int    $amount Amount of injury
	 * @return int         Actual state of HP
	 */
	public function injure(int $amount)
	{
		$this->temp_hp = $this->temp_hp - ($amount - $this->attributes['armor']);
		if ($this->temp_hp < 1) {
			$this->temp_hp = 0;
		}
		return $this->temp_hp;
	}

	public function injureDef(int $amount)
	{
		$this->temp_def = $this->temp_def - ($amount - $this->attributes['armor']);
		if ($this->temp_def < 1) {
			$this->temp_def = 0;
		}
		return $this->temp_def;
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
	 * Execute an attack
	 * @param  int $value Random attack value
	 * @return bool        Attack success
	 */
	public function attack($value)
	{
		if ($this->hasAction()) {
			$this->actions--;
			if ($value >= $attributes['att']) {
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
}