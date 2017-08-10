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
    public function __construct(string $menuId = "_exit_") {
        $this->menuId = trim($menuId);
    }

    /**
     * 
     * @return string
     */
    public function getMenuId(): string {
        return $this->menuId;
    }

    /**
     * 
     * @param string $menuId
     * @return $this
     */
    public function setMenuId(string $menuId) {
        $this->menuId = $menuId;
        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function isSkippable(): bool {
        return $this->isSkippale;
    }

    /**
     * 
     * @return bool
     */
    public function getIsSkippale(): bool {
        return $this->isSkippale;
    }

    /**
     * 
     * @param bool $isSkippale
     * @return $this
     */
    public function setIsSkippale(bool $isSkippale) {
        $this->isSkippale = $isSkippale;
        return $this;
    }

}
