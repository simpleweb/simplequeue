<?php
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(dirname(__FILE__) . '/library'),
    get_include_path(),
)));

require_once 'Zend/Loader/Autoloader.php';

Zend_Loader_Autoloader::getInstance();

/**
* Worker class, harvests urls from the queue and curls 'em
*
* @author Chris Mytton
* @package ContactZilla
* @subpackage Queue
*/
class Simple_Worker
{
    protected static $_instance;

    protected static $_config;

    public static function getInstance($config = null)
    {
        if (self::$_instance === null) {
            self::$_instance = new self($config);
        }

        return self::$_instance;
    }

    public static function start()
    {
        self::getInstance()->receive();
    }

    protected function __construct($config)
    {
        if (is_array($config)) {
            self::$_config = new Zend_Config($config);
        } elseif (is_string('config') && strripos($config, '.ini') == (strlen($config) - 4)) {
            self::$_config = new Zend_Config_Ini($config);
        } else {
            throw new Simple_Worker_Exception('Invalid config. Please pass an array or the location of an ini file.');
        }

        $this->_setup();
    }

    public function receive()
    {
        echo "Receiving messages...\n";
        while ($messages = $this->queue->receive(10)) {
            if (count($messages) === 0) {
                echo "No messages found for processing\n";
                sleep(5);
                continue;
            }

            echo "Got " . count($messages) . " messages\n";
            foreach ($messages as $message) {
                $msg = Zend_Json::decode($message->body);

                $this->_callJob($msg);
            }
        }
    }

    protected function _callJob($msg)
    {
        echo "Attempting to retrieve {$msg['url']}\n";
        $remote = curl_init($msg['url']);

        curl_setopt_array($remote, array(
            CURLOPT_RETURNTRANSFER  => true,
        ));

        $return = curl_exec($remote);

        /*
            TODO Check for 200 response
        */
    }

    protected function _setup()
    {
        $this->queue = new Zend_Queue('Db', self::$_config->default->toArray());
    }
}

class Simple_Worker_Exception extends Zend_Exception {}
