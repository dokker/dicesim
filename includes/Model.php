<?php
namespace diceSim;

class Model
{
	public function __construct()
	{
	}

	public function filter_input($data)
	{
	  $data = trim($data);
	  $data = stripslashes($data);
	  $data = htmlspecialchars($data);
	  return $data;
	}

	public function filter_array($dataset)
	{
		foreach ($dataset as $key => $data) {
			$dataset[$key] = $this->filter_input($data);
		}
		return $dataset;
	}

	public function sanitize_combatant($combatant)
	{
		foreach ($combatant as $key => $value) {
			if (empty($value)) {
				switch($key) {
					case 'att':
						$combatant[$key] = 50;
						break;
					default:
						$combatant[$key] = 5;
				}
			}
		}
		return $combatant;
	}
}