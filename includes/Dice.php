<?php
namespace diceSim;

class Dice
{
	private $attributes;

	public function __construct (array $attributes)
	{
		$this->attributes = $attributes;
	}

	public function roll()
	{
		$roll = rand(1, $this->attributes['sides']);
		return (int)$roll;
	}
}