<?php

namespace Bitmarshals\InstantUssd;

use Bitmarshals\InstantUssd\Response;
use Exception;
use Bitmarshals\InstantUssd\UssdMenuItem;
use ArrayObject;

/**
 * Description of UssdResponseGenerator
 *
 * @author David Bwire
 */
class UssdResponseGenerator {

    /**
     * This is the last method called to send the USSD response to the client
     *
     * @param string $ussdContent
     * @return Response
     */
    public function renderUssdMenu($ussdContent) {

        $response = new Response();
        $response->getHeaders()->addHeaderLine('Content-Type', 'text/plain');
        // trim right incase  of any spaces
        $response->setContent(rtrim($ussdContent));
        return $response;
    }

    /**
     * 
     * @param ArrayObject $menuConfig
     * @param bool $continueUssdHops
     * @param bool $appendNavigationText
     * @return string
     */
    public function composeUssdMenu(ArrayObject $menuConfig, $continueUssdHops = true, $appendNavigationText = true) {

        // extract menu data
        $menuTitle = array_key_exists('title', $menuConfig) ? $menuConfig['title'] : "";
        $menuBody = array_key_exists('body', $menuConfig) ? $menuConfig['body'] : "";
        $menuFooter = array_key_exists('footer', $menuConfig) ? $menuConfig['footer'] : "";
        $menuItems = array_key_exists('menu_items', $menuConfig) ? $menuConfig['menu_items'] : [];
        $errorMessage = array_key_exists('error_message', $menuConfig) ? $menuConfig['error_message'] : "";
        $hasError = array_key_exists('has_error', $menuConfig) ? $menuConfig['has_error'] : false;
        $isSkippable = array_key_exists('is_skippable', $menuConfig) ? $menuConfig['is_skippable'] : false;

        if ($isSkippable !== false) {
            // is_skippable is an indicator that menu should have been skipped
            // if it wasn't skipped then this setting should have been changed
            throw new \Exception('Skippable menu encountered. Set '
            . '$menuConfig["is_skippable"]=false; ' . __METHOD__ . ':' . __LINE__);
        }

        // attach error if available
        if (!empty($errorMessage) && $hasError) {
            $menuTitle = $errorMessage . PHP_EOL . PHP_EOL . $menuTitle;
        }



        $responseText = "";

        // override append navigation text if hops are being terminated
        if ($continueUssdHops === false) {
            $appendNavigationText = false;
        }

        if (array_key_exists('prepend_continuity_text', $menuConfig)) {
            if ($menuConfig['prepend_continuity_text'] === true) {
                if ($continueUssdHops === true) {
                    $responseText = $this->con();
                } else {
                    $responseText = $this->end();
                }
            }
        } else {
            // indicate if we're continuing or ending
            if ($continueUssdHops === true) {
                $responseText = $this->con();
            } else {
                $responseText = $this->end();
            }
        }
        // attach menu title if available
        if ($menuTitle) {
            // attach menu title & break
            $responseText = $responseText . "$menuTitle" . $this->lineBreak() . $this->lineBreak();
        }
        // see if menu body is attached
        if ($menuBody) {
            $responseText = $responseText . $menuBody . $this->lineBreak() . $this->lineBreak();
        }
        // check for menu items & attach if available
        if (is_array($menuItems) && count($menuItems)) {
            $length = count($menuItems);
            // check if we have there's need for ranking or not
            if ($length > 1) {
                foreach ($menuItems as $key => $menuItem) {
                    // check if description key exists
                    if (!array_key_exists('description', $menuItem)) {
                        // @todo log notice
                        // skip to the next $menuItem
                        continue;
                    }
                    if (array_key_exists('is_hidden', $menuItem) &&
                            ($menuItem['is_hidden'] === true)) {
                        // skip menu items that are hidden
                        continue;
                    }
                    // rank if we have more than one item in ArrayObject
                    $ranking = $key + 1;
                    $responseText = $responseText . ((string) $ranking) . ". " . $menuItem['description'] . $this->lineBreak();
                    if (($key + 1) == ($length)) {
                        $responseText .= $this->lineBreak();
                    }
                }
            } else {
                $menuItem = current($menuItems);
                // indeterminate menus may have a single list item that just point to it's next call
                if (array_key_exists('description', $menuItem) && !empty($menuItem['description'])) {
                    // check is_hidden but maintain old behaviour
                    if (array_key_exists('is_hidden', $menuItem)) {
                        if ($menuItem['is_hidden'] === false) {
                            // we have a single menu item attach and break
                            $responseText = $responseText . ("1. ") . $menuItem['description'] . $this->lineBreak();
                        }
                    } else {
                        // we have a single menu item attach and break
                        $responseText = $responseText . ("1. ") . $menuItem['description'] . $this->lineBreak();
                    }
                } else {
                    // @todo log notice
                }
            }
            $responseText = $responseText . $this->lineBreak();
        }
        // see if menufooter is attached
        if ($menuFooter) {
            $responseText = $responseText . $menuFooter . $this->lineBreak() . $this->lineBreak();
        }
        // check if we should append navigation text
        if ($appendNavigationText === true) {
            if (array_key_exists('navigation_text', $menuConfig) &&
                    !empty($menuConfig['navigation_text'])) {
                return $responseText . $menuConfig['navigation_text'];
            } else {
                return $this->appendNavigationText($responseText);
            }
        } else {
            return $responseText;
        }
    }

