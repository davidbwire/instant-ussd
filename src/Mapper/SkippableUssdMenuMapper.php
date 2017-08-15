<?php

namespace Bitmarshals\InstantUssd\Mapper;

use Bitmarshals\InstantUssd\Mapper\TableGateway;
use Bitmarshals\InstantUssd\Contracts\SkippableInterface;

/**
 * Description of SkippableUssdMenuMapper
 *
 * @author David Bwire
 */
class SkippableUssdMenuMapper extends TableGateway implements SkippableInterface {

    /**
     * 
     * @param array $where ["menu_id" => $menuId, "reference_id" => $referenceId]
     * @param string $referenceTable The table you'd like to check if the given menu_id is skippable
     * @return bool
     */
    public function isSkippable(array $where, $referenceTable = null) {

        $sql    = $this->getSlaveSql($referenceTable);
        $select = $sql->select()
                ->columns(['is_skippable'])
                ->where($where);

        $result = $sql->prepareStatementForSqlObject($select)
                ->execute();
        if ($result->count() == 1) {
            return (bool) $result->current()['is_skippable'];
        }
        return false;
    }

}
