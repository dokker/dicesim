<?php
namespace diceSim\Tests;

use diceSim\Combatant;
use diceSim\Model;
use diceSim\Simulator;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
	private function twig()
	{
		$loader = new \Twig\Loader\FilesystemLoader(dirname(__DIR__) . '/templates');
		return new \Twig\Environment($loader, ['strict_variables' => false]);
	}

	public function testEmptyFormRendersDefaults()
	{
		$html = $this->twig()->render('form.html.twig', ['test' => false, 'fd' => (new Model())->default_formdata()]);

		$this->assertStringContainsString('value="Mester"', $html);
		$this->assertMatchesRegularExpression('/name="c1\[def\]"\s+value="40"/', $html);
		$this->assertMatchesRegularExpression('/checked\s+value="1"/', $html);
		$this->assertStringNotContainsString('Teszt összesítése', $html);
	}

	public function testResultsRenderWithWinnersAndDraws()
	{
		$model = new Model();
		$c1 = $model->sanitize_combatant(['name' => 'Mester', 'att' => '0', 'def' => '0'], 1);
		$c2 = $model->sanitize_combatant(['name' => 'Tanítvány', 'att' => '0', 'def' => '0'], 2);
		$options = $model->sanitize_options(['battles' => '1']);
		$sim = new Simulator(new RiggedDice([9, 1], true), [new Combatant($c1), new Combatant($c2)], $options);
		$test = $sim->runTest(1);

		$html = $this->twig()->render('form.html.twig', [
			'test' => $test,
			'fd' => array_merge(['c1' => $c1, 'c2' => $c2], $options),
		]);

		$this->assertStringContainsString('Teszt összesítése', $html);
		$this->assertStringContainsString('csata döntetlen', $html);
		$this->assertStringContainsString('Döntetlen:', $html);
		// a 0 defense is shown as 0, not replaced with the default
		$this->assertMatchesRegularExpression('/name="c1\[def\]"\s+value="0"/', $html);
	}

	public function testNamesAreEscaped()
	{
		$fd = (new Model())->default_formdata();
		$fd['c1']['name'] = '<b>"X"</b>';
		$html = $this->twig()->render('form.html.twig', ['test' => false, 'fd' => $fd]);

		$this->assertStringContainsString('&lt;b&gt;&quot;X&quot;&lt;/b&gt;', $html);
	}
}
