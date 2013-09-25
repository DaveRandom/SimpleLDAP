<?php

namespace SimpleLDAP;

use LDAPi\Directory as LDAPiDirectory;

class DirectoryFactory
{
    public function create($uri, $user = null, $pass = null, array $options = [])
    {
        return new Directory(new LDAPiDirectory, $uri, $user, $pass, $options);
    }
}
