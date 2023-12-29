<?php

class GetParameters
{
	public function __construct(private array $_fields = [])
    {
    }

	public function __set($key, $value)
	{
		$this->_fields[$key] = $value;
	}

	public function __isset($key)
	{
		return isset($this->_fields[$key]);
	}

	public function __unset($key)
	{
		unset($this->_fields[$key]);
	}

	public function __get($key)
	{
		return $this->_fields[$key];
	}

	public function getURL(array $assoc = [], $html = true, $prefix='?'): string
    {
		$arg_sep = ($html?'&amp;':'&');
		return $prefix . http_build_query(array_merge($this->_fields, $assoc), '', $arg_sep);
	}

	public function getLink(array $assoc = [], $content = '[ link ]', $class = '', $title = '', $target=''): string
    {
		return '<a href="' . $this->getURL($assoc) . '"'
			. ($class  != '' ? ' class="'  . $class .  '"' : '')
			. ($title  != '' ? ' title="'  . $title .  '"' : '')
			. ($target != '' ? ' target="' . $target . '"' : '')
			. '>' . $content . '</a>';
	}

	public function getForm(array $assoc = [], $method = 'post', $upload = false, $name = '', $csrf = true): string
    {
		$hidden = '';
		if($method == 'get')
		{
			$url = '';
			foreach(array_merge($this->_fields, $assoc) as $key => $value)
			{
				if(!is_null($value))
					$hidden .= '<input type="hidden" name="'.htmlencode($key).'" value="'.htmlencode($value).'" /> ';
			}
		}
		else
			$url = $this->getURL($assoc);

		if($csrf && $method == 'post')
			$hidden .= '<input type="hidden" name="token" value="'.$_SESSION[COOKIENAME.'token'].'" />';

		return "<form action='". $url ."' method='" . $method . "'" .
			($name!=''? " name='". $name ."'" : '') .
			($upload? " enctype='multipart/form-data'" : '') . ">" .
			$hidden;
	}

	public function redirect(array $assoc = [], string $message="")
	{
		if($message!="")
		{
			$_SESSION[COOKIENAME.'messages'][md5($message)] = $message;
			$url = $this->getURL(array_merge($assoc, ['message'=>md5($message)]), false);
		}
		else
			$url = $this->getURL($assoc, false);

		$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http');

		header("Location: ".$protocol."://".$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].$url, true, 302);
		exit;
	}
}
