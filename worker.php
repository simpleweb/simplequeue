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

    function __construct($config)
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
        echo "Receiving messages...\n";

        while ($messages = $this->queue->receive(10)) {
            if (count($messages) === 0) {
                echo "No messages found for processing\n";
                sleep(5);
                continue;
            }

            echo "Got " . count($messages) . " messages\n";
            foreach ($messages as $message) {
                $this->_callJob($message);
            }
        }
    }

    protected function _callJob($message)
    {
		$msg = Zend_Json::decode($message->body);
		
		if(!array_key_exists('attempt', $msg)) {
			$msg['attempt'] = 1;
		} else {
			$msg['attempt'] += 1;
		}
		
        echo "Attempting to retrieve {$msg['url']}\n";

		$client = new Zend_Http_Client($msg['url']);
        
		$timeout = 30;
		if(array_key_exists('timeout', $msg)) {
			$timeout=$msg['timeout'];
		}
		
		$client->setConfig(array('timeout' => $timeout, 'maxredirects' => 0));
		
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
		
		$client->setParameterGet($msg['get']);
		 
		if(array_key_exists('post', $msg) && !empty($msg['post'])) {
			$client->setParameterPost($msg['post']);
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
		
   		if($status==200) {
			echo '200 - Success (Deleting Message From Queue)';
			$this->queue->deleteMessage($message);
		} else {
			
			echo $status.' - Failed.';
			$this->queue->deleteMessage($message);
			
			$maxRetries = 5;
			if(array_key_exists('maxretries',$msg)) {
				$maxRetries = $msg['maxretries'];
			}
		
			//Put back on to queue if we haven't hit max retries.
			if($msg['attempt'] < $maxRetries) {
				$this->queue->send(json_encode($msg), time() + 300);
			}
			
		}
			
			
    }

    protected function _setup()
    {
    	$options = $this->_config->default->toArray();
        $this->queue = new SimpleWeb_Queue(new SimpleWeb_Queue_Adapter_Db($options), $options);
    }

	public function iterateQueues() {
		
		$queue = $this->queue;

        foreach ($queue->getQueues() as $name) {
            echo $name, "\n";
        }
		
	}

}

class Simple_Worker_Exception extends Zend_Exception {}
