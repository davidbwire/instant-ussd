<?php

namespace Bitmarshals\InstantUssd\Mapper;

use Bitmarshals\InstantUssd\Mapper\TableGateway;
use Bitmarshals\InstantUssd\Contracts\SkippableInterface;
use Zend\Db\Sql\Predicate\PredicateInterface;
use Zend\Db\Sql\Where;

/**
 * Description of SkippableUssdMenuMapper
 *
 * @author David Bwire
 */
class SkippableUssdMenuMapper extends TableGateway implements SkippableInterface {

    /**
     * 
     * @param PredicateInterface|Where|array  eg ["menu_id" => $menuId, "reference_id" => $referenceId]
     * @param string $referenceTable The table you'd like to check if the given menu_id is skippable
     * @return boolean
     */
    public function isSkippable($predicate, $referenceTable = null) {

        $sql    = $this->getSlaveSql($referenceTable);
        $select = $sql->select()
                ->columns(['is_skippable'])
                ->where($predicate);

        $result = $sql->prepareStatementForSqlObject($select)
                ->execute();
        if ($result->count() == 1) {
            return (bool) $result->current()['is_skippable'];
        }
        return false;
    }

}
