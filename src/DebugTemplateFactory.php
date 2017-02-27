<?php

namespace jasir;

use Nette\Application\UI\Control;
use Nette\Application\UI\ITemplate;
use Nette\Application\UI\ITemplateFactory;
use Nette\Bridges\ApplicationLatte\ILatteFactory;
use Nette\ComponentModel\IContainer;
use Nette\DI\Container;

class DebugTemplateFactory implements ITemplateFactory
{
	/** @var ITemplateFactory */
	public $factory;

	public $container;


	/**
	 * DebugTemplateFactory constructor.
	 * @param IContainer $container
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}


	/**
	 * @param $factoryName
	 */
	public function setOriginalFactory($factoryName)
	{
		$this->factory = $this->container->getService($factoryName);
	}
	/**
	 * @param Control|null $control
	 * @return ITemplate
	 */
	public function createTemplate(Control $control = null)
	{
		return DebugTemplate::register($this->factory->createTemplate($control));
	}
}