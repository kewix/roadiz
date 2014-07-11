<?php 

namespace RZ\Renzo\Core;

use RZ\Renzo\Inheritance\Doctrine\DataInheritanceEvent;
use RZ\Renzo\Core\Routing\MixedUrlMatcher;
use RZ\Renzo\Core\Bags\SettingsBag;

use Symfony\Component\Console\Application;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManager;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Routing;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Stopwatch\Stopwatch;


use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\FirewallMap;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
/**
* 
*/
class Kernel {

	private static $instance = null;

	private $em =           null;
	private $debug =        true;
	private $backendDebug = false;
	private $config =       null;

	protected $httpKernel =          null;
	protected $request =             null;
	protected $requestContext =      null;
	protected $response =            null;
	protected $httpUtils =           null;
	protected $context =             null;
	protected $matcher =             null;
	protected $resolver =            null;
	protected $dispatcher =          null;
	protected $urlGenerator =        null;
	protected $stopwatch =           null;
	protected $securityContext =     null;
	protected $backendClass =        null;
	protected $frontendClass =       null;
	protected $rootCollection =      null;


	private final function __construct() {

		$this->stopwatch = new Stopwatch();
		$this->stopwatch->start('global');
		$this->rootCollection = new RouteCollection();

		$this->request = Request::createFromGlobals();
		$this->requestContext = new Routing\RequestContext($this->getResolvedBaseUrl());

	}

	/**
	 * @return array
	 */
	public function getConfig() {
	    return $this->config;
	}
	/**
	 * @param array $newconfig
	 */
	public function setConfig($config) {
	    $this->config = $config;
	    return $this;
	}

	/**
	 * Get application debug status.
	 * @return boolean
	 */
	public function isDebug() {
		return $this->debug;
	}
	/**
	 * Get backend application debug status
	 * @return boolean
	 */
	public function isBackendDebug()
	{
		return $this->backendDebug;
	}

	/**
	 * Return unique instance of Kernel
	 * @return Kernel
	 */
	public static function getInstance(){

		if (static::$instance === null) {
			static::$instance = new Kernel();
		}

		return static::$instance;
	}

	/**
	 * @return Symfony\Component\Stopwatch\Stopwatch
	 */
	public function getStopwatch() { return $this->stopwatch; }

	/**
	 * Set kernel Doctrine entity manager and 
	 * prepare custom data inheritance for Node system
	 * 
	 * @param EntityManager $em 
	 */
	public function setEntityManager(EntityManager $em)
	{
		$this->em = $em;

		$evm = $this->em->getEventManager();
 
        /*
         * Create dynamic dicriminator map for our Node system
         */
        $inheritableEntityEvent = new DataInheritanceEvent();
        $evm->addEventListener(Events::loadClassMetadata, $inheritableEntityEvent);

		return $this;
	}
	/**
	 * 
	 * @return Doctrine\ORM\EntityManager
	 */
	public function getEntityManager()
	{
		return $this->em;
	}
	/**
	 * Alias for Kernel::getEntityManager method
	 * @return Doctrine\ORM\EntityManager
	 */
	public function em() { return $this->em; }

	/**
	 * 
	 * @return RZ\Renzo\Core\Kernel $this
	 */
	public function runConsole()
	{
		$application = new Application('Renzo Console Application', '0.1');
		$application->add(new \RZ\Renzo\Console\TranslationsCommand);
		$application->add(new \RZ\Renzo\Console\NodeTypesCommand);
		$application->add(new \RZ\Renzo\Console\NodesCommand);
		$application->add(new \RZ\Renzo\Console\SchemaCommand);
		$application->add(new \RZ\Renzo\Console\ThemesCommand);
		$application->add(new \RZ\Renzo\Console\InstallCommand);
		
		$application->run();

		$this->stopwatch->stop('global');
		return $this;
	}
	/**
	 * 
	 * Run main HTTP application
	 * 
	 * @return RZ\Renzo\Core\Kernel $this
	 */
	public function runApp()
	{
		$this->dispatcher = new EventDispatcher();
		$this->resolver =   new ControllerResolver();
		$this->httpKernel = new HttpKernel($this->dispatcher, $this->resolver);
		
		$this->prepareRequestHandling();

		try{
			/*
			 * ----------------------------
			 * Main Framework handle call
			 * ----------------------------
			 */
			$this->response = $this->httpKernel->handle( $this->request );
			$this->response->send();
			
			$this->httpKernel->terminate( $this->request, $this->response );
		}
		catch(\Symfony\Component\Routing\Exception\ResourceNotFoundException $e){
			echo $e->getMessage().PHP_EOL;
		}
		catch(\LogicException $e){
			echo $e->getMessage().PHP_EOL;
		}
		catch(\PDOException $e){
			echo $e->getMessage().PHP_EOL;
		}

		return $this;
	}
	/**
	 * Run first installation interface
	 * 
	 * @return RZ\Renzo\Core\Kernel $this
	 */
	public function runSetup()
	{
		return $this;
	}

