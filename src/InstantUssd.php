<?php

namespace Bitmarshals\InstantUssd;

use Exception;
use Bitmarshals\InstantUssd\UssdResponseGenerator;
use Bitmarshals\InstantUssd\UssdService;
use Zend\Http\PhpEnvironment\Response;
use Zend\EventManager\EventManager;
use Zend\ServiceManager\ServiceLocatorInterface;
use Bitmarshals\InstantUssd\Mapper\UssdMenusServedMapper;
use Zend\ServiceManager\ServiceManager;

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
     * Array of pre-trimmed ussd values that are devoid of navigation 
     * text eg 0,000,00 and 98
     * 
     * @var array
     */
    protected $aNonExtraneousUssdValues = [];

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

    public function __construct(array $instantUssdConfig) {

        $this->instantUssdConfig     = $instantUssdConfig;
        $ussdResponseGenerator       = new UssdResponseGenerator();
        $ussdService                 = new UssdService($this->getServiceLocator());
        $this->ussdService           = $ussdService;
        $this->ussdResponseGenerator = $ussdResponseGenerator;
    }

    /**
     * 
     * @param array $ussdData
     * @param EventManager $eventManager
     * @return Response|void
     */
    public function exitUssd(array $ussdData, EventManager $eventManager) {

        // trigger until we get a Response
        $results = $eventManager->triggerUntil(function($result) {
            return ($result instanceof Response);
        }, '_exit_', $this, $ussdData);

        if ($results->stopped()) {
            return $results->last();
        } else {
            // @todo log error
            $results = $eventManager->triggerUntil(function($result) {
                return ($result instanceof Response);
            }, '_error_', $this, $ussdData);
            return $results->last();
        }
    }

    /**
     * 
     * @param array $ussdData
     * @param EventManager $eventManager
     * @param string $homePageMenuId
     * @return Response
     */
    public function showPage(array $ussdData, EventManager $eventManager, $homePageMenuId = 'home_instant_ussd') {

        $results = $eventManager->trigger($homePageMenuId, $this, $ussdData);

        // get response from 1st listener
        $response = $results->first();

        if ($response instanceof Response) {
            return $response;
        } else {
            // @todo log error
            $results = $eventManager->triggerUntil(function($result) {
                return ($result instanceof Response);
            }, '_error_', $this, $ussdData);

            return $results->last();
        }
    }

    /**
     * 
     * @param array $ussdData
     * @param EventManager $eventManager
     * @return mixed Response|false
     */
    public function goBack(array $ussdData, EventManager $eventManager) {
        // try and find the previous menu
        $results      = $eventManager->trigger('_go_back_.pre', $this, $ussdData);
        // retreive first data
        $previousMenu = $results->first();

        // try and show previous page
        if (!empty($previousMenu)) {
            // explicitly disable tracking
            // this allows for the user to go back indefinitely
            $ussdData['disable_tracking'] = true;
            $ussdData['is_incoming_data'] = false;

            // trigger event to show previous menu
            $results = $eventManager->trigger($previousMenu, $this, $ussdData);

            // get response from 1st listener
            $response = $results->first();
            if ($response instanceof Response) {
                return $response;
            } else {
                // @todo log error
                $results = $eventManager->triggerUntil(function($result) {
                    return ($result instanceof Response);
                }, '_error_', $this, $ussdData);

                return $results->last();
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
            $serviceManagerConfig = $this->instantUssdConfig['service_manager'];
            // attach config for use by dbAdapter
            $serviceManagerConfig['invokables']['config'] = $serviceManagerConfig;
            $this->serviceLocator = new ServiceManager($serviceManagerConfig);
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
     * @return UssdService
     */
    public function getUssdService(): UssdService {
        return $this->ussdService;
    }

    /**
     * 
     * @return UssdResponseGenerator
     */
    public function getUssdResponseGenerator(): UssdResponseGenerator {
        return $this->ussdResponseGenerator;
    }

    /**
     * Array of ussd responses without extraneous values eg navigation codes
     * (0, 00, 000) and pagination codes (98)
     * 
     * @return array
     */
    public function getANonExtraneousUssdValues() {
        return $this->aNonExtraneousUssdValues;
    }

    /**
     * 
     * @param array $aNonExtraneousUssdValues
     * @return $this
     */
    public function setANonExtraneousUssdValues(array $aNonExtraneousUssdValues) {
        $this->aNonExtraneousUssdValues = $aNonExtraneousUssdValues;
        return $this;
    }

}
