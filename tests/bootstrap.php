<?php
/**
 * @author     Chris Wright <https://github.com/DaveRandom>
 * @copyright  Copyright (c) 2013 Chris Wright
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    1.0.0
 */

namespace SimpleLDAPTest;

date_default_timezone_set('UTC');

spl_autoload_register(function($className) {
    if (substr($className, 0, strlen(__NAMESPACE__)) === __NAMESPACE__) {
        $path = __DIR__ . strtr('\\', '/', substr($className, 14)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

require_once __DIR__ . '/../vendor/autoload.php';
