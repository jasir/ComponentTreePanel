<?php

namespace jasir;

use Nette\Application\UI\ITemplateFactory;
use Nette\DI\CompilerExtension;
use Nette\DI\ServiceDefinition;
use Nette\PhpGenerator\ClassType;

class ComponentTreePanelExtension extends CompilerExtension
{
	public function loadConfiguration()
	{
		$compiler = $this->getContainerBuilder();

		$factoryName = $compiler->getByType(ITemplateFactory::class);
		$factory = $compiler->getDefinition($factoryName);
		$factory->setAutowired(false);

		$config = [
			'originalFactory' => $factoryName,
		];

		$definition = new ServiceDefinition();
		$definition
			->setClass(DebugTemplateFactory::class)
			->addSetup('setOriginalFactory', [$factoryName]);
		$compiler->addDefinition($this->prefix('templateFactory'), $definition);
	}


	/**
	 * Adjusts DI container compiled to PHP class. Intended to be overridden by descendant.
	 * @param ClassType $class
	 * @return void
	 */
	public function afterCompile(ClassType $class)
	{
		$initialize = $class->methods['initialize'];
		$initialize->addBody('(new \jasir\ComponentTreePanel($this))->register();');
	}

}