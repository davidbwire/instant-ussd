<?php

namespace Bitmarshals\InstantUssd;

/**
 * Description of UssdMenuItem
 *
 * @author David Bwire
 */
class UssdMenuItem {

    /**
     *
     * @var string 
     */
    protected $nextMenuId;

    /**
     * Is the next_menu_id going to be a reset to a previous menu position?
     * 
     * @var bool 
     */
    protected $isResetToPreviousPosition = false;

    /**
     * Set _exit_ so that it automatically exits if there's no next menu
     * 
     * @param string $nextMenuId
     */
    public function __construct(string $nextMenuId = "_exit_") {
        $this->nextMenuId = trim($nextMenuId);
    }

    /**
     * 
     * @return string
     */
    public function getNextMenuId() {
        return $this->nextMenuId;
    }

    /**
     * 
     * @return bool
     */
    public function isResetToPreviousPosition() {
        return $this->isResetToPreviousPosition;
    }

    /**
     * 
     * @param bool $isResetToPreviousPosition
     * @return $this
     */
    public function setIsResetToPreviousPosition(bool $isResetToPreviousPosition) {
        $this->isResetToPreviousPosition = $isResetToPreviousPosition;
        return $this;
    }

}
