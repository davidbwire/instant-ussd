<?php

namespace Bitmarshals\InstantUssd\Listeners;

use Bitmarshals\InstantUssd\UssdEvent;
use Bitmarshals\InstantUssd\Mapper\UssdMenusServedMapper;
use Bitmarshals\InstantUssd\UssdResponseGenerator;
use Bitmarshals\InstantUssd\Response;
use Exception;
use Bitmarshals\InstantUssd\Mapper\UssdLoopMapper;
use ArrayObject;

/**
 * Description of IUssdEventsListener
 * 
 * Listens to events triggered by InstantUssd library
 * 
 *
 * @author David Bwire
 */
class IUssdEventsListener {

    /**
     *
     * @var ArrayObject
     */
    protected $ussdMenusConfig;

    public function __construct(ArrayObject $ussdMenusConfig) {
        $this->ussdMenusConfig = $ussdMenusConfig;
    }

    /**
     * 
     * @param UssdEvent $e
     * @return mixed boolean|null
     */
    public function onRetreiveLastMenuServed(UssdEvent $e) {

        // retreive mapper
        $ussdMenusServedMapper = $e->getServiceLocator()
                ->get(UssdMenusServedMapper::class);
        // instance check
        if (!$ussdMenusServedMapper instanceof UssdMenusServedMapper) {
            return false;
        }

        $menuId = $ussdMenusServedMapper->getLastServedMenu($e->getParam('session_id'));

        if (!empty($menuId)) {
            return $menuId;
        }

        return null;
    }

    /**
     * 
     * @param UssdEvent $e
     * @return mixed false|null|string
     */
    public function onGoBackPre(UssdEvent $e) {

        // retreive mapper
        $ussdMenusServedMapper = $e->getServiceLocator()
                ->get(UssdMenusServedMapper::class);
        // instance check
        if (!$ussdMenusServedMapper instanceof UssdMenusServedMapper) {
            return false;
        }

        // fetch the last 2 items in history LIFO
        $results = $ussdMenusServedMapper->listServedMenusBySessionId($e->getParam('session_id'), 2, ['id', 'menu_id', 'is_loop_end', 'loopset_name']);
        $resultsFound = $results->count();

        if (empty($resultsFound)) {
            return null;
        }
        $currentResults = $results->current();

        if ($resultsFound == 1) {
            if ($currentResults['is_loop_end']) {
                // decrement loops done so far
                $this->getUssdLoopMapper($e)
                        ->decrementLoops($currentResults['loopset_name'], $e->getParam('session_id'));
            }
            // remove one item i.e latest_menu
            $ussdMenusServedMapper->removeMenuVisitHistoryById((int) $currentResults['id']);
            // no other item to return
            return null;
        }
        if ($resultsFound == 2) {
            if ($currentResults['is_loop_end']) {
                // decrement loops done so far
                $this->getUssdLoopMapper($e)
                        ->decrementLoops($currentResults['loopset_name'], $e->getParam('session_id'));
            }
            // remove latest_menu & return previous_menu
            $menuVisitHistoryIdToRemove = $currentResults['id'];
            $ussdMenusServedMapper->removeMenuVisitHistoryById((int) $menuVisitHistoryIdToRemove);
            // move to next
            $results->next();
            // get previous menu
            $previousMenu = $results->current()['menu_id'];
            // return previous menu
            return $previousMenu;
        }

        return null;
    }

    /**
     * 
     * @param UssdEvent $e
     * @return mixed false|null
     * @throws Exception
     */
    public function onRetreiveMenuConfig(UssdEvent $e) {

        // extract the menu_id whose config we're looking for
        $menuId = $e->getParam('menu_id', null);

        if (empty($menuId)) {
            throw new Exception('Please set menu_id param.');
        }

        if (!array_key_exists($menuId, $this->ussdMenusConfig)) {
            //@todo - log menu_id not found
            return null;
        }
        // extract & return the requested config
        return $this->ussdMenusConfig[$menuId];
    }

    /**
     * 
     * @param UssdEvent $e
     * @return mixed false|int
     */
    public function onUssdMenuTrigger(UssdEvent $e) {

        $eventName = $e->getName();
        // incoming cycle & system events should not be tracked
        $containsIncomingData = $e->containsIncomingData();
        $trackingDisabled = $e->getParam('disable_tracking', false);
        $isValid = $e->getParam('is_valid', true);
        // test for 'system' events and cancel them
        // system events start with _ (underscore)
        if (preg_match('/^_[a-z]{1,}/', $eventName) || $containsIncomingData || $trackingDisabled || (!$isValid)) {
            return false;
        }
        // retreive mapper
        $ussdMenusServedMapper = $e->getServiceLocator()
                ->get(UssdMenusServedMapper::class);

        // instance check
        if (!$ussdMenusServedMapper instanceof UssdMenusServedMapper) {
            return false;
        }
        $menuConfig = array_key_exists($eventName, $this->ussdMenusConfig) ? $this->ussdMenusConfig[$eventName] : ['loopset_name' => null, 'is_loop_end' => false];
        $loopsetName = array_key_exists('loopset_name', $menuConfig) ? $menuConfig['loopset_name'] : null;
        $isLoopEnd = array_key_exists('is_loop_end', $menuConfig) ? $menuConfig['is_loop_end'] : false;

        // pull params and save
        $result = $ussdMenusServedMapper->push($e->getParam('session_id'), $eventName, $e->getParam('phone_number'), $loopsetName, $isLoopEnd);
        // disables any event that can be set at a priority less than -100 on this namespace.
        $e->stopPropagation(true);
        return $result;
    }

    /**
     * 
     * @param UssdEvent $e
     * @return Response
     */
    public function onExit(UssdEvent $e) {

        $exitMessage = $e->getParam('exit_message');

        if (!empty($exitMessage)) {
            $menuTitle = $exitMessage;
        } else {
            $menuTitle = "Thank you for using our service.";
        }
        $ussdResponseGenerator = new UssdResponseGenerator();
        $ussdContent = $ussdResponseGenerator
                ->composeUssdMenu(['title' => $menuTitle], false, false);
        return $ussdResponseGenerator
                        ->renderUssdMenu($ussdContent);
    }

    /**
     * 
     * @param UssdEvent $e
     * @return Response
     */
    public function onError(UssdEvent $e) {

        $errorMessage = $e->getParam('error_message');
        if (!empty($errorMessage)) {
            $menuTitle = $errorMessage;
        } else {
            $menuTitle = "An error occured. Please go back and try again.";
        }

        $ussdResponseGenerator = new UssdResponseGenerator();
        $ussdContent = $ussdResponseGenerator
                ->composeUssdMenu(['title' => $menuTitle], true, true);
        return $ussdResponseGenerator
                        ->renderUssdMenu($ussdContent);
    }

    /**
     * 
     * @param UssdEvent $e
     * @return UssdLoopMapper
     */
    private function getUssdLoopMapper(UssdEvent $e) {
        return $e->getServiceLocator()
                        ->get(UssdLoopMapper::class);
    }

}
