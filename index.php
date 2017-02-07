<?php

$vendorAutoload = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($vendorAutoload)) {
	require_once($vendorAutoload);
}

$combatant1 = [
	'name' => 'Novice',
	'init' => 15,
	'att' => 55,
	'def' => 50,
	'hp' => 13,
	'armor' => 4,
	'x2' => 2,
];

$combatant2 = [
	'name' => 'Master',
	'init' => 22,
	'att' => 75,
	'def' => 70,
	'hp' => 13,
	'armor' => 4,
	'x2' => 2,
];

$controller = new \diceSim\Controller([$combatant1, $combatant2]);
$controller->start();
