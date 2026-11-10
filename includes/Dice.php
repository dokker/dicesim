<?php
namespace diceSim;

class Dice
{
	private $sides;

	public function __construct(array $attributes = ['sides' => 10])
	{
		$this->sides = (int)$attributes['sides'];
	}

	/**
	 * Roll the dice once
	 * @return int Result between 1 and the number of sides
	 */
	public function roll()
	{
		return random_int(1, $this->sides);
	}
}
