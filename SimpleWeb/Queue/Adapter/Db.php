<?php 
class SimpleWeb_Queue_Adapter_Db extends Zend_Queue_Adapter_Db
{
        public function send($message, Zend_Queue $queue = null, $timeout = null)
        {
        if ($queue === null) {
            $queue = $this->_queue;
        }

        if (is_scalar($message)) {
            $message = (string) $message;
        }
        if (is_string($message)) {
            $message = trim($message);
        }

        if (!$this->isExists($queue->getName())) {
            require_once 'Zend/Queue/Exception.php';
            throw new Zend_Queue_Exception('Queue does not exist:' .
$queue->getName());
        }

        $msg           = $this->_messageTable->createRow();
        $msg->queue_id = $this->getQueueId($queue->getName());
        $msg->created  = time();
        $msg->body     = $message;
        $msg->md5      = md5($message);
        $msg->timeout  = $timeout;

        if ($timeout) {
			$msg->handle = md5(uniqid(rand(), true));
		}
        
        try {
            $msg->save();
        } catch (Exception $e) {
            require_once 'Zend/Queue/Exception.php';
            throw new Zend_Queue_Exception($e->getMessage(), $e->getCode());
        }

        $options = array(
            'queue' => $queue,
            'data'  => $msg->toArray(),
        );

        $classname = $queue->getMessageClass();
        if (!class_exists($classname)) {
            require_once 'Zend/Loader.php';
            Zend_Loader::loadClass($classname);
        }
        return new $classname($options);
        }
}
