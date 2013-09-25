<?php

namespace SimpleLDAP;

use LDAPi\Directory as LDAPi_Directory,
    LDAPi\DirectoryOperationFailureException;

class Directory implements \ArrayAccess
{
    const SECURITY_NONE = 0;
    const SECURITY_SSL  = 1;
    const SECURITY_TLS  = 2;

    const OPT_ENTRY_PERSISTENCE = 10000001;

    private $internalOptions = [
        self::OPT_ENTRY_PERSISTENCE => false
    ];

    private $directory;

    private $securityType = self::SECURITY_NONE;
    private $host;
    private $port = 389;
    private $user;

    private function createResultSet($result)
    {
        return new ResultSet($this, $result);
    }

    private function createEntry($result)
    {
        return new Entry($this, $result);
    }

    private function setInternalOption($option, $value)
    {
        switch ($option) {
            case self::OPT_ENTRY_PERSISTENCE:
                $this->internalOptions[self::OPT_ENTRY_PERSISTENCE] = (bool) $value;
                break;
        }
    }

    public function __construct(LDAPi_Directory $directory, $uri, $user = null, $pass = null, array $options = [])
    {
        $parts = parse_url($uri);

        $scheme = 'ldap';
        if (isset($parts['scheme'])) {
            $scheme = strtolower($parts['scheme']);

            if ($scheme === 'ldaps') {
                $this->securityType = self::SECURITY_SSL;
            } else if ($scheme === 'tls') {
                $this->securityType = self::SECURITY_TLS;
                $scheme = 'ldap';
            } else if ($scheme !== 'ldap') {
                throw new \LogicException('URI scheme must be ldap, ldaps or tls');
            }
        }

        if (!isset($parts['host'])) {
            throw new \LogicException('Target host must be specified');
        }
        $this->host = (string) $parts['host'];

        if (isset($parts['port'])) {
            $this->port = (int) $parts['port'];
        }

        if (!isset($user) && isset($parts['user'])) {
            $this->user = urldecode($parts['user']);
        } else if (isset($user)) {
            $this->user = (string) $user;
        }

        if (!isset($pass) && isset($parts['pass'])) {
            $pass = urldecode($parts['pass']);
        }

        try {
            $directory->connect("$scheme://{$this->host}:{$this->port}");

            foreach ($options as $option => $value) {
                $directory->setOption($option, $value);
            }

            $directory->bind($this->user, $pass);

            if ($this->securityType === self::SECURITY_TLS) {
                $directory->startTLS();
            }
        } catch(DirectoryOperationFailureException $e) {
            throw new ConnectionFailureException('Unable to connect to directory', $e->getCode, $e);
        }

        $this->directory = $directory;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getSecurityType()
    {
        return $this->securityType;
    }

    public function add($dn, array $entry)
    {
        $this->directory->add($dn, $entry);
    }

    public function delete($dn)
    {
        $this->directory->delete($dn);
    }

    public function getOption($option)
    {
        if (array_key_exists($option, $this->internalOptions)) {
            return $this->internalOptions[$option];
        } else {
            return $this->directory->getOption($option);
        }
    }

    public function listChildren($dn, $filter = 'objectClass=*', array $attributes = null)
    {
        return $this->createResultSet($this->directory->listChildren($dn, $filter, $attributes));
    }

    public function modAdd($dn, array $entry)
    {
        $this->directory->modAdd($dn, $entry);
    }

    public function modDel($dn, array $entry)
    {
        $this->directory->modDel($dn, $entry);
    }

    public function modReplace($dn, array $entry)
    {
        $this->directory->modReplace($dn, $entry);
    }

    public function modify($dn, array $entry)
    {
        $this->directory->modify($dn, $entry);
    }

    public function read($dn, array $attributes = null)
    {
        // DO NOT CHAIN firstEntry() ON TO THIS!!
        // Chaining will cause bad things to happen, the result resource will be prematurely freed
        $resultSet = $this->directory->read($dn, 'objectClass=*', $attributes);
        return $resultSet->entryCount()
                   ? $this->createEntry($resultSet->firstEntry())
                   : null;
    }

    public function rename($dn, $newDN)
    {
    }

    public function search($dn, $filter = 'objectClass=*', array $attributes = null)
    {
        return $this->createResultSet($this->directory->search($dn, $filter, $attributes));
    }

    public function setOption($option, $value)
    {
        if (array_key_exists($option, $this->internalOptions)) {
            $this->setInternalOption($option, $value);
        } else {
            $this->directory->setOption($option, $value);
        }
    }

    public function offsetExists($dn)
    {
        return $this->read($dn, ['objectClass']) !== null;
    }

    public function offsetGet($dn)
    {
        return $this->createEntry($dn);
//        trigger_error('SimpleLDAP: Undefined entry: ' . $dn, E_USER_NOTICE);
//        return null;
    }

    public function offsetSet($dn, $entry)
    {
        $entry = $entry instanceof Entry ? $entry->getAttributes() : (array) $entry;

        if ($this->offsetExists($dn)) {
            $this->modify($dn, $entry);
        } else {
            $this->add($dn, $entry);
        }
    }

    public function offsetUnset($dn)
    {
        $this->delete($dn);
    }
}
