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

	private $response;

	static private $dumps = array();

	static private $isRegistered = FALSE;

	/* --- Properties --- */

	/* --- Public Methods--- */

	public static function register() {
		if (!self::$isRegistered) {
			Debugger::addPanel(new self);
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
		$template->setFile(dirname(__FILE__) . "/control.phtml");
		$template->registerFilter(new Engine());
		$template->baseUri = /*Nette\*/Environment::getVariable('baseUri');
		$template->basePath = rtrim($template->baseUri, '/');
		$template->presenter = $template->control = $template->rootComponent = Environment::getApplication()->getPresenter();
		$template->wrap = static::$wrap;
		$template->fullTree = static::$fullTree;
		$template->cache = static::$cache ? \Nette\Environment::getCache('Debugger.Panels.ComponentTree') : NULL;
		ob_start();
		$template->render();

		return ob_get_clean();
	}

	/**
	 * Returns panel ID.
	 * @return string
	 * @see IDebugPanel::getId()
	 */
	public function getId() {
		return __CLASS__;
	}

	public static function createEditLink($file, $line) {
		return strtr(Debugger::$editor, array('%file' => urlencode(realpath($file)), '%line' => $line));
	}

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


}
