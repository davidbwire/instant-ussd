<?php

namespace Bitmarshals\InstantUssd;

/**
 * Description of UssdMenu
 *
 * @author David Bwire
 */
class UssdMenu {

    /**
     *
     * @var string 
     */
    protected $menuId;

    /**
     * Is this menu optional and thus can be skipped?
     * 
     * @var string 
     */
    protected $isSkippale = false;

    /**
     * 
     * @param string $menuId
     */
    public function __construct($menuId = "_exit_") {
        $this->menuId = trim($menuId);
    }

    /**
     * 
     * @return string
     */
    public function getMenuId() {
        return $this->menuId;
    }

    /**
     * 
     * @param string $menuId
     * @return $this
     */
    public function setMenuId($menuId) {
        $this->menuId = $menuId;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function isSkippable() {
        return $this->isSkippale;
    }

    /**
     * 
     * @return bool
     */
    public function getIsSkippale() {
        return $this->isSkippale;
    }

    /**
     * 
     * @param bool $isSkippale
     * @return $this
     */
    public function setIsSkippale($isSkippale) {
        $this->isSkippale = $isSkippale;
        return $this;
    }

}
