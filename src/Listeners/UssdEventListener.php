<?php

namespace Bitmarshals\InstantUssd\Listeners;

use Bitmarshals\InstantUssd\UssdEvent;
use Bitmarshals\InstantUssd\UssdResponseGenerator;
use Bitmarshals\InstantUssd\UssdMenuItem;
use Bitmarshals\InstantUssd\Response;
use Exception;

/**
 * Description of UssdEventListener
 * 
 * Extend this class and override some of it's methods to quickly build custom ussd
 * event listeners.
 *
 * @author David Bwire
 */
class UssdEventListener {

    /**
     *
     * @var array 
     */
    protected $menuConfig;

    /**
     *
     * @var array 
     */
    protected $ussdMenusConfig;

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
     * @param array $ussdMenusConfig
     */
    public function __construct(UssdEvent $e, array $ussdMenusConfig) {
        $this->ussdEvent             = $e;
        $this->ussdMenusConfig       = $ussdMenusConfig;
        $this->menuConfig            = $ussdMenusConfig[$e->getName()];
        $this->latestResponse        = $e->getLatestResponse();
        $this->lastServedMenu        = $e->getName();
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
            $isHomeMenu      = (substr($this->lastServedMenu, 0, 5) == 'home_');
            if ($isValidResponse && $isHomeMenu) {
                // this method should only be called by home menus (menus beginning with home_*)
                $e->getInstantUssd()->clearMenuVisitHistory($e);
            }
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
     * Override this method and get the correct 
     * 
     * @param string $targetLoopset
     */
    protected function initializeLoopingSession($targetLoopset) {
        $e             = $this->ussdEvent;
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
        $e           = $this->ussdEvent;
        // check if we should stop looping
        $stopLooping = $e->getInstantUssd()
                ->shouldStopLooping($this->menuConfig, $e);
        return $this->ussdResponseGenerator
                        ->determineNextMenu($this->lastServedMenu, $this->menuConfig, $this->latestResponse, $stopLooping);
    }

    /**
     * Override this method and add your business logic
     * 
     *  @return void
     */
    protected function captureIncomingData() {
        
    }

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
        $alternativeScreen       = new UssdMenuItem($alternativeScreenName);
        // are we going back to an already displayed screen?
        $alternativeScreen->setIsResetToPreviousPosition($isResetToPreviousPosition);
        $this->alternativeScreen = $alternativeScreen;
    }

}
