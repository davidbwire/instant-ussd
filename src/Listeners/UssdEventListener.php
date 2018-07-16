<?php

namespace Bitmarshals\InstantUssd\Listeners;

use Bitmarshals\InstantUssd\UssdEvent;
use Bitmarshals\InstantUssd\UssdResponseGenerator;
use Bitmarshals\InstantUssd\UssdMenuItem;
use Bitmarshals\InstantUssd\Response;
use Exception;
use GuzzleHttp\Client;
use ArrayObject;

/**
 * Description of UssdEventListener
 * 
 * Extend this class and override some of it's methods to quickly build custom ussd
 * event listeners.
 *
 * @todo Rename to ScreenListener
 * @author David Bwire
 */
abstract class UssdEventListener {

    /**
     *
     * @var ArrayObject 
     */
    protected $menuConfig;

    /**
     *
     * @var ArrayObject 
     */
    protected static $ussdMenusConfig;

    /**
     *
     * @var string 
     */
    protected $latestResponse;

    /**
     *
     * @var string 
     */
    protected $lastServedMenu;

    /**
     *
     * @var UssdEvent 
     */
    protected $ussdEvent;

    /**
     *
     * @var UssdResponseGenerator 
     */
    protected $ussdResponseGenerator;

    /**
     *
     * @var UssdMenuItem 
     */
    protected $alternativeScreen;

    /**
     * 
     * @param UssdEvent $e
     * @param ArrayObject $ussdMenusConfig
     */
    public function __construct(UssdEvent $e, ArrayObject $ussdMenusConfig) {
        $this->ussdEvent = $e;
        $this->ussdMenusConfig = $ussdMenusConfig;
        $this->menuConfig = $ussdMenusConfig[$e->getName()];
        $this->latestResponse = $e->getLatestResponse();
        $this->lastServedMenu = $e->getName();
        $this->ussdResponseGenerator = new UssdResponseGenerator();
    }

    /**
     * 
     * @param boolean $continueUssdHops
     * @param boolean $appendNavigationText
     * @return UssdMenuItem|Response
     */
    public function onTrigger($continueUssdHops = true, $appendNavigationText = true) {
        $e = $this->ussdEvent;
        // Override isSkippableScreen method, if we're dealing with a skippable menu
        if ($this->isSkippableScreen()) {
            // stop event propagation so that navigation history is not captured
            $this->ussdEvent->stopPropagation(true);
            $alternativeScreen = $this->getAlternativeScreen();
            if ($alternativeScreen instanceof UssdMenuItem) {
                // return $alternativeScreen thus skipping default screen
                return $alternativeScreen;
            } else {
                // return default screen
                return $this->nextMenu();
            }
        }
        if (!$e->containsIncomingData()) {
            $e->attachDynamicErrors($this->menuConfig);
            $isValidResponse = $e->getParam('is_valid', true);
            $isHomeMenu = (substr($this->lastServedMenu, 0, 5) == 'home_');
            if ($isValidResponse && $isHomeMenu) {
                // this method should only be called by home menus (menus beginning with home_*)
                $e->getInstantUssd()->clearMenuVisitHistory($e);
            }
            $this->updateCurrentMenuConfig($this->menuConfig);
            // update the main screens config
            $this->ussdMenusConfig[$this->ussdEvent->getName()] = $this->menuConfig;
            return $this->showScreen($continueUssdHops, $appendNavigationText);
        }
        // try see if we can initialize a looping session
        $targetLoopset = array_key_exists('target_loopset', $this->menuConfig) ? $this->menuConfig['target_loopset'] : "";
        if (!empty($targetLoopset)) {
            $this->initializeLoopingSession($targetLoopset);
        }
        // do your processing; save to db; etc
        $this->captureIncomingData();
        // return UssdMenuItem with pointer to the next screen
        return $this->nextMenu();
    }

    /**
     * Check if there's URL to pull menu config from
     * 
     * @param ArrayObject $menuConfig
     * @return boolean true|false
     */
    private function hasDynamicGetUri(ArrayObject $menuConfig) {
        return (array_key_exists('request_config', $menuConfig) &&
                !empty($menuConfig['request_config']['uri']) &&
                (strtoupper($menuConfig['request_config']['method']) === 'GET'));
    }