	/**
	 * 
	 * Prepare backend and frontend routes and logic
	 * @return boolean
	 */
	private function prepareRequestHandling()
	{	
		$this->debug = 			(boolean)SettingsBag::get('debug');
		$this->backendDebug = 	(boolean)SettingsBag::get('backend_debug');
		$this->backendClass = $this->getBackendClass();
		$this->frontendClass = $this->getFrontendClass();


		/*
		 * Add Assets controller routes
		 */
		$this->rootCollection->addCollection(\RZ\Renzo\CMS\Controllers\AssetsController::getRoutes());

		/*
		 * Add Backend routes
		 */
		$beClass = $this->backendClass;
		$cmsCollection = $beClass::getRoutes();
		if ($cmsCollection !== null) {
			
			$this->rootCollection->addCollection($cmsCollection, '/rz-admin', array('_scheme' => 'https'));
		}

		/*
		 * Add Frontend routes
		 */
		$feClass = $this->frontendClass;
		$feCollection = $feClass::getRoutes();
		if ($feCollection !== null) {
			
			$this->rootCollection->addCollection($feCollection);
		}

		$matcher = new MixedUrlMatcher($this->rootCollection, $this->requestContext);
		$this->urlGenerator = new UrlGenerator($this->rootCollection, $this->requestContext);
		$this->httpUtils = new HttpUtils($this->urlGenerator, $matcher);

		$this->dispatcher->addSubscriber(new RouterListener($matcher));

		/*
		 * Security
		 */
		$map = new FirewallMap();
		// Register back-end security scheme
		$beClass::appendToFirewallMap( $map, $this->httpKernel, $this->httpUtils, $this->dispatcher );
		// Register front-end security scheme
		$feClass::appendToFirewallMap( $map, $this->httpKernel, $this->httpUtils, $this->dispatcher );

		$firewall = new Firewall($map, $this->dispatcher);
		$this->dispatcher->addListener(
		    KernelEvents::REQUEST,
		    array($firewall, 'onKernelRequest')
		);

		/*
		 * If debug, alter HTML responses to append Debug panel to view
		 */
		if ($this->isDebug()) {
			$this->dispatcher->addSubscriber(new \RZ\Renzo\Core\Utils\DebugPanel());
		}
	}

	/**
	 * Get frontend app controller full-qualified classname
	 * 
	 * Return 'RZ\Renzo\CMS\Controllers\FrontendController' if none found in database
	 * @return string
	 */
	private function getFrontendClass()
	{
		$theme = $this->em()
			->getRepository('RZ\Renzo\Core\Entities\Theme')
			->findOneBy(array('available'=>true, 'backendTheme'=>false));

		if ($theme !== null) {
			return $theme->getClassName();
		}

		return 'RZ\Renzo\CMS\Controllers\FrontendController';
	}
	/**
	 * Get backend app controller full-qualified classname
	 * 
	 * Return 'RZ\Renzo\CMS\Controllers\BackendController' if none found in database
	 * 
	 * @return string
	 */
	private function getBackendClass()
	{
		$theme = $this->em()
			->getRepository('RZ\Renzo\Core\Entities\Theme')
			->findOneBy(array('available'=>true, 'backendTheme'=>true));

		if ($theme !== null) {
			return $theme->getClassName();
		}

		return 'RZ\Renzo\CMS\Controllers\BackendController';
	}

	/**
	 * @return Symfony\Component\HttpFoundation\Request
	 */
	public function getRequest()
	{
		return $this->request;
	}

	/**
	 * 
	 * @return Symfony\Component\Routing\Generator\UrlGenerator
	 */
	public function getUrlGenerator()
	{
		return $this->urlGenerator;
	}

	/**
	 * Resolve current front controller URL
	 * 
	 * This method is the base of every URL building methods in RZ-CMS. 
	 * Be careful with handling it.
	 * 
	 * @return string 
	 */
	private function getResolvedBaseUrl()
	{
		if (isset($_SERVER["SERVER_NAME"])) {
			$url = pathinfo($_SERVER['PHP_SELF']);

			// Protocol
			$pageURL = 'http';
			if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
			$pageURL .= "://";
			// Port
			if (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80") {
				$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"];
			} else {
				$pageURL .= $_SERVER["SERVER_NAME"];
			}
			// Non root folder
			if (!empty($url["dirname"]) && $url["dirname"] != '/') {
				$pageURL .= $url["dirname"];
			}

			return $pageURL;
		}
		else {
			return false;
		}
	}

	/**
	 * Execute after kernel response
	 * 
	 * Build debug panel if debug is ON
	 * 
	 * @param  FilterResponseEvent $event
	 * @return void
	 */
	public function onKernelResponse(FilterResponseEvent $event)
	{
	   	if ($this->isDebug()) {

	    	$response = $event->getResponse();

	    	if (strpos($response->getContent(), '</body>') !== false) {
	    		$sw = $this->stopwatch->stop('global');
	    		$debug = $sw->getCategory().' : '.$sw->getDuration().'ms - '.$sw->getMemory()/1000000.0.'Mo';

	    		$content = str_replace('</body>', $debug."</body>", $response->getContent());

	    		$response->setContent($content);
	    	}
	   	}
	}
}