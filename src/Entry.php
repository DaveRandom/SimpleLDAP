<?php
/**
 * @author     Chris Wright <https://github.com/DaveRandom>
 * @copyright  Copyright (c) 2013 Chris Wright
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    1.0.0
 */

namespace SimpleLDAP;

use LDAPi\Entry as LDAPi_Entry;

/**
 * Represents an entry in the directory
 */
class Entry implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * @var bool Whether the entry has been loaded from the directory
     */
    private $haveEntry = false;

    /**
     * @var string
     */
    private $dn;

    /**
     * @var string[]
     */
    private $attributes = [];

    /**
     * @var Directory
     */
    private $directory;

    /**
     * Convert an ext/ldap formatted entry array to a sane format and store it
     *
     * @param array $attrs
     */
    private function storeAttributes(array $attrs)
    {
        for ($i = 0, $l = $attrs['count']; $i < $l; $i++) {
            $attr = $attrs[$i];
            $attrLower = strtolower($attr);

            if ($attrs[$attr]['count'] > 1) {
                $this->attributes[$attrLower] = $attrs[$attr];
                unset($this->attributes[$attrLower]['count']);
            } else {
                $this->attributes[$attrLower] = $attrs[$attr][0];
            }
        }
    }

    /**
     * @param Directory          $directory The directory that created this entry
     * @param LDAPi_Entry|string $entryOrDN LDAPi\Entry object for this entry or DN of entry
     */
    public function __construct(Directory $directory, $entryOrDN)
    {
        if ($this->haveEntry = ($entryOrDN instanceof LDAPi_Entry)) {
            $this->dn = $entryOrDN->getDN();
            $this->storeAttributes($entryOrDN->getAttributes());
        } else {
            $this->dn = (string)$entryOrDN;
        }

        $this->directory = $directory;
    }

    /**
     * Return the DN when the object is cast to a string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->dn;
    }

    /**
     * Lazy-load entry and return attribute array
     *
     * @return mixed[]
     */
    public function getAttributes()
    {
        if (!$this->haveEntry) {
            $this->refresh();
        }

        return $this->attributes;
    }

    /**
     * Return entry DN
     *
     * @return string
     */
    public function getDN()
    {
        return $this->dn;
    }

    /**
     * List direct descendants of this entry
     *
     * @param string   $filter     An LDAP filter string
     * @param string[] $attributes An array of attributes to fetch for the matched objects
     * @return ResultSet
     * @throws SimpleLDAPException
     */
    public function listChildren($filter = 'objectClass=*', $attributes = null)
    {
        return $this->directory->listChildren($this->dn, (string)$filter, $attributes);
    }

    /**
     * Search descendants of this entry
     *
     * @param string   $filter     An LDAP filter string
     * @param string[] $attributes An array of attributes to fetch for the matched objects
     * @return ResultSet
     * @throws SimpleLDAPException
     */
    public function search($filter, $attributes = null)
    {
        return $this->directory->search($this->dn, (string)$filter, $attributes);
    }

    /**
     * Get the parent object of this entry, based on the DN
     *
     * @return Entry|null
     */
    public function parent()
    {
        if ($parentDN = trim(implode(',', array_slice(explode(',', $this->dn), 1)))) {
            return new Entry($this->directory, $parentDN);
        }

        return null;
    }

    /**
     * Modify this entry
     *
     * @param mixed[] $attributes An array of attributes to modify
     * @throws SimpleLDAPException
     */
    public function modify(array $attributes)
    {
        $this->directory->modify($this->dn, $attributes);
    }

    /**
     * Add attribute values to this entry
     *
     * @param mixed[] $attributes An array of attributes to modify
     * @throws SimpleLDAPException
     */
    public function modAdd(array $attributes)
    {
        $this->directory->modAdd($this->dn, $attributes);
    }

    /**
     * Delete attribute values from this entry
     *
     * @param mixed[] $attributes An array of attributes to modify
     * @throws SimpleLDAPException
     */
    public function modDel(array $attributes)
    {
        $this->directory->modDel($this->dn, $attributes);
    }

    /**
     * Replace attribute values in this entry
     *
     * @param mixed[] $attributes An array of attributes to modify
     * @throws SimpleLDAPException
     */
    public function modReplace(array $attributes)
    {
        $this->directory->modReplace($this->dn, $attributes);
    }

    /**
     * Delete this entry
     *
     * @throws SimpleLDAPException
     */
    public function delete()
    {
        $this->directory->delete($this->dn);
    }

    /**
     * Reload this entry from the directory
     *
     * @throws SimpleLDAPException
     */
    public function refresh()
    {
        $this->attributes = $this->directory->read($this->dn)->getAttributes();
        $this->haveEntry = true;
    }

    /**
     * Check if this entry has an attribute by name
     *
     * @param string $attribute
     * @return bool
     * @throws SimpleLDAPException When a lazy-load operation fails
     */
    public function offsetExists($attribute)
    {
        if (!$this->haveEntry) {
            $this->refresh();
        }

        return isset($this->attributes[strtolower($attribute)]);
    }

    /**
     * Fetch the value of an attribute by name
     *
     * @param string $attribute
     * @return mixed
     * @throws SimpleLDAPException When a lazy-load operation fails
     */
    public function offsetGet($attribute)
    {
        if (!$this->haveEntry) {
            $this->refresh();
        }

        if (isset($this->attributes[$attrLower = strtolower($attribute)])) {
            return $this->attributes[$attrLower];
        }

        trigger_error('SimpleLDAP: Undefined attribute: ' . $attribute, E_USER_NOTICE);
        return null;
    }

    /**
     * Set the value of an attribute by name
     *
     * @param string $attribute
     * @param mixed  $value
     * @throws SimpleLDAPException
     */
    public function offsetSet($attribute, $value)
    {
        $this->modify([$attribute => $value]);
    }

    /**
     * Delete an attribute by name
     *
     * @param string $attribute
     * @throws SimpleLDAPException
     */
    public function offsetUnset($attribute)
    {
        $this->modDel([$attribute => []]);
    }

    /**
     * Get an iterator for this entry's attributes
     *
     * @return \ArrayIterator
     * @throws SimpleLDAPException When a lazy-load operation fails
     */
    public function getIterator()
    {
        if (!$this->haveEntry) {
            $this->refresh();
        }

        return new \ArrayIterator($this->attributes);
    }

    /**
     * Get the number of attributes in this entry
     *
     * @return int
     * @throws SimpleLDAPException When a lazy-load operation fails
     */
    public function count()
    {
        if (!$this->haveEntry) {
            $this->refresh();
        }

        return count($this->attributes);
    }
}
