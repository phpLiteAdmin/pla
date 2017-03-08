<?php
// Authorization class
// Maintains user's logged-in state and security of application
//
class Authorization
{
	private $authorized;
	private $login_failed;
	private $system_password_encrypted;

	public function __construct()
	{
		// first, make sure a CSRF token is generated
		$this->generateToken();
		// second, check for possible CSRF attacks. to protect logins, this is done before checking login
		$this->checkToken();
		
		// the salt and password encrypting is probably unnecessary protection but is done just
		// for the sake of being very secure
		if(!isset($_SESSION[COOKIENAME.'_salt']) && !isset($_COOKIE[COOKIENAME.'_salt']))
		{
			// create a random salt for this session if a cookie doesn't already exist for it
			$_SESSION[COOKIENAME.'_salt'] = self::generateSalt(20);
		}
		else if(!isset($_SESSION[COOKIENAME.'_salt']) && isset($_COOKIE[COOKIENAME.'_salt']))
		{
			// session doesn't exist, but cookie does so grab it
			$_SESSION[COOKIENAME.'_salt'] = $_COOKIE[COOKIENAME.'_salt'];
		}

		// salted and encrypted password used for checking
		$this->system_password_encrypted = md5(SYSTEMPASSWORD."_".$_SESSION[COOKIENAME.'_salt']);

		$this->authorized =
			// no password
			SYSTEMPASSWORD == ''
			// correct password stored in session
			|| isset($_SESSION[COOKIENAME.'password']) && $_SESSION[COOKIENAME.'password'] == $this->system_password_encrypted 
			// correct password stored in cookie
			|| isset($_COOKIE[COOKIENAME]) && isset($_COOKIE[COOKIENAME.'_salt']) && md5(SYSTEMPASSWORD."_".$_COOKIE[COOKIENAME.'_salt']) == $_COOKIE[COOKIENAME];
	}

	public function attemptGrant($password, $remember)
	{
		if ($password == SYSTEMPASSWORD) {
			if ($remember) {
				// user wants to be remembered, so set a cookie
				$expire = time()+60*60*24*30; //set expiration to 1 month from now
				setcookie(COOKIENAME, $this->system_password_encrypted, $expire, null, null, null, true);
				setcookie(COOKIENAME."_salt", $_SESSION[COOKIENAME.'_salt'], $expire, null, null, null, true);
			} else {
				// user does not want to be remembered, so destroy any potential cookies
				setcookie(COOKIENAME, "", time()-86400, null, null, null, true);
				setcookie(COOKIENAME."_salt", "", time()-86400, null, null, null, true);
				unset($_COOKIE[COOKIENAME]);
				unset($_COOKIE[COOKIENAME.'_salt']);
			}

			$_SESSION[COOKIENAME.'password'] = $this->system_password_encrypted;
			$this->authorized = true;
			return true;
		}

		$this->login_failed = true;
		return false;
	}

	public function revoke()
	{
		//destroy everything - cookies and session vars
		setcookie(COOKIENAME, "", time()-86400, null, null, null, true);
		setcookie(COOKIENAME."_salt", "", time()-86400, null, null, null, true);
		unset($_COOKIE[COOKIENAME]);
		unset($_COOKIE[COOKIENAME.'_salt']);
		session_unset();
		session_destroy();
		$this->authorized = false;
		// start a new session and generate a new CSRF token for the login form
		session_start();
		$this->generateToken();
	}

	public function isAuthorized()
	{
		return $this->authorized;      
	}

	public function isFailedLogin()
	{
		return $this->login_failed;
	}

	public function isPasswordDefault()
	{
		return SYSTEMPASSWORD == 'admin';
	}

	private static function generateSalt($saltSize)
	{
		$set = 'ABCDEFGHiJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$setLast = strlen($set) - 1;
		$salt = '';
		while ($saltSize-- > 0) {
			$salt .= $set[mt_rand(0, $setLast)];
		}
		return $salt;
	}
	
	private function generateToken()
	{
		// generate CSRF token 
		if (empty($_SESSION[COOKIENAME.'token']))
		{
			if (function_exists('random_bytes')) // introduced in PHP 7.0
			{
				$_SESSION[COOKIENAME.'token'] = bin2hex(random_bytes(32));
			}
			elseif (function_exists('openssl_random_pseudo_bytes')) // introduced in PHP 5.3.0
			{
				$_SESSION[COOKIENAME.'token'] = bin2hex(openssl_random_pseudo_bytes(32));
			}
			else
			{
				// For PHP 5.2.x - This case can be removed once we drop support for 5.2.x
				$_SESSION[COOKIENAME.'token'] = bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM));
			}
		}
	}
	
	private function checkToken()
	{
		// checking CSRF token
		if($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['download'])) // all POST forms need tokens! downloads are protected as well
		{
			if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token']))
				$check_token=$_POST['token'];
			elseif($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token']))
				$check_token=$_GET['token'];
			
			if (!isset($check_token))
			{
				die("CSRF token missing");
			}
			elseif	((function_exists('hash_equals') && !hash_equals($_SESSION[COOKIENAME.'token'], $check_token)) ||
					 (!function_exists('hash_equals') && $_SESSION[COOKIENAME.'token']!==$check_token) )   // yes, timing attacks might be possible here. update your php ;)
			{
				die("CSRF token is wrong - please try to login again");
			}
		}
	}

}
