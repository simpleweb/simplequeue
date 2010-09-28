<?php

/**
* Worker class, harvests urls from the queue and curls 'em
*
* @author Chris Mytton
* @package ContactZilla
* @subpackage Queue
*/
class Simple_Worker
{
    const VERSION = '0.0.1';

    protected $_config;
    protected $_successQueue;
    protected $_failQueue;

    public function __construct($config)
    {
        $this->log("SimpleWorker v".self::VERSION);

        if (is_array($config)) {
            $this->_config = new Zend_Config($config);
        } elseif (is_string('config') && strripos($config, '.ini') == (strlen($config) - 4)) {
            $this->_config = new Zend_Config_Ini($config);
        } else {
            throw new Simple_Worker_Exception('Invalid config. Please pass an array or the location of an ini file.');
        }

        $this->_setup();
    }

    public function process()
    {
        $this->log("Receiving messages...");

        while ($messages = $this->queue->receive(10)) {
            if (count($messages) === 0) {
                // No messages found for processing;
                sleep(5);
                continue;
            }

            $this->log("Got " . count($messages) . " messages");
            foreach ($messages as $message) {
                try {
                    $this->_callJob($message);
                } catch (Exception $e) {
                    $this->log("Failed to run job: {$e->getMessage()}\n{$message->body}");
                    $this->queue->deleteMessage($message);
                    $this->_failQueue->send($message->body);
                }
            }
        }
    }
    
    public function iterateQueues() {
		
		$queue = $this->queue;

        foreach ($queue->getQueues() as $name) {
            $this->log($name);
        }
		
	}

    protected function _callJob($message)
    {
		$msg = $message->body;

		if(!isset($msg->attempt)) {
			$msg->attempt = 1;
		} else {
			$msg->attempt += 1;
		}
		
        $this->log("Attempting to retrieve {$msg->url}");

		$client = new Zend_Http_Client($msg->url);
        
		$timeout = 30;
		if(isset($msg->timeout)) {
			$timeout = $msg->timeout;
		}
		
		$client->setConfig(array('timeout' => $timeout, 'maxredirects' => 5));
		
		//Deal with headers
		if(!isset($msg->headers)) {
			$msg->headers = array();
		} else {
            $msg->headers = (array) $msg->headers;
        }
		
    	if(!isset($msg->headers["User-Agent"])) {
			$msg->headers["User-Agent"] = 'Simplequeue';
		}
        	
		if(!isset($msg->headers['Cache-Control'])) {
			$msg->headers['Cache-Control'] = 'no-cache';
		}
				
    	if(!isset($msg->headers['Connection'])) {
			$msg->headers['Connection'] = 'Keep-Alive';
        }
		
		foreach($msg->headers as $key => $value) {
			$client->setHeaders($key, $value);
		}

        if (isset($msg->get)) {
            $client->setParameterGet((array) $msg->get);
        }

        if(isset($msg->post) && !empty($msg->post)) {
            $client->setParameterPost((array) $msg->post);
            $client->setMethod(Zend_Http_Client::POST);
        } else {
            $client->setMethod(Zend_Http_Client::GET);
        }

		try
		{
			//Request the remote page.
			$response = $client->request();
			$status = $response->getStatus();

		} catch (Exception $e) {
			
			$status = $e->getCode() . ' - ' . $e->getMessage();
		}

		echo $response->getBody() . "\n";

   		if($status==200) {
			$this->log('200 - Success (Deleting Message From Queue)');
			$msg->suceededAt = date('r');
			$msg->queue = $this->queue->getName();
            $this->_successQueue->send($msg);
			$this->queue->deleteMessage($message);
		} else {
			$this->log("{$status} - Failed.");
			$this->queue->deleteMessage($message);
			
			$maxRetries = 5;
			if(isset($msg->maxretries)) {
				$maxRetries = $msg->maxretries;
			}
		
			//Put back on to queue if we haven't hit max retries.
			if($msg->attempt < $maxRetries) {
				$this->queue->send($msg, time() + 300);
			} else {
                $this->_failQueue->send($msg);
            }
			
		}
			
			
    }

    protected function _setup()
    {
        $config = getenv('SIMPLEQUEUE_CONFIG');

        if (!$config) {
            $config = 'default';
        }

        $this->log("Using config {$config}");
        $options = $this->_config->{$config}->toArray();
        
        $this->queue = new Zend_Queue(new Rediska_Zend_Queue_Adapter_Redis($options), $options);

        $options['name'] = $options['successQueue'];
        $this->_successQueue = new Zend_Queue(new Rediska_Zend_Queue_Adapter_Redis($options), $options);
        $options['name'] = $options['failQueue'];
        $this->_failQueue = new Zend_Queue(new Rediska_Zend_Queue_Adapter_Redis($options), $options);
    }
    
    protected function log()
    {
        $timestamp = date('r');
        foreach (func_get_args() as $arg) {
            echo "[{$timestamp}] $arg\n";
        }
    }
}

class Simple_Worker_Exception extends Zend_Exception {}
