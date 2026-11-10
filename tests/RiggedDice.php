<?php
namespace diceSim\Tests;

use diceSim\Dice;

/**
 * Dice that returns predetermined values, for deterministic tests
 */
class RiggedDice extends Dice
{
	private $values;
	private $cycle;
	private $position = 0;

	/**
	 * @param int[] $values Values returned in order
	 * @param bool  $cycle  Start again from the first value when all were used
	 */
	public function __construct(array $values, $cycle = false)
	{
		parent::__construct(['sides' => 10]);
		$this->values = array_values($values);
		$this->cycle = $cycle;
	}

	public function roll()
	{
		if ($this->position >= count($this->values)) {
			if (!$this->cycle) {
				throw new \LogicException('RiggedDice ran out of values');
			}
			$this->position = 0;
		}
		return $this->values[$this->position++];
	}

	public function remaining()
	{
		return count($this->values) - $this->position;
	}
}
