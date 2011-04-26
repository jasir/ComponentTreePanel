<?php
/**
 * @author jasir
 * @license WTFPL (http://en.wikipedia.org/wiki/WTFPL)
 */
namespace Extras\Debug;

use \Nette\Object;
use Nette\Diagnostics\IBarPanel;
use Nette\Templating\FileTemplate;
use Nette\Latte\Engine;
use Nette\Diagnostics\Debugger;
use \Nette\Environment;

/**
 * Displays current presenter and component
 * tree hiearchy
 */
class ComponentTreePanel extends Object implements IBarPanel {

	/**
	 * Use wrapping in output
	 * @var bool
	 */
	public static $wrap = FALSE;

	/**
	 * Tree of components is fully visible (opened) on reload
	 * @var bool
	 */
	public static $fullTree = FALSE;

	/**
	 * Is caching allowed
	 * @var bool
	 */
	public static $cache = TRUE;

	/**
	 * Include dumps
	 *
	 * @var bool
	 */
	public static $dumps = TRUE;


	/**
	 * Should be paremeters section open by default
	 *
	 * @var bool
	 */
	public static $parametersOpen = TRUE;


	private $response;

	static private $isRegistered = FALSE;

	/* --- Properties --- */

	/* --- Public Methods--- */

	public static function register() {
		if (!self::$isRegistered) {
			Debugger::$bar->addPanel(new self);
			self::$isRegistered = TRUE;
		}
	}

	/**
	 * Renders HTML code for custom tab.
	 * @return string
	 * @see IDebugPanel::getTab()
	 */
	public function getTab() {
		return "<img src=\"data:image/gif;base64,R0lGODlhEAAQAMQWACRITrPgAKXFXgQ2BBA+EP/ndv/6u8z/AAo6Cgk6CgBRHigVAXCjAP/NJD4fAFwuAP+xEeDg4EAcAQFaUgBIHgAuOwFcdwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAABYALAAAAAAQABAAAAVToGUBUgmIaCpKhlFIaswWjROrEg0td0o+i1MvVUlNKMhJrIiiRCIBwrJCrSgiB0FimaIEBIwBFzUhIAZKFXNINCIp6XbzGZ1Wr9kt2wsWs8tnaSEAOw%3D%3D\">Tree";
	}

	/**
	 * Renders HTML code for custom panel.
	 * @return string
	 * @see IDebugPanel::getPanel()
	 */
	public function getPanel() {

		/** @var Template */
		$template = new FileTemplate;
		$template->setFile(dirname(__FILE__) . "/bar.latte");
		$template->registerFilter(new Engine());
		$template->baseUri = /*Nette\*/Environment::getVariable('baseUri');
		$template->basePath = rtrim($template->baseUri, '/');
		$template->presenter = $template->control = $template->rootComponent = Environment::getApplication()->getPresenter();
		$template->wrap = static::$wrap;
		$template->fullTree = static::$fullTree;
		$template->cache = static::$cache ? \Nette\Environment::getCache('Debugger.Panels.ComponentTree') : NULL;
		$template->dumps = static::$dumps;
		$template->parametersOpen = static::$parametersOpen;
		$template->registerHelper('parametersInfo', callback($this, 'getParametersInfo'));
		$template->registerHelper('editlink', callback($this, 'buildEditorLink'));
		$template->registerHelper('highlight', callback($this, 'highlight'));
		$template->registerHelper('filterMethods', callback($this, 'filterMethods'));
		ob_start();
		$template->render();

		return ob_get_clean();
	}

	/**
	 * Returns link to open file in editor
	 *
	 * @param string $file
	 * @param int $line
	 * @return string
	 */
	public static function buildEditorLink($file, $line) {
		return strtr(Debugger::$editor, array('%file' => urlencode(realpath($file)), '%line' => $line));
	}

	/**
	 * Get part of source file
	 *
	 * @param string $fileName
	 * @param int $startLine
	 * @param int $endLine
	 * @return \LimitIterator
	 */
	public static function getSource($fileName, $startLine = NULL, $endLine = NULL) {
		static $sources = array(); /* --- simple caching --- */

		if (!in_array($fileName, $sources)) {
			$txt = file_get_contents($fileName);
			$txt = str_replace("\r\n", "\n", $txt);
			$txt = str_replace("\r", "\n", $txt);
			$sources[$fileName] = explode("\n", $txt);
		}

		$iterator = new \LimitIterator(new \ArrayIterator($sources[$fileName]), $startLine, $endLine - $startLine + 1);
		return $iterator;
	}

	/**
	 * Highligts PHP source code of object
	 *
	 * @param mixed $object
	 * @return source
	 */
	public static function highlight($object) {

		if ( !($object instanceOf \Nette\Reflection\Method || $object instanceof \Nette\Reflection\ClassType)) {
			$object = $object->getReflection();
		}

		$sourceLines = static::getSource($object->getFileName(), $object->getStartLine()-1, $object->getEndLine()-1);

		$phpDocTxt = $object->getDocComment();
		$phpDoc = array();
		if (strlen($phpDocTxt) > 0) {
			$phpDoc = explode("\n", $phpDocTxt);
		}
		$phpDoc = new \ArrayIterator($phpDoc);
		$lines = new \AppendIterator();

		$lines->append($phpDoc);
		$lines->append($sourceLines);

		$source = '';

		foreach ($lines as $line) {
			$source .= $line . "\n";
		}
		$source = highlight_string("<?php\n" . $source, TRUE);
		$source = str_replace('<span style="color: #0000BB">&lt;?php<br />&nbsp;&nbsp;&nbsp;&nbsp;</span>', '', $source);
		$source = str_replace('<span style="color: #0000BB">&lt;?php<br /></span>', '', $source);
		$source = "&nbsp;&nbsp;&nbsp;" . $source;
		return $source;

	}

	/**
	 * Filters methods from object
	 * - filters in methods that matches pattern
	 * - filters out methods that are in $hideMethods
	 * - if $inherited === FALSE, shows only methods from current object's class, not from its predecessors
	 * @param mixed $object
	 * @param string $pattern
	 * @param array $hideMethods
	 * @param bool $inherited
	 * @return array
	 */
	public static function filterMethods($object, $pattern, $hideMethods, $inherited) {
		$methods = $object->getReflection()->getMethods();
		$filtered = array();
		foreach ($methods as $method) {
			if (!preg_match($pattern, $method->getName(), $matches)) {
				continue;
			}
			if ($method->class !== get_class($object) && $inherited === FALSE) {
				continue;
			}
			if (in_array($method->getName(), $hideMethods)) {
				continue;
			}
			$filtered[] = $method;
		}
		return $filtered;
	}

	/**
	 * Returns informations about parameters (actual and persistent)
	 * @param presenterComponent $presenterComponent
	 * @return array
	 */
	public static function getParametersInfo($presenterComponent) {
			$params = array();

			$normalParameters = $presenterComponent->getParam();
			ksort($normalParameters);
			foreach ($normalParameters as $name => $value) {
				$params[$name] = array(
					'value' => $value,
					'persistent' => FALSE,
					'meta' => NULL,
				);
			}

			$persistentParameters = $presenterComponent->getReflection()->getPersistentParams();
			ksort($persistentParameters);
			foreach ($persistentParameters as $name => $meta) {
				$params[$name] = array(
					'value' => $presenterComponent->$name,
					'persistent' => TRUE,
					'meta' => $meta,
				);
			}

			return $params;
	}

}
