<?php

namespace Bitmarshals\InstantUssd;

use Exception;
use Bitmarshals\InstantUssd\UssdResponseGenerator;
use Bitmarshals\InstantUssd\UssdService;
use Zend\Http\PhpEnvironment\Response;
use Zend\EventManager\EventManager;
use Zend\ServiceManager\ServiceLocatorInterface;
use Bitmarshals\InstantUssd\Mapper\UssdMenusServedMapper;
use Bitmarshals\InstantUssd\Mapper\UssdLoopMapper;
use Bitmarshals\InstantUssd\Mapper\SkippableUssdMenuMapper;
use Zend\ServiceManager\ServiceManager;
use Bitmarshals\InstantUssd\UssdMenuItem;
use Zend\EventManager\EventManagerInterface;
use Bitmarshals\InstantUssd\UssdEvent;

/**
 * Description of InstantUssd
 *
 * @author David Bwire
 */
class InstantUssd {

    /**
     *
     * @var UssdService 
     */
    protected $ussdService;

    /**
     *
     * @var UssdResponseGenerator 
     */
    protected $ussdResponseGenerator;

    /**
     *
     * @var ServiceLocatorInterface 
     */
    protected $serviceLocator;

    /**
     *
     * @var array 
     */
    protected $instantUssdConfig = [];

    /**
     *
     * @var object
     */
    protected $initializer;

    /**
     *
     * @var object 
     */
    protected $logger;

    /**
     *
     * @var string 
     */
    protected $ussdEventListener;

    /**
     *
     * @var EventManager 
     */
    protected $eventManager;

    /**
     *
     * @var array 
     */
    protected $ussdMenusConfig = [];

    /**
     * 
     * @param array $instantUssdConfig
     * @param object $initializer The class that instantiates this class (InstantUssd)
     *               The $initializer is useful when you'd like to extract dependancies
     *               for use in a ussd event listener
     * 
     * @throws Exception
     */
    public function __construct(array $instantUssdConfig, $initializer) {
        if (gettype($initializer) != 'object') {
            throw new Exception('The initializer must be an object');
        }
        $this->initializer       = $initializer;
        $this->instantUssdConfig = $instantUssdConfig;
        $ussdResponseGenerator   = new UssdResponseGenerator();

        $ussdMenusConfig   = $instantUssdConfig['ussd_menus'];
        $ussdEventListener = $instantUssdConfig['ussd_event_listener'];
        $this->setUssdEventListener($ussdEventListener);

        $this->ussdMenusConfig       = $ussdMenusConfig;
        $this->ussdResponseGenerator = $ussdResponseGenerator;

        // IMPORTANT - force $this->eventManager to hold a configured EventManager instance
        $this->getEventManager();
    }

    /**
     * 
     * @param array $ussdData
     * @param string $errorMessage
     * @return Response
     */
    public function showError($ussdData, $errorMessage = null) {
        $ussdData['error_message'] = $errorMessage;
        $results                   = $this->eventManager->triggerUntil(function($result) {
            return ($result instanceof Response);
        }, '_error_', $this, $ussdData);
        return $results->last();
    }

    /**
     * 
     * @param array $ussdData
     * @return Response|void
     */
    public function exitUssd(array $ussdData) {

        // trigger until we get a Response
        $results = $this->eventManager->triggerUntil(function($result) {
            return ($result instanceof Response);
        }, '_exit_', $this, $ussdData);

        if ($results->stopped()) {
            return $results->last();
        } else {
            // @todo log error
            return $this->showError($ussdData);
        }
    }

    /**
     * 
     * @param array $ussdData
     * @param string $homePageMenuId
     * @return Response
     */
    public function showHomePage(array $ussdData, $homePageMenuId) {
        // enforce home page format
        if (!(substr($homePageMenuId, 0, 5) === 'home_')) {
            throw new Exception("Home pages must begin with 'home_'");
        }
        $results = $this->eventManager->trigger($homePageMenuId, $this, $ussdData);

        // get response from 1st listener
        $response = $results->first();

        if ($response instanceof Response) {
            return $response;
        } else {
            // @todo log error
            return $this->showError($ussdData);
        }
    }

