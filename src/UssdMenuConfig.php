<?php

namespace Bitmarshals\InstantUssd;

/**
 * Description of UssdMenuConfig
 * 
 * - There can only be one next_menu_id that may point to a skippable or mandatory menu
 * - A skippable stops event propagation and returns a Skippable object
 *
 * @author David Bwire
 */
class UssdMenuConfig {

    // next_menu_id points to only one menu
    // menu_items should be ranked according to how they are listed in config file
    const RANKING_TYPE_PREDETERMINED         = 'predetermined';
    // ussd session data is temporarily stored in the database
    const RANKING_TYPE_DYNAMIC               = 'dynamic';
    // no ranking applied
    const RANKING_TYPE_NONE                  = 'none';
    /*
     * User response types
     * 
     * */
    // -- user response type helps when determining next menu
    // --- USER_RESPONSE_TYPE_CUSTOM may need next_menu_id_custom/next_menu_id_dynamic 
    // user selects a number from an indexed list (predetermined/dynamic) eg 1
    // applies to predetermined lists only?? seems so
    const USER_RESPONSE_TYPE_INDEX           = 'index';
    // user enters a custom value eg his/her name
    // applies to no lists(indexed/dynamic) at all?? seems so
    const USER_RESPONSE_TYPE_CUSTOM          = 'custom';
    // mix of list or custom value
    // applies to dynamic lists only with option to enter?? seems so
    const USER_RESPONSE_TYPE_INDEX_OR_CUSTOM = 'index_or_custom';

    /**
     * Configuration array of all USSD menus available
     *
     * @var array 
     */
    protected $ussdMenus = [];

    public function __construct() {
        // initialise the menu config
        // menu's starting with home_* are special
        $this->ussdMenus["home_instant_ussd"] = [
            "menu_title" => "Welcome to InstantUssd",
            "is_skippable" => false,
            "menu_footer" => "Questions? Call +254712688559",
            "menu_items_ranking_type" => self::RANKING_TYPE_PREDETERMINED,
            "valid_values" => range(1, 2),
            "loopset_name" => "",
            // menu_items
            "menu_items" => [
                [
                    "next_menu_id" => 'example_enter_full_name',
                    "description" => "Register"
                ],
                [
                    "next_menu_id" => 'example_my_account',
                    "description" => "My account"
                ]
            ]
        ];
    }

    /**
     * 
     * @return array
     */
    public function getUssdMenus(): array {
        return $this->ussdMenus;
    }

    /**
     * 
     * @param array $ussdMenus
     * @return $this
     */
    public function setUssdMenus(array $ussdMenus) {
        $this->ussdMenus = $ussdMenus;
        return $this;
    }

    /**
     * 
     * @param mixed $menuId
     * @param array $menuConfig
     * @return $this
     */
    public function addUssdMenu($menuId, array $menuConfig) {
        $this->ussdMenus[$menuId] = $menuConfig;
        return $this;
    }

    /**
     * 
     * @param mixed $menuId
     * @return array|null
     */
    public function getUssdMenu($menuId) {
        if (array_key_exists($menuId, $this->ussdMenus)) {
            return $this->ussdMenus[$menuId];
        }
        return null;
    }

}
