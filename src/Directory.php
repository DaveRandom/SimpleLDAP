<?php
/**
 * @author     Chris Wright <https://github.com/DaveRandom>
 * @copyright  Copyright (c) 2013 Chris Wright
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    1.0.0
 */

namespace SimpleLDAP;

use LDAPi\Directory as LDAPi_Directory;
use LDAPi\ResultSet as LDAPi_ResultSet;

/**
 * Represents a connection to an LDAP directory
 */
class Directory implements \ArrayAccess
{
    // Connection security types
    const SECURITY_NONE = 0;
    const SECURITY_SSL  = 1;
    const SECURITY_TLS  = 2;

    // SimpleLDAP options
    const OPT_ENTRY_PERSISTENCE = 10000001;

    /**
     * @var mixed[] SimpleLDAP options applied to this instance
     */
    private $internalOptions = [
        self::OPT_ENTRY_PERSISTENCE => false
    ];

    /**
     * @var \LDAPi\Directory
     */
    private $directory;

    /**
     * @var int
     */
    private $securityType = self::SECURITY_NONE;

    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $user;

    /**
     * Factory method for ResultSet objects
     *
     * @param \LDAPi\ResultSet $result
     * @return ResultSet
     * @throws \LDAPi\EntryCountRetrievalFailureException
     */
    private function createResultSet(LDAPi_ResultSet $result)
    {
        return new ResultSet($this, $result);
    }

    /**
     * Factory method for Entry objects
     *
     * @param \LDAPi\Entry|string $result
     * @return Entry
     */
    private function createEntry($result)
    {
        return new Entry($this, $result);
    }

    /**
     * Validate and store the value for a SimpleLDAP option
     *
     * @param int   $option
     * @param mixed $value
     */
    private function setInternalOption($option, $value)
    {
        switch ($option) {
            case self::OPT_ENTRY_PERSISTENCE:
                $this->internalOptions[self::OPT_ENTRY_PERSISTENCE] = (bool) $value;
                break;
        }
    }

