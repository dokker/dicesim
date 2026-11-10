<?php
namespace diceSim;

class Model
{
	const MAX_BATTLES = 10000;

	/** Systems that can be selected in the form */
	const SYSTEMS = [1, 2, 3, 4, 5, 6];

	/**
	 * Default fighter stats shown in the form
	 * @param  int $id Fighter ID (1 or 2)
	 * @return array
	 */
	public function default_combatant($id)
	{
		return [
			'id' => $id,
			'name' => $id == 1 ? 'Mester' : 'Tanítvány',
			'init' => 10,
			'att' => 40,
			'def' => 40,
			'hp' => 12,
			'armor' => 0,
			'x2' => 1,
		];
	}

	/**
	 * Default test settings shown in the form
	 * @return array
	 */
	public function default_options()
	{
		return [
			'battles' => 100,
			'system' => 1,
			'passive_percent' => 40,
			'mod_loose_turn' => false,
			'mod_master_hit' => false,
		];
	}

	/**
	 * Default form data
	 * @return array
	 */
	public function default_formdata()
	{
		return array_merge(
			['c1' => $this->default_combatant(1), 'c2' => $this->default_combatant(2)],
			$this->default_options()
		);
	}

	/**
	 * Turn raw form input into valid fighter stats.
	 * Empty fields get a fallback value, numbers are converted to int and kept in a valid range.
	 * @param  mixed $combatant Raw input
	 * @param  int   $id        Fighter ID (1 or 2)
	 * @return array
	 */
	public function sanitize_combatant($combatant, $id)
	{
		if (!is_array($combatant)) {
			$combatant = [];
		}

		$name = isset($combatant['name']) ? trim((string)$combatant['name']) : '';

		return [
			'id' => $id,
			'name' => $name !== '' ? $name : 'Harcos ' . $id,
			'init' => $this->int_field($combatant, 'init', 1, 0),
			'att' => $this->int_field($combatant, 'att', 10, 0, 100),
			'def' => $this->int_field($combatant, 'def', 0, 0),
			'hp' => $this->int_field($combatant, 'hp', 1, 1),
			'armor' => $this->int_field($combatant, 'armor', 0, 0),
			'x2' => $this->int_field($combatant, 'x2', 1, 1),
		];
	}

	/**
	 * Turn raw form input into valid test settings
	 * @param  array $input Raw input ($_POST)
	 * @return array
	 */
	public function sanitize_options(array $input)
	{
		$defaults = $this->default_options();

		$system = $this->int_field($input, 'system', $defaults['system']);
		if (!in_array($system, self::SYSTEMS, true)) {
			$system = $defaults['system'];
		}

		return [
			'battles' => $this->int_field($input, 'battles', $defaults['battles'], 1, self::MAX_BATTLES),
			'system' => $system,
			'passive_percent' => $this->int_field($input, 'passive_percent', $defaults['passive_percent'], 0, 100),
			'mod_loose_turn' => !empty($input['mod_loose_turn']),
			'mod_master_hit' => !empty($input['mod_master_hit']),
		];
	}

	/**
	 * Read an integer field
	 * @param  array    $data     Input data
	 * @param  string   $key      Field name
	 * @param  int      $fallback Value used when the field is empty or not a number
	 * @param  int|null $min      Minimum value
	 * @param  int|null $max      Maximum value
	 * @return int
	 */
	private function int_field(array $data, $key, $fallback, $min = null, $max = null)
	{
		$value = isset($data[$key]) ? trim((string)$data[$key]) : '';
		if (!is_numeric($value)) {
			return $fallback;
		}
		$value = (int)$value;
		if ($min !== null && $value < $min) {
			$value = $min;
		}
		if ($max !== null && $value > $max) {
			$value = $max;
		}
		return $value;
	}
}
