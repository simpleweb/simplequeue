<?php

/**
 * Get sorted elements contained in the List, Set, or Sorted Set value at key.
 *
 * @author Ivan Shumkov
 * @package Rediska
 * @subpackage Commands
 * @version 0.5.1
 * @link http://rediska.geometria-lab.net
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class Rediska_Command_Sort extends Rediska_Command_Abstract
{
    const ASC  = 'asc';
    const DESC = 'desc';

    protected $_options = array(
        'order'  => self::ASC,
        'limit'  => null,
        'offset' => null,
        'alpha'  => false,
        'by'     => null,
        'get'    => null,
        'store'  => null,
    );

    /**
     * Create command
     *
     * @param string        $key   Key name
     * @param string|array  $value Options or SORT query string (http://code.google.com/p/redis/wiki/SortCommand).
     *                             Important notes for SORT query string:
     *                                 1. If you set Rediska namespace option don't forget add it to key names.
     *                                 2. If you use more then one connection to Redis servers, it will choose by key name,
     *                                    and key by you pattern's may not present on it.
     * @return Rediska_Connection_Exec
     */
    public function create($key, $options = array())
    {
        $connection = $this->_rediska->getConnectionByKeyName($key);

        $command = "SORT {$this->_rediska->getOption('namespace')}$key";

        if (!is_string($options)) {
            foreach($options as $name => $value) {
                if (!array_key_exists($name, $this->_options)) {
                    throw new Rediska_Command_Exception("Unknown option '$name'");
                }
                $this->_options[$name] = $value;
            }

            // Limit
            if (isset($this->_options['limit'])) {
                $offset = isset($this->_options['offset']) ? $this->_options['offset'] : 0;
                $command .= " LIMIT $offset {$this->_options['limit']}";
            }

            // Alpha
            if ($this->_options['alpha']) {
                $command .= " ALPHA";
            }

            // Order
            if (isset($this->_options['order'])) {
                $command .= ' ' . strtoupper($this->_options['order']);
            }

            // By
            if (isset($this->_options['by'])) {
                $this->_throwExceptionIfManyConnections('by');
                $command .= " BY {$this->_rediska->getOption('namespace')}{$this->_options['by']}";
            }

            // Get
            if (isset($this->_options['get'])) {
                $this->_throwExceptionIfManyConnections('get');
                if (!is_string($this->_options['get'])) {
                    foreach($this->_options['get'] as $pattern) {
                        $command .= ' GET ' . $this->_addNamespaceToGetIfNeeded($pattern);
                    }
                } else {
                    $command .= ' GET ' . $this->_addNamespaceToGetIfNeeded($this->_options['get']);
                }
            }

            // Store
            if (isset($this->_options['store'])) {
                $this->_throwExceptionIfManyConnections('store');
                $command .= " STORE {$this->_rediska->getOption('namespace')}{$this->_options['store']}";
            }
        } else {
            $command .= ' ' . $options;
        }

        return new Rediska_Connection_Exec($connection, $command);
    }

    /**
     * Parse response
     *
     * @param array $response
     * @return array
     */
    public function parseResponse($response)
    {
        return array_map(array($this->_rediska->getSerializer(), 'unserialize'), $response);
    }

    protected function _addNamespaceToGetIfNeeded($pattern)
    {
        if ($pattern != '#') {
            $pattern = $this->_rediska->getOption('namespace') . $pattern;
        } else {
            $this->_throwExceptionIfNotSupported('1.1');
        }

        return $pattern;
    }

    protected function _throwExceptionIfManyConnections($argument)
    {
        $connections = $this->_rediska->getConnections();
        if (count($connections) > 1) {
            throw new Rediska_Command_Exception("You can use '$argument' with only one connection. Use 'on' method to specify it.");
        }
    }
}