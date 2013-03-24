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

}
