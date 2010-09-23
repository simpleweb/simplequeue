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
    protected $_config;

    public function __construct($config)
    {
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
		
        print_r($msg);

		if(!array_key_exists('attempt', $msg)) {
			$msg['attempt'] = 1;
		} else {
			$msg['attempt'] += 1;
		}
		
        $this->log("Attempting to retrieve {$msg['url']}");

		$client = new Zend_Http_Client($msg['url']);
        
		$timeout = 30;
		if(array_key_exists('timeout', $msg)) {
			$timeout=$msg['timeout'];
		}
		
		$client->setConfig(array('timeout' => $timeout, 'maxredirects' => 5));
		
		//Deal with headers
		if(!array_key_exists('headers', $msg)) {
			$msg['headers'] = array();
		}
		
    	if(!array_key_exists('User-Agent', $msg['headers'])) {
			$msg['headers']['User-Agent'] = 'Simplequeue';
		}
        	
		if(!array_key_exists('Cache-Control', $msg['headers'])) {
			$msg['headers']['Cache-Control'] = 'no-cache';
		}
				
    	if(!array_key_exists('Connection', $msg['headers'])) {
			$msg['headers']['Connection'] = 'Keep-Alive';
		}
		
		foreach($msg['headers'] as $key => $value) {
			$client->setHeaders($key, $value);
		}
		
		if (array_key_exists('get', $msg)) {
		    $client->setParameterGet($msg['get']);
		    $client->setMethod(Zend_Http_Client::GET);
		} elseif (array_key_exists('post', $msg) && !empty($msg['post'])) {
			$client->setParameterPost($msg['post']);
		 	$client->setMethod(Zend_Http_Client::POST);
		}

		try
		{
			//Request the remote page.
			$response = $client->request();
			$status = $response->getStatus();

		} catch (Exception $e) {
			
			$status = $e->getCode() . ' - ' . $e->getMessage();
		}

		echo $response->getBody();

   		if($status==200) {
			$this->log('200 - Success (Deleting Message From Queue)');
			$this->queue->deleteMessage($message);
		} else {
			
			$this->log("{$status} - Failed.");
			$this->queue->deleteMessage($message);
			
			$maxRetries = 5;
			if(array_key_exists('maxretries',$msg)) {
				$maxRetries = $msg['maxretries'];
			}
		
			//Put back on to queue if we haven't hit max retries.
			if($msg['attempt'] < $maxRetries) {
				$this->queue->send($msg, time() + 300);
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
        
        $options['driverOptions']['unserializer'] = array('Zend_Json', 'decode');
        
        $this->queue = new Zend_Queue(new Rediska_Zend_Queue_Adapter_Redis($options), $options);
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
