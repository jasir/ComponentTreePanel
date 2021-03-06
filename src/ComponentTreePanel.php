<?php
/**
 * @author jasir
 * @license WTFPL (http://en.wikipedia.org/wiki/WTFPL)
 */

namespace jasir;

use ArrayIterator;
use LimitIterator;
use Nette\Application\Application;
use Nette\Application\IPresenter;
use Nette\Application\Responses\ForwardResponse;
use Nette\Application\Responses\RedirectResponse;
use Nette\Application\UI\Presenter;
use Nette\Application\UI\PresenterComponent;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\ComponentModel\IComponent;
use Nette\DI\Container;
use Nette\Reflection\ClassType;
use Nette\Reflection\Method;
use Nette\Utils\Strings;
use Tracy\Debugger;
use Tracy\Dumper;
use Tracy\IBarPanel;

/**
 * Displays current presenter and component
 * tree hierarchy
 */
class ComponentTreePanel implements IBarPanel
{

	/**
	 * Use wrapping in output
	 * @var bool
	 */
	public static $wrap = false;

	/**
	 * Is caching allowed
	 * @var bool
	 */
	public static $cache = true;

	/**
	 * Include dumps in tree
	 * @var bool
	 */
	public static $dumps = false;

	/**
	 * Include sources in tree
	 * @var bool
	 */
	public static $showSources = true;

	/**
	 * Should be parameters section open by default?
	 * @var bool
	 */
	public static $parametersOpen = false;

	/**
	 * Parameters section open by default?
	 * @var bool
	 */
	public static $presenterOpen = false;

	/**
	 * Application dir
	 * @var string
	 */
	public static $appDir;

	/**
	 * Templates variables that should be not dumped (performance reasons)
	 * @var array
	 */

	public static $omittedTemplateVariables = ['presenter', 'control', 'netteCacheStorage', 'netteHttpResponse', 'template', 'user'];

	private static $reflectionCache = [];

	private static $_dumpCache = [];


	/* --- Private --- */

	private $response;

	/**
	 * @var Container
	 */
	private $container;

	/* --- Public Methods--- */


	/**
	 * ComponentTreePanel constructor.
	 * @param Container $container
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}


	/**
	 * Returns link to open file in editor
	 *
	 * @param string $file
	 * @param int $line
	 * @return string
	 */
	public static function buildEditorLink($file, $line)
	{
		return strtr(Debugger::$editor, ['%file' => urlencode(realpath($file)), '%line' => $line]);
	}


	/**
	 * Get part of source file
	 *
	 * @param string $fileName
	 * @param int $startLine
	 * @param int $endLine
	 * @return LimitIterator
	 */
	public static function getSource($fileName, $startLine = null, $endLine = null)
	{
		static $sources = []; /* --- simple caching --- */

		if (!in_array($fileName, $sources, true)) {
			$txt = file_get_contents($fileName);
			$txt = str_replace(["\r\n", "\r"], "\n", $txt);
			$sources[$fileName] = explode("\n", $txt);
		}

		$iterator = new LimitIterator(new ArrayIterator($sources[$fileName]), $startLine, $endLine - $startLine + 1);
		return $iterator;
	}


