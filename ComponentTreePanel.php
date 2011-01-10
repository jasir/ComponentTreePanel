<?php
/**
 * @author jasir
 * @license LGPL
 */
namespace Extras\Debug;

use \Nette\Object;
use \Nette\IDebugPanel;
use \Nette\Templates\FileTemplate;
use \Nette\Templates\LatteFilter;
use \Nette\Debug;
use \Nette\Environment;

/**
 * Displays current presenter and component
 * tree hiearchy
 */
class ComponentTreePanel extends Object implements IDebugPanel {

	private $response;

	static private $dumps = array();

	static private $isRegistered = FALSE;

	/* --- Properties --- */

	/* --- Public Methods--- */

	public static function register() {
		if (!self::$isRegistered) {
			Debug::addPanel(new self);
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

		//Debug::timer('component-tree');

		/** @var Template */
		$template = new FileTemplate;
		$template->setFile(dirname(__FILE__) . "/control.phtml");
		$template->registerFilter(new LatteFilter());
		$template->baseUri = /*Nette\*/Environment::getVariable('baseUri');
		$template->basePath = rtrim($template->baseUri, '/');
		$template->presenter = $template->control = $template->rootComponent = Environment::getApplication()->getPresenter();
		ob_start();
		$template->render();

		//Debug::fireLog("component-tree render time (ms): " . round(1000 * Debug::timer('component-tree', TRUE), 2));

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
		return strtr(Debug::$editor, array('%file' => urlencode(realpath($file)), '%line' => $line));
	}

	public static function getSource($fileName, $startLine = NULL, $endLine = NULL) {
		static $sources = array(); /* --- simple caching --- */

		if (!in_array($fileName, $sources)) {
			$sources[$fileName] = file($fileName);
		}

		$iterator = new \LimitIterator(new \ArrayIterator($sources[$fileName]), $startLine, $endLine - $startLine + 1);
		return $iterator;
	}


}
