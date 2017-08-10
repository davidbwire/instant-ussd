<?php

namespace Bitmarshals\InstantUssd\Mapper;

/**
 * Description of SkippableInterface
 *
 * @author David Bwire
 */
interface SkippableInterface {

    /**
     * 
     * @param string $menuId
     * @param mixed $referenceId string|int|array
     */
    public function isSkippable(string $menuId, $referenceId): bool;
}
