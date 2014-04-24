<?php
//	class Message
//	a message to be printed to the user. Can be message, warning or error. 
//
class Message {

	private $type, $text, $html;

	// create a new message
	function __construct($text, $type="message", $html=false)
	{
		$this->type = $type;
		$this->text = $text;
		$this->html = $html;
	}

	// get the message as HTML
	public function __toString()
	{
		global $lang;
		$html  = '<div class="confirm message_'.$this->type.'">';
		if($this->type=='error')
			$lang_key = 'err';
		else
			$lang_key = $this->type;
		if($this->type!='message')
			$html .= '<div class="messagetype">'.$lang[$lang_key].'</div>';
		
		if($this->html)
			$html.=$this->text;
		else
			$html.=htmlencode($this->text);
		$html .= '</div>';
		return $html;
	}

}

