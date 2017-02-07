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
		$damage = ($amount - $this->attributes['armor']) > 0 ? ($amount - $this->attributes['armor']) : 0;
		$hp = $this->temp_hp;
		$hp = $hp - ($damage);
		if ($hp < 1) {
			$hp = 0;
		}
		$difference = $this->temp_hp - $hp;
		$this->temp_hp = $hp;
		return $difference;
	}

	public function injureDef(int $amount)
	{
		$damage = ($amount - $this->attributes['armor']) > 0 ? ($amount - $this->attributes['armor']) : 0;
		$def = $this->temp_def;
		$def = $def - ($damage);
		if ($def < 1) {
			$def = 0;
		}
		$difference = $this->temp_def - $def;
		$this->temp_def = $def;
		return $difference;
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
}