	/**
	 * Highlights PHP source code of object
	 *
	 * @param mixed $object
	 * @return string
	 */
	public static function highlight($object)
	{

		if (!($object instanceOf Method || $object instanceof ClassType)) {
			$object = self::getReflection($object);
		}

		$sourceLines = static::getSource($object->getFileName(), $object->getStartLine() - 1, $object->getEndLine() - 1);

		$phpDocTxt = $object->getDocComment();
		$phpDoc = [];
		if (strlen($phpDocTxt) > 0) {
			$phpDoc = explode("\n", $phpDocTxt);
		}
		$phpDoc = new ArrayIterator($phpDoc);
		$lines = new \AppendIterator();

		$lines->append($phpDoc);
		$lines->append($sourceLines);

		$source = '';

		foreach ($lines as $line) {
			$source .= $line . "\n";
		}
		$source = str_replace(
			['<span style="color: rgb(0,0,187)">&lt;?php<br />&nbsp;&nbsp;&nbsp;&nbsp;</span>', '<span style="color: rgb(0,0,187)">&lt;?php<br /></span>'],
			'',
			highlight_string("<?php\n" . $source, true)
		);
		$source = '&nbsp;&nbsp;&nbsp;' . $source;
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
	public static function filterMethods($object, $pattern, $hideMethods, $inherited)
	{
		$reflection = ClassType::from($object);
		$methods = $reflection->getMethods();
		$filtered = [];
		/** @var Method $method */
		foreach ($methods as $method) {
			if (!preg_match($pattern, $method->getName(), $matches)) {
				continue;
			}
			if ($inherited === false && $method->class !== get_class($object)) {
				continue;
			}
			if (in_array($method->getName(), $hideMethods, true)) {
				continue;
			}
			$filtered[] = $method;
		}
		return $filtered;
	}


	/**
	 * Returns information about parameters (actual and persistent)
	 * @param $presenterComponent
	 * @return array
	 */
	public static function getParametersInfo(PresenterComponent $presenterComponent)
	{
		$params = [];

		$normalParameters = $presenterComponent->getParameters();
		ksort($normalParameters);
		foreach ($normalParameters as $name => $value) {
			$params[$name] = [
				'value' => $value,
				'persistent' => false,
				'meta' => null,
			];
		}

		$persistentParameters = $presenterComponent->getReflection()->getPersistentParams();
		ksort($persistentParameters);
		foreach ($persistentParameters as $name => $meta) {
			$params[$name] = [
				'value' => $presenterComponent->{$name},
				'persistent' => true,
				'meta' => $meta,
			];
		}

		return $params;
	}


	/**
	 * Returns templates used when rendering object
	 * @param $object
	 * @return array
	 */
	public static function getRenderedTemplates($object)
	{
		$arr = [];
		/** @var array $info */
		foreach (DebugTemplate::$templatesRendered as $info) {
			if ($info['template']->control === $object) {
				$arr[] = $info;
			}
		}
		return $arr;
	}


	/**
	 * @param $object
	 * @return int|null
	 */
	public static function getOutputCount($object)
	{
		$templates = self::getRenderedTemplates($object);
		$bytes = null;
		foreach ($templates as $template) {
			$bytes = strlen($template['rendered']);
		}
		return $bytes;
	}


	/**
	 * @param $path
	 * @param null $tag
	 * @return string
	 */
	public static function relativePath($path, $tag = null)
	{
		$relative = FileHelpers\File::getRelative($path, static::$appDir);
		if ($tag) {
			$relative = Strings::replace($relative, '#(' . pathinfo($relative, PATHINFO_FILENAME) . ')(\.(latte|phtml))#', "<$tag>\$1</$tag>\$2");
		}
		return $relative;
	}


	/**
	 * @param array $values
	 * @param array $blacklist
	 * @param array|null $whitelist
	 * @return array
	 */
	public static function blacklistArray($values, array $blacklist, array $whitelist = null)
	{
		$filtered = [];
		foreach ($values as $key => $value) {
			if (!in_array($key, $blacklist, true)) {
				if ($whitelist === null || in_array($key, $whitelist, true)) {
					$filtered[$key] = $value;
				}
			}
		}
		return $filtered;
	}


	/**
	 * @param $object
	 * @return mixed|string
	 */
	static public function dumpToHtmlCached($object)
	{
		if (is_object($object)) {
			$id = spl_object_hash($object);
			if (!isset(self::$_dumpCache[$id])) {
				self::$_dumpCache[$id] = self::dumpToHtml($object);
			}
			return self::$_dumpCache[$id];
		}
		return self::dumpToHtml($object);
	}


	/**
	 * @param $object
	 * @return string
	 */
	static public function dumpToHtml($object)
	{
		return Dumper::toHtml($object, [Dumper::DEPTH => 3]);
	}


	/**
	 * @param $object
	 * @return string
	 */
	static public function simpleDump($object)
	{
		ob_start();
		var_dump($object);
		return trim(ob_get_clean());
	}


	/**
	 * @param string|\stdClass $object
	 * @return \ReflectionClass
	 */
	public static function getReflection($object)
	{
		$key = is_object($object) ? get_class($object) : $object;
		if (!array_key_exists($key, self::$reflectionCache)) {
			self::$reflectionCache[$key] = new \ReflectionClass($object);
		}
		return self::$reflectionCache[$key];
	}


	/**
	 * @param $object
	 * @param $propertyName
	 * @param null $parentClass
	 * @return mixed
	 */
	public static function readPrivateProperty($object, $propertyName, $parentClass = null)
	{
		$class = $parentClass ?: get_class($object);
		$reflection = self::getReflection($class);
		$property = $reflection->getProperty($propertyName);
		$property->setAccessible(true);
		return $property->getValue($object);
	}


	public function register()
	{
		Debugger::getBar()->addPanel($this);
		if (static::$appDir === null) {
			static::$appDir = FileHelpers\File::simplifyPath(__DIR__ . '/../../../../app');
		}
		$application = $this->container->getService('application');
		$application->onResponse[] = [$this, 'getResponseCb'];
	}


	/**
	 * @param $application
	 * @param $response
	 * @internal
	 */
	public function getResponseCb(/** @noinspection PhpUnusedParameterInspection */
		$application, $response)
	{
		$this->response = $response;
	}


	/**
	 * Renders HTML code for custom tab.
	 * @return string
	 * @see IDebugPanel::getTab()
	 */
	public function getTab()
	{
		return '<img src="data:image/gif;base64,R0lGODlhEAAQAMQWACRITrPgAKXFXgQ2BBA+EP/ndv/6u8z/AAo6Cgk6CgBRHigVAXCjAP/NJD4fAFwuAP+xEeDg4EAcAQFaUgBIHgAuOwFcdwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAABYALAAAAAAQABAAAAVToGUBUgmIaCpKhlFIaswWjROrEg0td0o+i1MvVUlNKMhJrIiiRCIBwrJCrSgiB0FimaIEBIwBFzUhIAZKFXNINCIp6XbzGZ1Wr9kt2wsWs8tnaSEAOw%3D%3D">Tree';
	}


	/**
	 * Renders HTML code for custom panel.
	 * @return string
	 * @see IDebugPanel::getPanel()
	 */
	public function getPanel()
	{
		$presenter = $this->container->getByType(Application::class)->getPresenter();
		if ($presenter === null || $this->response instanceOf ForwardResponse || $this->response instanceOf RedirectResponse) {
			return '';
		}

		if (static::$cache) {
			/** @var IStorage $storage */
			$storage = $this->container->getByType(IStorage::class);
			$cache = new Cache($storage, 'Debugger.Panels.ComponentTree');
		} else {
			$cache = null;
		}

		/** @var TemplateFactory $factory */
		$factory = $this->container->getService('latte.templateFactory');
		$template = $factory->createTemplate();

		$template->setFile(__DIR__ . '/bar.latte');
		$template->add('presenter', $presenter);
		$template->add('rootComponent', $presenter);
		$template->add('wrap', static::$wrap);
		$template->add('cache', $cache);
		$template->add('dumps', static::$dumps);

		$template->add('parametersOpen', static::$parametersOpen);
		$template->add('presenterOpen', static::$presenterOpen);
		$template->add('showSources', static::$showSources);
		$template->add('omittedVariables', static::$omittedTemplateVariables);
		$template->add('helpers', $this);

		ob_start();
		$template->render();
		return ob_get_clean();
	}


	/**
	 * @param IComponent $object
	 * @return bool
	 */
	public function isPersistent(IComponent $object)
	{
		static $persistentParameters = null;
		if ($persistentParameters === null) {
			/** @noinspection PhpUndefinedMethodInspection */
			$presenter = $object instanceOf Presenter ? $object : $object->lookupPath(IPresenter::class, false);
			if ($presenter) {
				$persistentParameters = $presenter::getPersistentComponents();
			}
		}
		if (is_array($persistentParameters)) {
			return in_array($object->getName(), $persistentParameters, true);
		}
		return false;
	}


}
