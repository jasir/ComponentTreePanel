<?php

namespace jasir;

use Latte\Engine;
use Nette\Application\UI\ITemplate;
use Nette\Bridges\ApplicationLatte\Template;
use Nette\Templating\IFileTemplate;

class DebugTemplate extends Template implements IFileTemplate
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

	/** @var Template|ITemplate */
	private $template;

	/** @noinspection MagicMethodsValidityInspection */


	/**
	 * DebugTemplate constructor.
	 * @param ITemplate $template
	 */
	public function __construct(ITemplate $template)
	{
		$this->template = $template;
	}


	/**
	 * @param $template
	 * @return static
	 */

	public static function register($template)
	{
		return new static($template);
	}


	/**
	 * @return Engine
	 */
	public function getLatte()
	{
		return $this->template->getLatte();
	}


	/**
	 * Renders template to output.
	 * @param null $file
	 * @param array $params
	 * @return void
	 */
	public function render($file = null, array $params = [])
	{
		if (!array_key_exists($this->template->getFile(), self::$templatesRendered)) {
			self::$templatesRendered[] = array(
				'template' => $this->template,
				'params' => $this->template->getParameters(),
				'file' => $this->template->getFile(),
				'trace' => debug_backtrace()
			);
		}

		if (count(static::$onRender)) {
			ob_start();
			$this->template->render($file, $params);
			$content = ob_get_contents();
			ob_end_clean();
			foreach (static::$onRender as $callback) {
				$content = $callback($this->template, $content, $this->template->control !== $this->template->presenter);
			}
			echo $content;
			return;
		}
		$this->template->render($file, $params);
	}


	/**
	 * @return string
	 */
	public function getFile()
	{
		return $this->template->getFile();
	}


	/**
	 * Sets the path to the template file.
	 * @param  string
	 * @return static
	 */
	public function setFile($file)
	{
		$this->template->setFile($file);
		return $this;
	}


	/**
	 * Sets all parameters.
	 * @param  array
	 * @return static
	 */
	public function setParameters(array $parameters)
	{
		$this->template->setParameters($parameters);
		return $this;
	}


	/**
	 * Returns array of all parameters.
	 * @return array
	 */
	public function getParameters()
	{
		return $this->template->getParameters();
	}


	/**
	 * Adds new template parameter.
	 * @param $name
	 * @param $value
	 * @return static
	 */
	public function add($name, $value)
	{
		$this->template->add($name, $value);
		return $this;
	}


	/**
	 * Returns a template parameter. Do not call directly.
	 * @param $name
	 * @return mixed value
	 */
	public function & __get($name)
	{
		$a = $this->template->$name;
		return $a;
	}


	/**
	 * Sets a template parameter. Do not call directly.
	 * @param $name
	 * @param $value
	 * @return void
	 */
	public function __set($name, $value)
	{
		$this->template->$name = $value;
	}


	/** @noinspection OverridingDeprecatedMethodInspection

	 * @param $method
	 * @param $params
	 * @return mixed
	 */
	public function __call($method, $params)
	{
		return call_user_func_array([$this->template, $method], $params);
	}


	/**
	 * Renders template to string.
	 * @return string
	 * @throws \Throwable
	 * @internal param throw $can exceptions? (hidden parameter)
	 */
	public function __toString()
	{
		/** @noinspection PhpMethodParametersCountMismatchInspection */
		return (string) $this->template->__toString(...func_get_args());
	}

}