    /**
     * 
     * @param array $ussdData
     * @param string $nextScreenId
     * @return Response
     */
    public function showNextScreen(array $ussdData, $nextScreenId) {
        // with the next_screen
        // disable incoming cycle
        $ussdData['is_incoming_data'] = false;
        // don't use triggerUntil as it will prevent tracking
        // try and render next menu
        $outGoingCycleResults         = $this->eventManager->trigger($nextScreenId, $this, $ussdData);
        $outGoingCycleResult          = $outGoingCycleResults->first();
        // Try and find a response that's not a skippable
        while ($outGoingCycleResult instanceof UssdMenuItem) {
            // get the next menu
            $nextScreenId         = $outGoingCycleResult->getNextMenuId();
            $outGoingCycleResults = $this->eventManager->trigger($nextScreenId, $this, $ussdData);
            $outGoingCycleResult  = $outGoingCycleResults->first();
        }
        //--- send data to user
        if ($outGoingCycleResult instanceof Response) {
            return $outGoingCycleResult;
        } else {
            return $this->showError($ussdData, "Error. Next screen could not be loaded.");
        }
    }

    /**
     * 
     * @param array $ussdData
     * @return mixed Response|false
     */
    public function goBack(array $ussdData) {
        // try and find the previous menu
        $results      = $this->eventManager->trigger('_go_back_.pre', $this, $ussdData);
        // retreive first data
        $previousMenu = $results->first();

        // try and show previous page
        if (!empty($previousMenu)) {
            // explicitly disable tracking
            // this allows for the user to go back indefinitely
            $ussdData['disable_tracking'] = true;
            $ussdData['is_incoming_data'] = false;

            // trigger event to show previous menu
            $results = $this->eventManager->trigger($previousMenu, $this, $ussdData);

            // get response from 1st listener
            $response = $results->first();
            if ($response instanceof Response) {
                return $response;
            } else {
                // @todo log error
                return $this->showError($ussdData);
            }
        }
        return false;
    }

    /**
     * This method provides UssdEvent with a way to access the service manager
     * 
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator() {
        $serviceLocator = $this->serviceLocator;
        if (!$serviceLocator instanceof ServiceManager) {
            if (!array_key_exists('service_manager', $this->instantUssdConfig)) {
                throw new Exception('service_manager key missing on instant_ussd config.');
            }
            $serviceManagerConfig                       = $this->instantUssdConfig['service_manager'];
            // attach config for use by dbAdapter
            $serviceManagerConfig['services']['config'] = $this->instantUssdConfig;
            $this->serviceLocator                       = new ServiceManager($serviceManagerConfig);
        }
        return $this->serviceLocator;
    }

    /**
     * 
     * @return UssdMenusServedMapper
     */
    public function getUssdMenusServedMapper() {
        return $this->getServiceLocator()
                        ->get(UssdMenusServedMapper::class);
    }

    /**
     * 
     * @return UssdLoopMapper
     */
    public function getUssdLoopMapper() {
        return $this->getServiceLocator()
                        ->get(UssdLoopMapper::class);
    }

    /**
     * 
     * @return SkippableUssdMenuMapper
     */
    public function getSkippableUssdMenuMapper() {
        return $this->getServiceLocator()
                        ->get(SkippableUssdMenuMapper::class);
    }

    /**
     * 
     * @return UssdService
     */
    public function getUssdService($ussdText, $separator = "*") {

        if (!$this->ussdService) {
            $ussdService       = new UssdService($ussdText, $separator);
            $this->ussdService = $ussdService;
        }
        return $this->ussdService;
    }

    /**
     * 
     * @return UssdResponseGenerator
     */
    public function getUssdResponseGenerator() {
        return $this->ussdResponseGenerator;
    }

    /**
     * Used to access functionality from the class that initializes InstantUssd
     * This method will generally be called from a UssdEventListener
     * 
     * $initializer = $ussdEvent
     *                  ->getTarget()->getInitializer();
     * 
     * @return object
     */
    public function getInitializer() {
        return $this->initializer;
    }

    /**
     * Get attached logger or try to retreive one from ServiceManager
     * 
     * @return object
     */
    public function getLogger() {

        if (!$this->logger) {
            $sl           = $this->getServiceLocator();
            // check we were able to get a service locator
            $this->logger = $sl->get('logger');
        }
        return $this->logger;
    }

