<?php

namespace Bitmarshals\InstantUssd\Mapper;

use Bitmarshals\InstantUssd\Mapper\TableGateway;
use Exception;
use ArrayObject;

/**
 * Description of UssdLoopMapper
 *
 * @author David Bwire
 */
class UssdLoopMapper extends TableGateway {

    /**
     * 
     * @param string $loopsetName
     * @param string $sessionId
     * @return boolean
     * @throws Exception
     */
    public function incrementLoops($loopsetName, $sessionId) {
        $loopingData = $this->getLoopingData($loopsetName, $sessionId, ['id', 'loops_done_so_far']);

        if (empty($loopingData)) {
            throw new Exception("Looping session for $loopsetName is not initialized.");
        }

        $loopsDone = $loopingData['loops_done_so_far'];
        if (empty($loopsDone)) {
            $loopsDone = 0;
        }
        $loopsDoneSoFar = $loopsDone + 1;
        return $this->updateLoopsDoneSoFarById($loopingData['id'], $loopsDoneSoFar);
    }

    /**
     * 
     * @param string $loopsetName
     * @param string $sessionId
     * @return boolean
     * @throws Exception
     */
    public function decrementLoops($loopsetName, $sessionId) {
        $loopingData = $this->getLoopingData($loopsetName, $sessionId, ['id', 'loops_done_so_far', 'loops_required']);
        if (empty($loopingData)) {
            throw new Exception("Looping session for $loopsetName is not initialized.");
        }

        $loopsDone = $loopingData['loops_done_so_far'];
        if (empty($loopsDone)) {
            $loopsDone = 1;
        }
        $loopsDoneSoFar = $loopsDone - 1;
        // check it's a positive value
        if ($loopsDoneSoFar < 0) {
            $loopsDoneSoFar = 0;
        }
        return $this->updateLoopsDoneSoFarById($loopingData['id'], $loopsDoneSoFar);
    }

    /**
     * 
     * @param string $loopsetName
     * @param string $sessionId
     * @param ArrayObject $menuConfig
     * @return bool
     */
    public function shouldStopLooping($loopsetName, $sessionId, ArrayObject $menuConfig) {
        $loopingData = $this->getLoopingData($loopsetName, $sessionId, ['loops_done_so_far', 'loops_required']);
        if (!empty($loopingData)) {
            $loopsDone = $loopingData['loops_done_so_far'];
            $loopsRequired = $loopingData['loops_required'];
            // 
            $stopLooping = ($loopsDone === $loopsRequired);
            $menuConfig['stop_looping'] = $stopLooping;
            return $stopLooping;
        }
        $menuConfig['stop_looping'] = true;
        return true;
    }

    /**
     * 
     * @param string $loopsetName
     * @param string $sessionId
     * @param string $columns
     * @return mixed null|array
     */
    private function getLoopingData($loopsetName, $sessionId, $columns = ['id', 'loops_done_so_far', 'loops_required']) {
        $sql = $this->getSlaveSql();
        $select = $sql->select()
                ->columns($columns)
                ->where(['loopset_name' => $loopsetName, 'session_id' => $sessionId]);
        $result = $sql->prepareStatementForSqlObject($select)
                ->execute();
        if ($result->count()) {
            return $result->current();
        }
        return null;
    }

    /**
     * 
     * @param string $loopsetName
     * @param string $sessionId
     * @return mixed int|null
     */
    public function getLoopsDoneSoFar($loopsetName, $sessionId) {
        $sql = $this->getSlaveSql();
        $select = $sql->select()
                ->columns(['loops_done_so_far'])
                ->where(['loopset_name' => $loopsetName, 'session_id' => $sessionId]);
        $result = $sql->prepareStatementForSqlObject($select)
                ->execute();
        if ($result->count()) {
            return $result->current()['loops_done_so_far'];
        }
        return null;
    }

    /**
     * 
     * @param string $loopsetName
     * @param string $sessionId
     * @return boolean
     */
    private function loopingSessionExists($loopsetName, $sessionId) {
        $sql = $this->getSlaveSql();
        $select = $sql->select()
                ->columns(['id'])
                ->where(['loopset_name' => $loopsetName, 'session_id' => $sessionId]);
        $result = $sql->prepareStatementForSqlObject($select)
                ->execute();
        if ($result->count()) {
            return true;
        }
        return false;
    }

    /**
     * 
     * @param string $loopsetName
     * @param string $sessionId
     * @param int $loopsRequired
     * @return mixed int|null
     * @throws Exception
     */
    public function initializeLoopingSession($loopsetName, $sessionId, $loopsRequired) {

        $sql = $this->getSlaveSql();
        $loopingSessionExists = $this->loopingSessionExists($loopsetName, $sessionId);

        if (!$loopingSessionExists) {
            $insert = $sql->insert()
                    ->values(['loops_required' => $loopsRequired, 'loopset_name' => $loopsetName, 'session_id' => $sessionId, 'create_time' => time()]);
            $result = $sql->prepareStatementForSqlObject($insert)
                    ->execute();
        } else {
            $update = $sql->update()
                    // set create_time to avoid cases where number of affected rows may be null
                    ->set(['loops_required' => $loopsRequired, 'create_time' => time(), 'loops_done_so_far' => 0])
                    ->where(['loopset_name' => $loopsetName, 'session_id' => $sessionId]);
            $result = $sql->prepareStatementForSqlObject($update)
                    ->execute();
        }
        if ($result->getAffectedRows()) {
            return $result->getGeneratedValue();
        } else {
            throw new Exception("Looping session ($loopsetName)"
            . " initialization failed " . __METHOD__ . ":" . __LINE__);
        }
    }

    /**
     * 
     * @param int $id
     * @param int $loopsDoneSoFar
     * @return boolean
     */
    private function updateLoopsDoneSoFarById($id, $loopsDoneSoFar) {
        $sql = $this->getSlaveSql();
        $update = $sql->update()
                ->set(['loops_done_so_far' => $loopsDoneSoFar])
                ->where(['id' => $id]);
        $result = $sql->prepareStatementForSqlObject($update)
                ->execute();
        if ($result->getAffectedRows()) {
            return true;
        }
        return false;
    }

}
