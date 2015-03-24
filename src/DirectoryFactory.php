<?php
/**
 * @author     Chris Wright <https://github.com/DaveRandom>
 * @copyright  Copyright (c) 2013 Chris Wright
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    1.0.0
 */

namespace SimpleLDAP;

use LDAPi\Directory as LDAPi_Directory;

/**
 * Factory which makes directory objects
 */
class DirectoryFactory
{
    /**
     * @param string $uri
     * @param string $user
     * @param string $pass
     * @param array  $options
     * @return Directory
     * @throws SimpleLDAPException
     */
    public function create($uri, $user = null, $pass = null, array $options = [])
    {
        return new Directory(new LDAPi_Directory, $uri, $user, $pass, $options);
    }
}
