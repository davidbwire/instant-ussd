<?php

namespace Bitmarshals\InstantUssd\Mapper;

use Zend\Db\Sql\Sql;
use Zend\Db\TableGateway\TableGateway as ZfTableGateway;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Adapter\Driver\ConnectionInterface;
use Exception;

/**
 * Description of AbstractTableGateway
 *
 * @author David Bwire
 */
class TableGateway extends ZfTableGateway {

    /**
     * Retreive an Sql instance preset with the dbAdapter and tableName
     *
     * @return \Zend\Db\Sql\Sql $sql
     */
    public function getSlaveSql($table = null) {
        if (!empty($table)) {
            return new Sql($this->getAdapter(), $table);
        }
        return new Sql($this->getAdapter(), $this->getTable());
    }

    /**
     * @return \Zend\Db\Sql\Predicate\Predicate Description
     */
    public function getPredicate() {
        return new Predicate();
    }

    /**
     * 
     * @return ConnectionInterface
     */
    public function beginTransaction() {
        return $this->getAdapter()->getDriver()
                        ->getConnection()->beginTransaction();
    }

    /**
     * 
     * @return ConnectionInterface
     */
    public function rollback() {
        return $this->getAdapter()->getDriver()
                        ->getConnection()->rollback();
    }

    /**
     * 
     * @return ConnectionInterface
     */
    public function commit() {
        return $this->getAdapter()->getDriver()
                        ->getConnection()->commit();
    }

    /**
     * @deprecated since version number
     * @param \Exception $ex
     * @return string
     */
    protected function getExceptionSummary(\Exception $ex) {
        return PHP_EOL .
                '>>>Exception' . ' - ' . $ex->getMessage() .
                PHP_EOL . '>>>Exception Code ' . $ex->getCode() .
                PHP_EOL . '>>>File ' . $ex->getFile() . ' Line ' . $ex->getLine();
    }

    /**
     *
     * @param \Exception $ex
     * @param string $file file the error occured in
     * @param string $line line in file where the error occured
     * @return string
     */
    protected function exceptionSummary(\Exception $ex, $file = null, $line = null) {
        return PHP_EOL .
                '>>>Exception' . ' - ' . $ex->getMessage() .
                PHP_EOL . '>>>Exception Code ' . $ex->getCode() .
                PHP_EOL . '>>>File ' . $ex->getFile() . ' Line ' . $ex->getLine() .
                PHP_EOL . '>>>Originating File ' . $file .
                PHP_EOL . '>>>Originating Line ' . $line;
    }

    /**
     *
     * @param mixed $data
     * @param bool $exit
     */
    public static function printData($data, $exit = 1) {
        echo '<br>';
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        echo '<br>';
        if ($exit) {
            exit;
        }
    }

    /**
     *
     * @param obj $sqlObject
     * @param Sql $sql
     * @param bool $exit
     */
    protected static function printSqlObject($sqlObject, $sql, $exit = 1) {
        echo '<br>';
        echo '<pre>';
        echo $sql->getSqlStringForSqlObject($sqlObject);
        echo '</pre>';
        if ($exit) {
            exit;
        }
    }

}
