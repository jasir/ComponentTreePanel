<?php

namespace jasir;

use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;

class ComponentTreePanelExtension extends CompilerExtension
{
	/**
	 * Adjusts DI container before is compiled to PHP class. Intended to be overridden by descendant.
	 * @return void
	 */
	public function beforeCompile()
	{
		parent::beforeCompile();
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