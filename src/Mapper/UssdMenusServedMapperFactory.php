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

    /**
     * 
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param array $options
     * @return UssdMenusServedMapper
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        return new UssdMenusServedMapper('iussd_ussd_menus_served', $container->get('dbAdapter'));
    }

}
