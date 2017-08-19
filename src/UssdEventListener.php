<?php

namespace Bitmarshals\InstantUssd;

use Zend\EventManager\SharedEventManager;
use Bitmarshals\InstantUssd\UssdResponseGenerator;
use Bitmarshals\InstantUssd\Listeners\System;
use Exception;

/**
 * UssdEventListener is a SharedEventManager that tracks all listeners for a specific 
 *
 * @author David Bwire
 */
class UssdEventListener extends SharedEventManager {

    /**
     *
     * @var UssdResponseGenerator 
     */
    protected $ussdResponseGenerator;

    /**
     * 
     * @param array $ussdMenusConfig
     */
    public function __construct(array $ussdMenusConfig) {
        $this->attachSystemEvents($ussdMenusConfig);
        $this->ussdResponseGenerator = new UssdResponseGenerator();
    }

    /**
     * Attaches common USSD menu events
     */
    protected function attachSystemEvents(array &$ussdMenusConfig) {
        // needs to be passed as a call ba
        $system = new System($ussdMenusConfig);
        // exit
        $this->attach(__NAMESPACE__, '_exit_', function($e) use ($system) {
            return call_user_func([$system, 'onExit'], $e);
        });
        // communicate ussd error
        // error
        $this->attach(__NAMESPACE__, '_error_', function($e) use ($system) {
            return call_user_func([$system, 'onError'], $e);
        });
        // return the requested menu_config
        // used mostly to retreive config of $lastServedMenu        
        $this->attach(__NAMESPACE__, '_retreive_menu_config_', function($e) use ($system) {
            return call_user_func([$system, 'onRetreiveMenuConfig'], $e);
        });
        // tries to retreive previous menu
        // returns null or menu_id to serve
        $this->attach(__NAMESPACE__, '_go_back_.pre', function($e) use ($system) {
            return call_user_func([$system, 'onGoBackPre'], $e);
        });
        // gets the menu sent out on last reponse
        $this->attach(__NAMESPACE__, '_retreive_last_served_menu_', function($e) use ($system) {
            return call_user_func([$system, 'onRetreiveLastMenuServed'], $e);
        });
        // This listener records visited menu
        // listen to all events but for system events
        // It's basically a stack that'll help when navigating backwards
        $this->attach(__NAMESPACE__, '*', function($e) use ($system) {
            return call_user_func([$system, 'onUssdMenuTrigger'], $e);
        }, -100);
    }

}
