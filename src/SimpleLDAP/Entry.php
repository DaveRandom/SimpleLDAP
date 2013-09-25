<?php

namespace SimpleLDAP;

use LDAPi\Entry as LDAPi_Entry;

class Entry implements \ArrayAccess, \IteratorAggregate, \Countable
{
    private $haveEntry = false;

    private $dn;
    private $attributes = [];

    private $directory;

    private function storeAttributes($attrs)
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

    public function __toString()
    {
        return $this->dn;
    }

    public function getAttributes()
    {
        if (!$this->haveEntry) {
            $this->refresh();
        }

        return $this->attributes;
    }

    public function getDN()
    {
        return $this->dn;
    }

    public function listChildren($filter = 'objectClass=*', $attributes = null)
    {
        return $this->directory->listChildren($this->dn, (string)$filter, $attributes);
    }

    public function search($filter, $attributes = null)
    {
        return $this->directory->search($this->dn, (string)$filter, $attributes);
    }

    public function parent($attributes = null)
    {
        if ($parentDN = trim(implode(',', array_slice(explode(',', $this->dn), 1)))) {
            return new Entry($this->directory, $parentDN);
        }

        return null;
    }

    public function modify(array $attributes)
    {
        $this->directory->modify($this->dn, $attributes);
    }

    public function modAdd(array $attributes)
    {
        $this->directory->modAdd($this->dn, $attributes);
    }

    public function modDel(array $attributes)
    {
        $this->directory->modDel($this->dn, $attributes);
    }

    public function modReplace(array $attributes)
    {
        $this->directory->modReplace($this->dn, $attributes);
    }

    public function delete()
    {
        $this->directory->delete($this->dn);
    }

    public function refresh()
    {
        $this->attributes = $this->directory->read($this->dn)->getAttributes();
        $this->haveEntry = true;
    }

    public function offsetExists($attribute)
    {
        if (!$this->haveEntry) {
            $this->refresh();
        }

        return isset($this->attributes[strtolower($attribute)]);
    }

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

    public function offsetSet($attribute, $value)
    {
        $this->modify([$attribute => $value]);
    }

    public function offsetUnset($attribute)
    {
        $this->modDel([$attribute => []]);
    }

    public function getIterator()
    {
        if (!$this->haveEntry) {
            $this->refresh();
        }

        return new \ArrayIterator($this->attributes);
    }

    public function count()
    {
        if (!$this->haveEntry) {
            $this->refresh();
        }

        return count($this->attributes);
    }
}
