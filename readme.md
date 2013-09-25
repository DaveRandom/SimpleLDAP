SimpleLDAP
==========

An extremely succinct object oriented LDAP client API for PHP. Uses some dirty tricks to make code that consumes LDAP directories a lot shorter to write, while (hopefully) remaining fairly readable.

Requirements
------------

 - PHP 5.4.0 or higher
 - ext/ldap
 - The [LDAPi](https://github.com/DaveRandom/LDAPi) library

Installation
------------

Preferably via [Composer](http://getcomposer.org/).

Example usage
-------------

This example produces the same result as the example for LDAPi

    <?php

    $link = (new SimpleLDAP\DirectoryFactory)->create('ldap://Manager:managerpassword@127.0.0.1:389');

    foreach ($link->search('cn=Users', 'objectClass=User', ['cn']) as $entry) {
        print_r($entry->getAttributes());
    }