    /**
     * Automatically updates the current menu config via GET. Override this 
     * method to come up with your own custom implimentation
     * 
     * @todo Complete Implementation. It should return a screen config object
     * @param ArrayObject $currentMenuConfig
     */
    public function updateCurrentMenuConfig(ArrayObject $currentMenuConfig) {

        // check if we have a preset URI
        if (!$this->hasDynamicGetUri($currentMenuConfig)) {
            return;
        }
        // pull live json data from your external API            
        $requestConfig = $currentMenuConfig['request_config'];
        // use 50s as USSD times out after 60s
        $client = new Client(['timeout' => 50]);
        $response = $client->request('GET', $requestConfig['uri']
                , $requestConfig['request_options']);
        $response instanceof \GuzzleHttp\Psr7\Response;
        $contents = $response->getBody()->getContents();
        // @todo - confirm that the content-type is JSON
        if (($response->getStatusCode() === 200 ) &&
                !empty($contents)) {
            $decodedContents = json_decode($contents, true);
            if (!array_key_exists('menu_items', $decodedContents)) {
                $currentMenuConfig['menu_items'] = $decodedContents;
            } else {
                $currentMenuConfig['menu_items'] = $decodedContents;
            }
            // @todo Merge $decodedContents to $currentMenuConfig
        }
    }

    /**
     * Override this method and get the correct 
     * 
     * @param string $targetLoopset
     */
    protected function initializeLoopingSession($targetLoopset) {
        $e = $this->ussdEvent;
        // when you override this method; use the correct value of loops required
        $loopsRequired = $this->latestResponse;

        $e->getInstantUssd()->getUssdLoopMapper()
                ->initializeLoopingSession($targetLoopset, $e->getParam('session_id'), $loopsRequired);
    }

    /**
     * 
     * @param boolean $continueUssdHops
     * @param boolean $appendNavigationText
     * @return Response
     */
    protected function showScreen($continueUssdHops = true, $appendNavigationText = true) {
        return $this->ussdResponseGenerator
                        ->composeAndRenderUssdMenu($this->menuConfig, $continueUssdHops, $appendNavigationText);
    }

    /**
     * 
     * @return UssdMenuItem
     */
    protected function nextMenu() {
        $e = $this->ussdEvent;
        // check if we should stop looping
        $stopLooping = $e->getInstantUssd()
                ->shouldStopLooping($this->menuConfig, $e);
        return $this->ussdResponseGenerator
                        ->determineNextMenu($this->lastServedMenu, $this->menuConfig, $this->latestResponse, $stopLooping);
    }

    /**
     * Implement this method MUST be implemented by all screens. Use it to to configure a 
     * menu config
     * 
     */
    public static abstract function configure();

    /**
     * Implement this method and add your business logic
     * 
     *  @return void
     */
    protected abstract function captureIncomingData();

    /**
     * Override this method to add logic to check if a screen is skippable
     * 
     * @return boolean
     */
    protected function isSkippableScreen() {
        //        $isSkippableMenu = $this->ussdEvent->getInstantUssd()
        //                ->getSkippableUssdMenuMapper()
        //                ->isSkippable(['col_1' => $col1Val,
        //            'col_2' => $col2Val, 'col_n' => $colNVal], $tableToCheck);
        //        if ($isSkippableMenu === true) {
        //            $this->setAlternativeScreen($alternativeScreenName, $isResetToPreviousPosition);
        //            return $isSkippableMenu;
        //        }
        //        return false;
        return (bool) $this->ussdEvent->getParam('is_skippable', false);
    }

    /**
     * You may override this method to manage optional screens/pages or 
     * 
     * @return UssdMenuItem
     * @throws Exception
     */
    protected function getAlternativeScreen() {
        if (!$this->alternativeScreen) {
            throw new Exception('Alternative screen not set.');
        }
        return $this->alternativeScreen;
    }

    /**
     * 
     * @param string $alternativeScreenName Set the screen we should show instead of the default one pointed by next_screen key
     * @param boolean $isResetToPreviousPosition
     */
    protected function setAlternativeScreen($alternativeScreenName, $isResetToPreviousPosition = false) {
        $alternativeScreen = new UssdMenuItem($alternativeScreenName);
        // are we going back to an already displayed screen?
        $alternativeScreen->setIsResetToPreviousPosition($isResetToPreviousPosition);
        $this->alternativeScreen = $alternativeScreen;
    }

}
