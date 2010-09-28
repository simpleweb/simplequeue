<?php

/**
 * Abstract class for provide options to Rediska components
 * 
 * @author Ivan Shumkov
 * @package Rediska
 * @version 0.5.0
 * @link http://rediska.geometria-lab.net
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
abstract class Rediska_Options
{
    protected $_options = array();

    /**
     * Exception class name for Rediska setter and getter
     * 
     * @var string
     */
    protected $_optionsException = 'Rediska_Exception';

    public function __construct(array $options = array()) 
    {
        $options = array_change_key_case($options, CASE_LOWER);
        $options = array_merge($this->_options, $options);

        $this->setOptions($options);
    }

    /**
     * Set options array
     * 
     * @param array $options Options (see $_options description)
     * @return Rediska_Options
     */
    public function setOptions(array $options)
    {
        foreach($options as $name => $value) {
            if (method_exists($this, "set$name")) {
                call_user_func(array($this, "set$name"), $value);
            } else {
                $this->setOption($name, $value);
            }
        }

        return $this;
    }

    /**
     * Get associative array of options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Set option
     * 
     * @param string $name Name of option
     * @param mixed $value Value of option
     * @return Rediska_Options
     */
    public function setOption($name, $value)
    {
        $lowerName = strtolower($name);

        if (!array_key_exists($lowerName, $this->_options)) {
            throw new $this->_optionsException("Unknown option '$name'");
        }

        $this->_options[$lowerName] = $value;

        return $this;
    }

    /**
     * Get option
     *  
     * @param string $name Name of option
     * @return mixed
     */
    public function getOption($name)
    {
        $lowerName = strtolower($name);

        if (!array_key_exists($lowerName, $this->_options)) {
            throw new $this->_optionsException("Unknown option '$name'");
        }

        return $this->_options[$lowerName];
    }
}