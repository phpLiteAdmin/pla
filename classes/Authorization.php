<?php
// Authorization class
// Maintains user's logged-in state and security of application
//

class Authorization
{
	private bool $authorized;
	private bool $login_failed;
	private string $system_password_encrypted;

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
			$_SESSION[COOKIENAME.'_salt'] = self::generateSalt(22);
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
			|| isset($_SESSION[COOKIENAME.'password']) && hash_equals($_SESSION[COOKIENAME.'password'], $this->system_password_encrypted)
			// correct password stored in cookie
			|| isset($_COOKIE[COOKIENAME]) && isset($_COOKIE[COOKIENAME.'_salt']) && hash_equals(md5(SYSTEMPASSWORD."_".$_COOKIE[COOKIENAME.'_salt']), $_COOKIE[COOKIENAME]);
	}

	public function attemptGrant($password, $remember): bool
    {
		$hashed_password = crypt(SYSTEMPASSWORD, '$2a$07$'.self::generateSalt(22).'$');
		if (hash_equals($hashed_password, crypt((string) $password, $hashed_password))) {
			if ($remember) {
				// user wants to be remembered, so set a cookie
				$expire = time()+60*60*24*30; //set expiration to 1 month from now
				setcookie(COOKIENAME, $this->system_password_encrypted, ['expires' => $expire, 'path' => '', 'domain' => '', 'secure' => null, 'httponly' => true]);
				setcookie(COOKIENAME."_salt", (string) $_SESSION[COOKIENAME.'_salt'], ['expires' => $expire, 'path' => '', 'domain' => '', 'secure' => null, 'httponly' => true]);
			} else {
				// user does not want to be remembered, so destroy any potential cookies
				setcookie(COOKIENAME, "", ['expires' => time()-86400, 'path' => '', 'domain' => '', 'secure' => null, 'httponly' => true]);
				setcookie(COOKIENAME."_salt", "", ['expires' => time()-86400, 'path' => '', 'domain' => '', 'secure' => null, 'httponly' => true]);
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

	public function revoke(): void
	{
		//destroy everything - cookies and session vars
		setcookie(COOKIENAME, "", ['expires' => time()-86400, 'path' => '', 'domain' => '', 'secure' => null, 'httponly' => true]);
		setcookie(COOKIENAME."_salt", "", ['expires' => time()-86400, 'path' => '', 'domain' => '', 'secure' => null, 'httponly' => true]);
		unset($_COOKIE[COOKIENAME]);
		unset($_COOKIE[COOKIENAME.'_salt']);
		session_unset();
		session_destroy();
		$this->authorized = false;
		// start a new session and generate a new CSRF token for the login form
		session_start();
		$this->generateToken();
	}

	public function isAuthorized(): bool
    {
		return $this->authorized;
	}

	public function isFailedLogin(): bool
    {
		return $this->login_failed;
	}

	public function isPasswordDefault(): bool
    {
		return SYSTEMPASSWORD == 'admin';
	}

	private static function generateSalt(int $saltSize): string
    {
		$set = 'ABCDEFGHiJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$setLast = strlen($set) - 1;
		$salt = '';
		while ($saltSize-- > 0) {
			$salt .= $set[mt_rand(0, $setLast)];
		}
		return $salt;
	}

    private function generateToken(): void
	{
		// generate CSRF token
		if (empty($_SESSION[COOKIENAME.'token']))
		{
			$_SESSION[COOKIENAME.'token'] = bin2hex(random_bytes(32));
		}
	}

	private function checkToken(): void
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
			elseif(!hash_equals($_SESSION[COOKIENAME.'token'], $check_token))
			{
				die("CSRF token is wrong - please try to login again");
			}
		}
	}

}
