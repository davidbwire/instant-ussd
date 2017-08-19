<?php

namespace Bitmarshals\InstantUssd\Contracts;

use Zend\Db\Sql\Predicate\PredicateInterface;
use Zend\Db\Sql\Where;

/**
 * Description of SkippableInterface
 *
 * @author David Bwire
 */
interface SkippableInterface {

    /**
     * 
     * @param PredicateInterface|Where|array  ["menu_id" => $menuId, "reference_id" => $referenceId]
     * @param string $referenceTable The table you'd like to check if the given menu_id is skippable
     * @return boolean
     */
    public function isSkippable($predicate, $referenceTable = null);
}
