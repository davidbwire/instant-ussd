<?php

namespace Bitmarshals\InstantUssd\Contracts;

/**
 * Description of SkippableInterface
 *
 * @author David Bwire
 */
interface SkippableInterface {

    /**
     * 
     * @param array $where ["menu_id" => $menuId, "reference_id" => $referenceId]
     * @param string $referenceTable The table you'd like to check if the given menu_id is skippable
     * @return bool
     */
    public function isSkippable(array $where, $referenceTable = null);
}
