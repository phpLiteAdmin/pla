<?php
//	class MessageQueue
//	manages result messages, warnings and error messages
//  multiple messages can be pushed at any time to the queue and will be printed
//  to the user when the page is rendered in the order they were pushed here
//
class MessageQueue {

	private $messages;

	function __construct()
	{
		$this->messages = array();
	}

	// push a message to the message Queue
	public function push($text, $type='message', $html=false, $fatal=false)
	{
		$message = new Message($text, $type, $html); 
		$this->messages[] = $message;
		if($fatal)
		{
			echo $this->__toString();
			echo "</body></html>";
			exit;
		}
	}
	
	// returns the message queue as printable HTML and cleans it 
	public function __toString()
	{
		$temp = implode("\n",$this->messages);
		$this->messages = array();
		return $temp;
	}

}

