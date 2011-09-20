<?php
/**
* @package    Extras\Debug
**/

namespace Extras\Debug;

class DebugTemplate implements \Nette\Templating\ITemplate, \Nette\Templating\IFileTemplate {


	/**
	 * Array of all rendered templates
	 * @var mixed
	 */
	static public $templatesRendered = array();

	/**
	 * Callbacks get called with parameters
	 * @var array of callbacks
	 */
	public static $onRender = array();

	private $template;

	public function __construct(\Nette\Templating\ITemplate $template) {
		$this->template = $template;
	}

	public function render() {
		$template = $this->template;
		if ($template instanceOf \Nette\Templating\FileTemplate) {
			if (!array_key_exists($template->getFile(), self::$templatesRendered)) {
				self::$templatesRendered[] = array(
					'template' => $template,
					'params' => $template->getParams(),
					'file' => $template->getFile(),
					'trace' => debug_backtrace()
				);

			}
		}
		
		if (count(static::$onRender)) {
			ob_start();
			$return = $template->render();
			$content = ob_get_contents();
			ob_end_clean();
			foreach (static::$onRender as $callback) {
				$content = call_user_func($callback, $this, $template, $content);
			}
			echo $content;
			return $return;
		}

		return $template->render();
		
	}
	
	public static function register($template) {
		return new static($template);
	}

	public function getFile() {
		return $this->template->getFile();
	}

	public function setFile($file) {
		$this->template->setFile($file);
	}

	public function __get($property) {
		return $this->template->$property;
	}

	public function __set($property, $value) {
		$this->template->$property = $value;
	}

	public function __call($method, $params) {
		return call_user_func_array(array($this->template,$method), $params);
	}

}
