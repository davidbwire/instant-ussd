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
     * @param string $menuId
     * @param array $where
     * @param string $referenceTable
     * @return bool
     */
    public function isSkippable(string $menuId, array $where, $referenceTable = null): bool {

        $sql    = $this->getSlaveSql($referenceTable);
        $select = $sql->select()
                ->columns(['is_skippable'])
                ->where(['menu_id' => $menuId])
                ->where($where);

        $result = $sql->prepareStatementForSqlObject($select)
                ->execute();
        if ($result->count() == 1) {
            return (bool) $result->current()['is_skippable'];
        }
        return false;
    }

}
