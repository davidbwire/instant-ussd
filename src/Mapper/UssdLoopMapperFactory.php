<?php

namespace Bitmarshals\InstantUssd\Mapper;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Bitmarshals\InstantUssd\Mapper\UssdLoopMapper;

/**
 * Description of UssdLoopMapperFactory
 *
 * @author David Bwire
 */
class UssdLoopMapperFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        return new UssdLoopMapper('iussd_ussd_loop', $container->get('dbAdapter'));
    }

}
