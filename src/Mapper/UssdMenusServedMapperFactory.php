<?php

namespace Bitmarshals\InstantUssd\Mapper;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Bitmarshals\InstantUssd\Mapper\UssdMenusServedMapper;

/**
 * Description of UssdMenusServedMapperFactory
 *
 * @author David Bwire
 */
class UssdMenusServedMapperFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null): UssdMenusServedMapper {
        return new UssdMenusServedMapper('iussd_ussd_menus_served', $container->get('dbAdapter'));
    }

}
