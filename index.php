<?php

$vendorAutoload = realpath(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($vendorAutoload)) {
	require_once($vendorAutoload);
}

$controller = new \diceSim\Controller(!empty($_POST["cx2"]));
