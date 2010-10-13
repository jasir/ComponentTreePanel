<?php
/**
 * @author jasir
 * @license LGPL
 *
 * Heavily based on original David Grudl's DebugBar panel from Nette Framework
 * - see nettephp.com
 */
namespace Extras\Debug;

use \Nette\Object;
use \Nette\IDebugPanel;
use \Nette\Templates\FileTemplate;
use \Nette\Templates\LatteFilter;
use \Nette\Environment;

class ComponentTreePanel extends Object implements IDebugPanel {

	private $response;

	static private $dumps = array();

	/* --- Properties --- */

	/* --- Public Methods--- */

	/**
	 * Renders HTML code for custom tab.
	 * @return string
	 * @see IDebugPanel::getTab()
	 */
	public function getTab() {
		return "Tree";
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

}
