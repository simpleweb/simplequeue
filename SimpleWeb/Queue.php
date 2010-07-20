<?php
class SimpleWeb_Queue extends Zend_Queue
{
  public function send($message, $timeout = null)
    {
        return $this->getAdapter()->send($message, null, $timeout);
    }
}
