<?php

/**
 * Move the key from the currently selected DB to the DB having as index dbindex
 * 
 * @author Ivan Shumkov
 * @package Rediska
 * @subpackage Commands
 * @version 0.5.1
 * @link http://rediska.geometria-lab.net
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class Rediska_Command_MoveToDb extends Rediska_Command_Abstract
{
    /**
     * Create command
     *
     * @param string  $key     Key name
     * @param integer $dbIndex Redis DB index
     * @return Rediska_Connection_Exec
     */
    public function create($key, $dbIndex)
    {
        if (!is_integer($dbIndex) || $dbIndex < 0) {
            throw new Rediska_Command_Exception("Index must be zero or positive integer");
        }

        $connection = $this->_rediska->getConnectionByKeyName($key);

        $command = "MOVE {$this->_rediska->getOption('namespace')}$key $dbIndex";
        
        return new Rediska_Connection_Exec($connection, $command);
    }

    /**
     * Parse response
     *
     * @param integer $response
     * @return boolean
     */
    public function parseResponse($response)
    {
        return (boolean)$response;
    }
}