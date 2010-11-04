<?php

/**
 * Select the DB having the specified index
 * 
 * @author Ivan Shumkov
 * @package Rediska
 * @subpackage Commands
 * @version 0.5.1
 * @link http://rediska.geometria-lab.net
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class Rediska_Command_SelectDb extends Rediska_Command_Abstract
{
    /**
     * Create command
     *
     * @param integer $index Db index
     * @return Rediska_Connection_Exec
     */
    public function create($index) 
    {
        if (!is_integer($index) || $index < 0) {
            throw new Rediska_Command_Exception("Index must be zero or positive integer");
        }

        $command = "SELECT $index";

        $commands = array();
        foreach($this->_rediska->getConnections() as $connection) {
            $commands[] = new Rediska_Connection_Exec($connection, $command);
        }

        return $commands;
    }

    /**
     * Parse responses
     *
     * @param array $responses
     * @return boolean
     */
    public function parseResponses($responses)
    {
        return true;
    }
}