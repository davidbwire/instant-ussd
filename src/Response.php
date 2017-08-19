<?php

namespace Bitmarshals\InstantUssd;

use Zend\Http\PhpEnvironment\Response as ZfResponse;

/**
 * Description of Response
 *
 * @author David Bwire
 */
class Response extends ZfResponse {

    public function send() {
        parent::send();
        // exit to pr
        exit;
    }

}
