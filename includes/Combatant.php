<?php
namespace diceSim;

class Combatant
{
	private $attributes;
	private $temp_hp;
	private $temp_def;
	private $passive_def = null;
	private $actions = 0;

	public function __construct(array $attributes)
	{
		$this->attributes = $attributes;
		$this->reset();
	}

	/**
	 * Handle injuries
	 * @param  int  $amount     Amount of injury (negative values are treated as 0)
	 * @param  bool $loose_turn Lose one remaining attack when wounded
	 * @return int              HP actually lost
	 */
	public function injure(int $amount, $loose_turn = false)
	{
		$amount = max(0, $amount);
		$hp = max(0, $this->temp_hp - $amount);
		$difference = $this->temp_hp - $hp;
		$this->temp_hp = $hp;

		if ($loose_turn && $difference > 0 && $this->hasAction()) {
			$this->actions--;
		}

		return $difference;
	}

	/**
	 * Lower the defense value. Lowers the passive defense when it is in use.
	 * @param  int $amount Amount of defense loss
	 * @return int         Defense actually lost
	 */
	public function injureDef(int $amount)
	{
		$amount = max(0, $amount);
		if ($this->passive_def === null) {
			$tempdef = &$this->temp_def;
		} else {
			$tempdef = &$this->passive_def;
		}
		$origdef = $tempdef;
		$tempdef = max(0, $tempdef - $amount);
		return $origdef - $tempdef;
	}

	/**
	 * Get init value
	 * @return int Initiative value
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
	 * Get passive defense value. It is calculated from the base defense
	 * on the first call of a battle and lowered by injureDef() afterwards.
	 * @param  int $percent Percent of the base defense used as passive defense
	 * @return int          Passive defense value
	 */
	public function getPassiveDef($percent = 40)
	{
		if ($this->passive_def === null) {
			$this->passive_def = (int)round($this->attributes['def'] * ($percent / 100), 0, PHP_ROUND_HALF_UP);
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
	 * Execute an attack, using up one action
	 * @param  int $value Random attack value
	 * @return bool       Attack success
	 */
	public function attack($value)
	{
		if (!$this->hasAction()) {
			return false;
		}
		$this->actions--;
		return $value <= $this->attributes['att'];
	}

	/**
	 * Execute a defense
	 * @param  int $value Random defense value
	 * @return bool       Defense success
	 */
	public function defense($value)
	{
		return $value <= $this->attributes['def'];
	}

	/**
	 * Checks if attack is lower than the master hit value (tens of the attack value)
	 * @param  int  $value   Roll value to compare
	 * @param  bool $enabled Masterhit mode enabled
	 * @return bool          Master hit successful
	 */
	public function getMasterHit($value, $enabled)
	{
		if (!$enabled) {
			return false;
		}
		return $value <= floor($this->attributes['att'] / 10);
	}

	public function hasAction()
	{
		return $this->actions > 0;
	}

	public function getActions()
	{
		return $this->actions;
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
		return $this->temp_hp <= 0;
	}

	public function getName()
	{
		return $this->attributes['name'];
	}

	public function getID()
	{
		return $this->attributes['id'];
	}

	/**
	 * Restore the combatant before a new battle
	 */
	public function reset()
	{
		$this->temp_hp = $this->attributes['hp'];
		$this->temp_def = $this->attributes['def'];
		$this->passive_def = null;
		$this->actions = 0;
	}
}