    /**
     * 
     * @param ArrayObject $menuConfig
     * @param bool $continueUssdHops
     * @param bool $appendNavigationText
     * @return Response
     */
    public function composeAndRenderUssdMenu(ArrayObject $menuConfig, $continueUssdHops = true, $appendNavigationText = true) {
        $ussdContent = $this->composeUssdMenu($menuConfig, $continueUssdHops, $appendNavigationText);
        return $this->renderUssdMenu($ussdContent);
    }

    /**
     * 
     * @param string $responseText
     * @return string
     */
    protected function appendNavigationText($responseText) {

        return $responseText
                . "0. GO BACK"
                . $this->lineBreak()
                . "00. MAIN MENU"
                . $this->lineBreak()
                . "000. EXIT";
    }

    /**
     * Add a PHP_EOL i.e \n
     *
     * @return string
     */
    protected function lineBreak() {

        return PHP_EOL;
    }

    /**
     * Indicate expecting more information from the mobile user
     *
     * @return string
     */
    protected function con() {
        // must have space after it
        return 'CON ';
    }

    /**
     * Indicate this is the last message to send to the client
     *
     * @return string
     */
    protected function end() {
        // must have space after it
        return 'END ';
    }

    /**
     * Indicate that the system is in service
     *
     * @return string
     */
    public function inServiceText() {
        return 'This application is currently being serviced. Check back '
                . 'later.';
    }

    /**
     * Indicate that a section of the system is in development     
     *
     * @return string
     */
    public function inDevelopmentText() {
        return 'This section is currently in development. Check back '
                . 'later.';
    }

    /**
     *
     * @return string
     */
    public function smsError() {
        return "An error occured, while trying to send you an sms. Please try again.";
    }

    /**
     * 
     * @param string $lastServedMenu
     * @param ArrayObject $menuConfig
     * @param string $latestResponse
     * @param bool $stopLooping
     * @return UssdMenuItem
     * @throws Exception
     */
    public function determineNextMenu($lastServedMenu, ArrayObject $menuConfig, $latestResponse, $stopLooping = true) {

        $isLoopEnd = array_key_exists('is_loop_end', $menuConfig) ? $menuConfig['is_loop_end'] : false;

        // check if looping should still be continued
        if ($isLoopEnd && !$stopLooping) {
            $loopStartMenuId = array_key_exists('loop_start', $menuConfig) ? $menuConfig['loop_start'] : null;
            if (empty($loopStartMenuId)) {
                throw new Exception('loop_start is missing for ' . $lastServedMenu);
            }
            // return the starting menu to build a longer looping chain
            return new UssdMenuItem($loopStartMenuId);
        }
        $lastServedMenuItems = (array_key_exists('menu_items', $menuConfig) &&
                is_array($menuConfig['menu_items'])) ? $menuConfig['menu_items'] : [];
        $totalMenuItems = count($lastServedMenuItems);
        if ($totalMenuItems === 0) {
            // throw new Exception("$lastServedMenu should have should be at least"
            // . " one menu item pointing to the next menu.");
            // return an exit
            // implies it's terminate
            return new UssdMenuItem();
        }
        // retreive next_screen from dynamic or non-ranked menus
        if ($totalMenuItems === 1) {
            $currentMenuItem = current($lastServedMenuItems);
            if (is_array($currentMenuItem) &&
                    array_key_exists('next_screen', $currentMenuItem) &&
                    !empty($currentMenuItem['next_screen'])) {
                $ussdMenuItem = new UssdMenuItem($currentMenuItem['next_screen']);
                // check if we're jumping back in history
                if (array_key_exists('is_reset_to_previous_position', $currentMenuItem)) {
                    $ussdMenuItem->setIsResetToPreviousPosition((bool) $currentMenuItem['is_reset_to_previous_position']);
                }
                return $ussdMenuItem;
            } else {
                // return an exit
                return new UssdMenuItem();
            }
        }

        // we are likely dealing with a predetermined list
        // we have many menu_items
        // use $latestResponse to try and find the next_screen
        $latestResponseKey = $latestResponse - 1;
        if (array_key_exists($latestResponseKey, $lastServedMenuItems)) {
            $targetMenuItem = $lastServedMenuItems[$latestResponseKey];

            if (is_array($targetMenuItem) && array_key_exists('next_screen', $targetMenuItem) &&
                    !empty($targetMenuItem['next_screen'])) {
                $ussdMenuItem = new UssdMenuItem($targetMenuItem['next_screen']);
                // check if we're jumping back in history
                if (array_key_exists('is_reset_to_previous_position', $targetMenuItem)) {
                    $ussdMenuItem->setIsResetToPreviousPosition((bool) $targetMenuItem['is_reset_to_previous_position']);
                }
                return $ussdMenuItem;
            } else {
                throw new Exception("Could not retreive next_screen from "
                . "menu items of $lastServedMenu using the latest response key ($latestResponseKey) " . __METHOD__ . ":" . __LINE__);
            }
        } else {
            throw new Exception("Could not retreive next_screen from "
            . "menu items of $lastServedMenu" . __METHOD__ . ":" . __LINE__);
        }
    }

}
