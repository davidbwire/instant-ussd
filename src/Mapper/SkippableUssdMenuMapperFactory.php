<?php

namespace Bitmarshals\InstantUssd\Mapper;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Bitmarshals\InstantUssd\Mapper\SkippableUssdMenuMapper;

/**
 * Description of SkippableUssdMenuMapperFactory
 *
 * @author David Bwire
 */
class SkippableUssdMenuMapperFactory implements FactoryInterface {

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null) {
        return new SkippableUssdMenuMapper('iussd_skippable_ussd_menu', $container->get('dbAdapter'));
    }

}
