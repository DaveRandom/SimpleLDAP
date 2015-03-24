<?php
/**
 * @author     Chris Wright <https://github.com/DaveRandom>
 * @copyright  Copyright (c) 2013 Chris Wright
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    1.0.0
 */

namespace SimpleLDAP;

use LDAPi\ResultSet as LDAPi_ResultSet;

/**
 * Represents an set of entries in the directory returned by a read operation
 */
class ResultSet implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * @var Directory
     */
    private $directory;

    /**
     * @var \LDAPi\ResultSet
     */
    private $resultSet;

    /**
     * @var int Number of results in the set
     */
    private $count;

    /**
     * @var \LDAPi\Entry The most recently retrieved entry
     */
    private $currentEntry;

    /**
     * @var Entry[]
     */
    private $entries = [];

    /**
     * @var int Pointer for the iterator interface
     */
    private $iterationPointer = 0;

    /**
     * Fetch the most recent entry from the LDAPi result set and create a new Entry object
     *
     * @return Entry|null
     */
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

    /**
     * @param Directory $directory
     * @param \LDAPi\ResultSet $resultSet
     * @throws \LDAPi\EntryCountRetrievalFailureException
     */
    public function __construct(Directory $directory, LDAPi_ResultSet $resultSet)
    {
        $this->directory = $directory;
        $this->resultSet = $resultSet;
        $this->count = $resultSet->entryCount();
    }

    /**
     * Fetch the entry at the specified index
     *
     * @param int $index
     * @return Entry
     * @throws SimpleLDAPException
     */
    public function item($index)
    {
        $index = (int) $index;

        if (isset($this->entries[$index])) {
            return $this->entries[$index];
        }

        if (!$this->offsetExists($index)) {
            throw new SimpleLDAPException('Undefined index in result set: ' . $index);
        }

        $i = -1;
        $entry = null;

        try {
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
        } catch (\Exception $e) {
            throw new SimpleLDAPException(
                'Error fetching entry at index ' . $i . ': ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        return $entry;
    }

    /**
     * Check if an index is valid in the result set
     *
     * @param int $index
     * @return bool
     */
    public function offsetExists($index)
    {
        return $index >= 0 && $index < $this->count;
    }

    /**
     * Alias of item()
     *
     * @param int $index
     * @return Entry
     * @throws SimpleLDAPException
     */
    public function offsetGet($index)
    {
        return $this->item($index);
    }

    /**
     * No effect, collection is read-only
     *
     * @param mixed $index
     * @param mixed $value
     * @throws SimpleLDAPException
     */
    public function offsetSet($index, $value)
    {
        throw new SimpleLDAPException('Members of result sets cannot be assigned');
    }

    /**
     * No effect, collection is read-only
     *
     * @param mixed $index
     * @throws SimpleLDAPException
     */
    public function offsetUnset($index)
    {
        throw new SimpleLDAPException('Members of result sets cannot be assigned');
    }

    /**
     * @return Entry
     * @throws SimpleLDAPException
     */
    public function current()
    {
        return $this->item($this->iterationPointer);
    }

    /**
     * @return int
     */
    public function key()
    {
        return $this->iterationPointer;
    }

    public function next()
    {
        $this->iterationPointer++;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->iterationPointer < $this->count;
    }

    public function rewind()
    {
        $this->iterationPointer = 0;
    }

    /**
     * @return int
     */
    public function count()
    {
        return $this->count;
    }
}