    /**
     * Directly attach your own logger
     * 
     * @param object $logger
     * @return $this
     */
    public function setLogger($logger) {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 
     * @return EventManagerInterface
     */
    public function getEventManager() {
        if (!$this->eventManager) {
            if (empty($this->ussdEventListener)) {
                throw new Exception('UssdEventListener class not set.');
            }
            $class = $this->ussdEventListener;
            $this->setEventManager(new EventManager(new $class($this->ussdMenusConfig)));
        }
        return $this->eventManager;
    }

    /**
     * 
     * @param EventManagerInterface $eventManager
     */
    protected function setEventManager(EventManagerInterface $eventManager) {
        $eventManager->setIdentifiers([__NAMESPACE__]);
        // set a custom ussd event
        $eventManager->setEventPrototype(new UssdEvent('ussd'));
        $this->eventManager = $eventManager;
    }

    /**
     * 
     * @param string $ussdEventListener
     */
    protected function setUssdEventListener($ussdEventListener) {
        if (!(gettype($ussdEventListener) === 'string')) {
            throw new \Exception('ussd_event_listener should be a string.');
        }
        $this->ussdEventListener = $ussdEventListener;
    }

    /**
     * get last served menu_id from database
     * 
     * @param array $ussdData
     * @return mixed string|null
     */
    public function retrieveLastServedMenuId(array $ussdData) {
        $resultsMenuId    = $this->eventManager
                ->trigger('_retreive_last_served_menu_', $this, $ussdData);
        $lastServedMenuId = $resultsMenuId->first();
        return $lastServedMenuId;
    }

    /**
     * 
     * @param array $ussdData
     * @return mixed array|false|null
     */
    public function retrieveMenuConfig(array $ussdData) {
        $resultsMenuConfig = $this->eventManager
                ->trigger('_retreive_menu_config_', $this, $ussdData);
        $menuConfig        = $resultsMenuConfig->first();
        return $menuConfig;
    }

    /**
     * Send data for processing by the listener and returns pointer to the next screen
     * 
     * @param string $lastServedMenuId
     * @param array $ussdData
     * @return mixed boolean|string pointer to the next screen or false
     */
    public function processIncomingData($lastServedMenuId, array $ussdData) {

        // activate incoming data state
        $ussdData['is_incoming_data'] = true;
        $incomingCycleResults         = $this->eventManager->triggerUntil(function ($result) {
            // data was processed and we should expect a pointer to the next menu
            return ($result instanceof UssdMenuItem);
        }, $lastServedMenuId, $this, $ussdData);
        // check if we missed a pointer to the next screen
        if (!$incomingCycleResults->stopped()) {
            return false;
        }

        // try and render the pointer/next screen
        $ussdMenuItem              = $incomingCycleResults->last();
        $isResetToPreviousPosition = $ussdMenuItem->isResetToPreviousPosition();
        // retreive our next menu_id
        $nextScreenId              = $ussdMenuItem->getNextMenuId();
        // check if it's a parent node reset
        if ($isResetToPreviousPosition) {
            $this->getUssdMenusServedMapper()
                    ->resetMenuVisitHistoryToPreviousPosition($ussdParams['sessionId'], $nextScreenId);
        }
        // pointer to the next screen
        return $nextScreenId;
    }

    /**
     * Check if we should continue looping a loopset or exit. It should be 
     * called before determining next menu. Should be called at the very top of
     * your listener
     * 
     * @param array $menuConfig
     * @param UssdEvent $e
     * @return boolean
     */
    public function shouldStopLooping(array &$menuConfig, UssdEvent $e) {

        $shouldStopLooping = true;
        if (!array_key_exists('is_loop_end', $menuConfig) ||
                empty($menuConfig['is_loop_end'])) {
            return true;
        }
        // we must have loopset_name to proceed
        if (!array_key_exists('loopset_name', $menuConfig) ||
                empty($menuConfig['loopset_name'])) {
            throw new Exception('loopset_name not set for menu_id '
            . $e->getName() . ' ' . __FILE__ . ':' . __LINE__);
        }
        $loopsetName    = $menuConfig['loopset_name'];
        $sessionId      = $e->getParam('session_id');
        $latestResponse = $e->getParam('latest_response');

        $ussdLoopMapper    = $this->getUssdLoopMapper();
        $shouldStopLooping = $ussdLoopMapper->shouldStopLooping(
                $loopsetName, $sessionId, $menuConfig);

        if (!$shouldStopLooping
                // prevent double increment due to double pass per listener
                // Track count on the contains data face
                && ($e->containsIncomingData())
                // lastly prevent increment in loops done when user goes back
                // this happens because event will be refired on post go back
                && (trim($latestResponse) !== UssdService::GO_BACK_KEY)) {
            $ussdLoopMapper->incrementLoops($loopsetName, $sessionId);
            // check AGAIN if we should stop looping
            // informs determineNextMenu
            //--- helpful incase of skippable menu
            $shouldStopLooping = $ussdLoopMapper->shouldStopLooping(
                    $loopsetName, $sessionId, $menuConfig);
        }
        return $shouldStopLooping;
    }

}
