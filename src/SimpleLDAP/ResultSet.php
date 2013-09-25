<?php

namespace SimpleLDAP;

use LDAPi\ResultSet as LDAPi_ResultSet;

class ResultSet implements \ArrayAccess, \Iterator, \Countable
{
    private $directory;
    private $resultSet;

    private $count;

    private $currentEntry;
    private $entries = [];

    private $iterationPointer = 0;

    private function nextEntry()
    {
        if ($this->currentEntry) {
            $this->currentEntry = $this->currentEntry->nextEntry();
        } else {
            $this->currentEntry = $this->resultSet->firstEntry();
        }

        if (!$this->currentEntry) {
            $this->resultSet = null;
            return null;
        }

        return new Entry($this->directory, $this->currentEntry);
    }

    public function __construct(Directory $directory, LDAPi_ResultSet $resultSet)
    {
        $this->directory = $directory;
        $this->resultSet = $resultSet;
        $this->count = $this->resultSet->entryCount();
    }

    public function item($index)
    {
        if (isset($this->entries[$index])) {
            return $this->entries[$index];
        }

        if (!$this->offsetExists($index)) {
            trigger_error('SimpleLDAP: Undefined index in result set: ' . $attribute, E_USER_NOTICE);
            return null;
        }

        if ($this->directory->getOption(Directory::OPT_ENTRY_PERSISTENCE)) {
            for ($i = count($this->entries); $i <= $index; $i++) {
                $entry = $this->entries[$i] = $this->nextEntry();
            }
        } else {
            for ($i = count($this->entries); $i <= $index; $i++) {
                $entry = $this->nextEntry();
                $this->entries[$i] = null;
            }
        }

        return $entry;
    }

    public function offsetExists($index)
    {
        return $index >= 0 && $index < $this->count;
    }

    public function offsetGet($index)
    {
        return $this->item($index);
    }

    public function offsetSet($index, $value)
    {
        trigger_error('SimpleLDAP: Unable to assign index in result set', E_USER_NOTICE);
    }

    public function offsetUnset($attribute)
    {
        trigger_error('SimpleLDAP: Unable to assign index in result set', E_USER_NOTICE);
    }

    public function current()
    {
        return $this->item($this->iterationPointer);
    }

    public function key()
    {
        return $this->iterationPointer;
    }

    public function next()
    {
        $this->iterationPointer++;
    }

    public function valid()
    {
        return $this->iterationPointer < $this->count;
    }

    public function rewind()
    {
        $this->iterationPointer = 0;
    }

    public function count()
    {
        return $this->count;
    }
}
