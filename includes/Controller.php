<?php
namespace diceSim;

class Controller
{
	private $twig;
	private $model;

	public function __construct()
	{
		$loader = new \Twig\Loader\FilesystemLoader(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates');
		$this->twig = new \Twig\Environment($loader);
		$this->model = new Model();

		$this->handle_requests();
	}

	private function handle_requests()
	{
		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$c1 = $this->model->sanitize_combatant(isset($_POST['c1']) ? $_POST['c1'] : [], 1);
			$c2 = $this->model->sanitize_combatant(isset($_POST['c2']) ? $_POST['c2'] : [], 2);
			$options = $this->model->sanitize_options($_POST);

			$simulator = new Simulator(
				new Dice(['sides' => 10]),
				[new Combatant($c1), new Combatant($c2)],
				$options
			);
			$test = $simulator->runTest($options['battles']);

			$formdata = array_merge(['c1' => $c1, 'c2' => $c2], $options);
			echo $this->twig->render('form.html.twig', ['test' => $test, 'fd' => $formdata]);
		} else {
			echo $this->twig->render('form.html.twig', ['test' => false, 'fd' => $this->model->default_formdata()]);
		}
	}
}
