<?php
/**
 * @author jasir
 * @license WTFPL (http://en.wikipedia.org/wiki/WTFPL)
 */
namespace Extras\Debug;

use Nette\Object;
use Nette\Diagnostics\IBarPanel;
use Nette\Templating\FileTemplate;
use Nette\Latte\Engine;
use Nette\Diagnostics\Debugger;
use Nette\Environment;

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
	 * Is caching allowed
	 * @var bool
	 */
	public static $cache = TRUE;

	/**
	 * Include dumps in tree
	 * @var bool
	 */
	public static $dumps = TRUE;

	/**
	 * Include sources in tree
	 * @var bool
	 */
	public static $showSources = TRUE;


	/**
	 * Should be paremeters section open by default?
	 * @var bool
	 */
	public static $parametersOpen = TRUE;

	/**
	 * Parameters section open by default?
	 * @var bool
	 */
	public static $presenterOpen = TRUE;

	public static $appDir;


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
		$template->setCacheStorage(Environment::getContext()->templateCacheStorage);
		$template->setFile(dirname(__FILE__) . "/bar.latte");
		$template->registerFilter(new Engine());
		$template->presenter = $template->control = $template->rootComponent = Environment::getApplication()->getPresenter();
		$template->wrap = static::$wrap;
		$template->cache = static::$cache ? Environment::getCache('Debugger.Panels.ComponentTree') : NULL;
		$template->dumps = static::$dumps;
		$template->parametersOpen = static::$parametersOpen;
		$template->presenterOpen = static::$presenterOpen;
		$template->showSources = static::$showSources;
		$template->registerHelper('parametersInfo', callback($this, 'getParametersInfo'));
		$template->registerHelper('editlink', callback($this, 'buildEditorLink'));
		$template->registerHelper('highlight', callback($this, 'highlight'));
		$template->registerHelper('filterMethods', callback($this, 'filterMethods'));
		$template->registerHelper('renderedTemplates', callback($this, 'getRenderedTemplates'));

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

		$normalParameters = $presenterComponent->getParameter();
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

	/**
	 * Returns templates used when rendering object
	 * @param type $object
	 * @return array
	 */
	public static function getRenderedTemplates($object) {
		$arr = array();
		foreach(\Extras\Debug\DebugTemplate::$templatesRendered as $info) {
			if ($info['template']->control === $object) {
				$arr[] = $info;
			}
		}
		return $arr;
	}

	public static function relativizePath($path, $tag = NULL) {
		$relative = static::getRelative($path, static::$appDir);
		if ($tag) {
			$relative = \Nette\Utils\Strings::replace($relative, '#('.pathinfo($relative, PATHINFO_FILENAME).')(\.(latte|phtml))#', "<$tag>\$1</$tag>\$2");
		}
		return $relative;
	}

	/**
	 * Converts path to be relative to given $compartTo path
	 *
	 * @param string $path
	 * @param string $compareTo
	 * @return string
	 */
	static public function getRelative($path, $compareTo) {
		$path = realpath($path);
		$path = str_replace(':', '', $path);
		$path = str_replace('\\', '/', $path);
		$compareTo = realpath($compareTo);
		$compareTo = str_replace(':', '', $compareTo);
		$compareTo = str_replace('\\', '/', $compareTo);

		// clean arguments by removing trailing and prefixing slashes
		if (substr($path, - 1) == '/') {
			$path = substr($path, 0, - 1);
		}
		if (substr($path, 0, 1) == '/') {
			$path = substr($path, 1);
		}

		if (substr($compareTo, - 1) == '/') {
			$compareTo = substr($compareTo, 0, - 1);
		}
		if (substr($compareTo, 0, 1) == '/') {
			$compareTo = substr($compareTo, 1);
		}

		if ($compareTo == '')
			return $path;

		// simple case: $compareTo is in $path
		if (strpos($path, $compareTo) === 0) {
			$offset = strlen($compareTo) + 1;
			return substr($path, $offset);
		}

		$relative       = array();
		$pathParts      = explode('/', $path);
		$compareToParts = explode('/', $compareTo);

		foreach ($compareToParts as $index => $part) {
			if (isset($pathParts[$index]) && $pathParts[$index] == $part) {
				continue;
			}
			$relative[] = '..';
		}

		foreach ($pathParts as $index => $part) {
			if (isset($compareToParts[$index]) && $compareToParts[$index] == $part) {
				continue;
			}
			$relative[] = $part;
		}
			return implode('/', $relative);
	}



}
