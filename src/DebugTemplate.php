<?php

namespace jasir;

use Nette\Application\UI\ITemplate;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Templating\IFileTemplate;

class DebugTemplate extends Template implements ITemplate, IFileTemplate
{


	/**
	 * Array of all rendered templates
	 * @var array
	 */
	static public $templatesRendered = array();

	/**
	 * Callbacks get called with parameters
	 * @var array of callbacks
	 */
	public static $onRender = array();

	private $template;


	/**
	 * DebugTemplate constructor.
	 * @param ITemplate $template
	 */
	public function __construct(ITemplate $template)
	{
		$this->template = $template;
	}


	public function getLatte()
	{
		return $this->template->getLatte();
	}


	/**
	 * @param $template
	 * @return static
	 */
	public static function register($template)
	{
		return new static($template);
	}


	public function render()
	{
		$template = $this->template;
		if (!array_key_exists($template->getFile(), self::$templatesRendered)) {
			self::$templatesRendered[] = array(
				'template' => $template,
				'params' => $template->getParameters(),
				'file' => $template->getFile(),
				'trace' => debug_backtrace()
			);
		}

		if (count(static::$onRender)) {
			ob_start();
			$return = $template->render();
			$content = ob_get_contents();
			ob_end_clean();
			foreach (static::$onRender as $callback) {
				$content = call_user_func(
					$callback,
					$template,
					$content,
					$template->control !== $template->presenter
				);
			}
			echo $content;
			return $return;
		}

		return $template->render();

	}



	public function getFile()
	{
		return $this->template->getFile();
	}


	public function setFile($file)
	{
		$this->template->setFile($file);
	}


	public function & __get($property)
	{
		$a = $this->template->$property;
		return $a;
	}


	public function __set($property, $value)
	{
		$this->template->$property = $value;
	}


	public function __call($method, $params)
	{
		return call_user_func_array(array($this->template, $method), $params);
	}


	public function setParameters(array $parameters)
	{
		$this->template->setParameters($parameters);
	}

	public function add($name, $value)
	{
		return $this->template->add($name, $value);
	}


	public function getParameters()
	{
		return $this->template->getParameters();
	}



	public function __toString()
	{
		return (string) $this->template;
	}

}
