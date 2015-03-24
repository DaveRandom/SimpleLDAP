<?php
/**
 * @author     Chris Wright <https://github.com/DaveRandom>
 * @copyright  Copyright (c) 2013 Chris Wright
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    1.0.0
 */

namespace SimpleLDAP;

/**
 * Exception thrown when errors occur in SimpleLDAP operations
 */
class SimpleLDAPException extends \Exception
{
    public function __construct($message, $code = 1000000, $previous = null)
    {
        // Note that the default of 1000000 was chosen as a value that is unlikely to
        // ever collide with a standard LDAP error code, it has no other significance
        parent::__construct($message, $code, $previous);
    }
}