    /**
     * Connects to the directory
     *
     * @param \LDAPi\Directory $directory
     * @param string           $uri
     * @param string           $user
     * @param string           $pass
     * @param array            $options
     * @throws SimpleLDAPException
     */
    public function __construct(LDAPi_Directory $directory, $uri, $user = null, $pass = null, array $options = [])
    {
        $parts = parse_url($uri);

        if (!isset($parts['host'])) {
            if (substr($uri, 0, 2) === '//') {
                $parts = parse_url(substr($uri, 2));
            } else {
                $parts = parse_url('//' . $uri);
            }

            if (!isset($parts['host'])) {
                throw new SimpleLDAPException('Target host must be specified');
            }
        }

        $this->host = (string) $parts['host'];

        $scheme = 'ldap';
        if (isset($parts['scheme'])) {
            $scheme = strtolower($parts['scheme']);

            if ($scheme === 'ldaps') {
                $this->securityType = self::SECURITY_SSL;
            } else if ($scheme === 'tls') {
                $this->securityType = self::SECURITY_TLS;
                $scheme = 'ldap';
            } else if ($scheme !== 'ldap') {
                throw new SimpleLDAPException('URI scheme must be ldap, ldaps or tls');
            }
        }

        if (isset($parts['port'])) {
            $this->port = (int) $parts['port'];
        } else if ($this->securityType === self::SECURITY_SSL) {
            $this->port = 636;
        } else {
            $this->port = 389;
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
        } catch(\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to connect to directory: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        $this->directory = $directory;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return int
     */
    public function getSecurityType()
    {
        return $this->securityType;
    }

    /**
     * Add an entry
     *
     * @param string  $dn
     * @param mixed[] $entry
     * @throws SimpleLDAPException
     */
    public function add($dn, array $entry)
    {
        try {
            $this->directory->add($dn, $entry);
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to add ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Delete an entry
     *
     * @param string $dn
     * @throws SimpleLDAPException
     */
    public function delete($dn)
    {
        try {
            $this->directory->delete($dn);
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to delete ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get an option for this instance
     *
     * @param int $option
     * @return mixed
     * @throws SimpleLDAPException
     */
    public function getOption($option)
    {
        try {
            if (array_key_exists($option, $this->internalOptions)) {
                return $this->internalOptions[$option];
            }

            return $this->directory->getOption($option);
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to read value of option ' . $option . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * List direct descendants of an entry
     *
     * @param string   $dn         DN of the parent entry
     * @param string   $filter     An LDAP filter string
     * @param string[] $attributes An array of attributes to fetch for the matched objects
     * @return ResultSet
     * @throws SimpleLDAPException
     */
    public function listChildren($dn, $filter = 'objectClass=*', array $attributes = null)
    {
        try {
            return $this->createResultSet($this->directory->listChildren($dn, $filter, $attributes));
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to list children of ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Add attribute values to an entry
     *
     * @param string $dn    DN of the target entry
     * @param array  $entry An array of attributes to modify
     * @throws SimpleLDAPException
     */
    public function modAdd($dn, array $entry)
    {
        try {
            $this->directory->modAdd($dn, $entry);
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to modify ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Delete attribute values from an entry
     *
     * @param string $dn    DN of the target entry
     * @param array  $entry An array of attributes to modify
     * @throws SimpleLDAPException
     */
    public function modDel($dn, array $entry)
    {
        try {
            $this->directory->modDel($dn, $entry);
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to modify ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Replace attribute values in an entry
     *
     * @param string $dn    DN of the target entry
     * @param array  $entry An array of attributes to modify
     * @throws SimpleLDAPException
     */
    public function modReplace($dn, array $entry)
    {
        try {
            $this->directory->modReplace($dn, $entry);
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to modify ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Modify an entry
     *
     * @param string $dn    DN of the target entry
     * @param array  $entry An array of attributes to modify
     * @throws SimpleLDAPException
     */
    public function modify($dn, array $entry)
    {
        try {
            $this->directory->modify($dn, $entry);
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to modify ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Read a specific entry
     *
     * @param string   $dn         DN of the target entry
     * @param string[] $attributes An array of attributes to fetch for the matched object
     * @return Entry
     * @throws SimpleLDAPException
     */
    public function read($dn, array $attributes = null)
    {
        try {
            // DO NOT CHAIN firstEntry() ON TO THIS!!
            // Chaining will cause bad things to happen, the result will be prematurely freed
            $resultSet = $this->directory->read($dn, 'objectClass=*', $attributes);

            return $resultSet->entryCount()
                ? $this->createEntry($resultSet->firstEntry())
                : null;
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to read ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Rename an entry
     *
     * @param string $dn    DN of the target entry
     * @param string $newDN New DN for the entry
     * @throws SimpleLDAPException
     */
    public function rename($dn, $newDN)
    {
        $parts = explode(',', $newDN);

        $newRDN = array_shift($parts);

        if (!$parts) {
            $parts = array_slice(explode(',', $dn), 1);
        }
        $newParent = implode(',', $parts);

        try {
            $this->directory->rename($dn, $newRDN, $newParent);
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to rename ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Search descendants of an entry in the directory
     *
     * @param string   $dn         DN of the parent entry
     * @param string   $filter     An LDAP filter string
     * @param string[] $attributes An array of attributes to fetch for the matched objects
     * @return ResultSet
     * @throws SimpleLDAPException
     */
    public function search($dn, $filter = 'objectClass=*', array $attributes = null)
    {
        try {
            return $this->createResultSet($this->directory->search($dn, $filter, $attributes));
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to search children of ' . $dn . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Set an option for this instance
     *
     * @param int   $option
     * @param mixed $value
     * @throws SimpleLDAPException
     */
    public function setOption($option, $value)
    {
        try {
            if (array_key_exists($option, $this->internalOptions)) {
                $this->setInternalOption($option, $value);
            } else {
                $this->directory->setOption($option, $value);
            }
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Unable to write value of option ' . $option . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Check whether a entry exists
     *
     * @param string $dn
     * @return bool
     * @throws SimpleLDAPException
     */
    public function offsetExists($dn)
    {
        return $this->read($dn, ['objectClass']) !== null;
    }

    /**
     * Fetch an Entry for the supplied DN
     *
     * This method will always return an entry regardless of whether it exists.
     * Read operations against the returned entry will query the directory for
     * data.
     *
     * @param string $dn
     * @return Entry
     * @throws SimpleLDAPException
     */
    public function offsetGet($dn)
    {
        return $this->createEntry($dn);
    }

    /**
     * Add or modify an entry
     *
     * If an entry with the supplied DN exists, this is an alias of modify(),
     * otherwise it is an alias of add(). This function always queries the
     * directory to check whether the entry exists, as is result if the desired
     * operation is known, it is more efficient to call that method explicitly.
     *
     * @param string        $dn
     * @param mixed[]|Entry $entry
     * @throws SimpleLDAPException
     */
    public function offsetSet($dn, $entry)
    {
        $entry = $entry instanceof Entry ? $entry->getAttributes() : (array) $entry;

        if ($this->offsetExists($dn)) {
            $this->modify($dn, $entry);
        } else {
            $this->add($dn, $entry);
        }
    }

    /**
     * Alias of delete()
     *
     * @param string        $dn
     * @throws SimpleLDAPException
     */
    public function offsetUnset($dn)
    {
        $this->delete($dn);
    }
}
