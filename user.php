<?php
////////////////////////////////////////////////////////////////////////////
//                                                                        //
//                           phpLiteAdmin v2.0                            //
//                  <http://phpliteadmin.googlecode.com>                  //
//                                                                        //
////////////////////////////////////////////////////////////////////////////
// phpLiteAdmin is a web based tool to manage SQLite v2 and v3 databases. //
//   phpLiteAdmin and phpLiterAdmin merged on March 7, 2011 in order to   //
// provide a better and more comprehensive SQLite manager for all to use. //
////////////////////////////////////////////////////////////////////////////
//                                                                        //
//         phpLiteAdmin is released under the GNU GPLv3 license.          //
//               <http://www.gnu.org/licenses/gpl-3.0.txt>                //
//                                                                        //
//  This program is free software: you can redistribute it and/or modify  //
//  it under the terms of the GNU GPL as published by the Free Software   //
//  Foundation, either version 3 of the License, or (at your option) any  //
//                             later version.                             //
//                                                                        //
//  This program is distributed in the hope that it will be useful, but   //
//       WITHOUT ANY WARRANTY; without even the implied warranty of       //
//          MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.          //
//                                                                        //
////////////////////////////////////////////////////////////////////////////
//         Copyright (C) 2011 Dane Iracleous and Ian Aldrighetti          //
////////////////////////////////////////////////////////////////////////////
//                                                                        //
//         Developers: Dane Iracleous (daneiracleous@gmail.com),          //
// Ian Aldrighetti (ian.aldrighetti@gmail.com), George Flanagin & Digital //
//               Gaslight, Inc (george@digitalgaslight.com)               //
//                                                                        //
////////////////////////////////////////////////////////////////////////////

// Time for all the fun user stuff.

// Are you attempting to log in? Make sure phpLiteAdmin isn't locked down.
if(!empty($_REQUEST['proc_login']) && empty($config['lock_down']))
{
  // Get the username and password submitted by the user.
  $username = !empty($_REQUEST['username']) ? $_REQUEST['username'] : '';
  $password = !empty($_REQUEST['password']) ? $_REQUEST['password'] : '';

  // Do they wish to be remembered?
  $remember_me = !empty($_REQUEST['remember_me']);

  // Check the supplied credentials.
  if(!empty($config['users'][$username]) && $password == $config['users'][$username])
  {
    // Save the credentials for this session, for sure.
    $_SESSION['username'] = $username;
    $_SESSION['password'] = sha1($password);

    // Did they want to be remembered?
    if(!empty($remember_me))
    {
      setcookie($config['cookie_name'], implode('|', array(urlencode($username), sha1($password))), time() + 3600 * 24 * 30);
    }

    $config['login_success'] = true;
  }
  else
  {
    // Something went wrong...
    $config['login_message'] = 'Incorrect username or password.';
  }
}

// Now, is the session data empty? Check the cookie! Mmmm...
if(empty($_SESSION['username']) && empty($_SESSION['password']) && !empty($_COOKIE[$config['cookie_name']]))
{
  // There should be a pipe (|) in the cookie.
  if(strpos($_COOKIE[$config['cookie_name']], '|') !== false)
  {
    list($username, $password) = explode('|', $_COOKIE[$config['cookie_name']], 2);

    // Make sure it isn't empty... We don't need to check whether this
    // data is correct, either.
    if(!empty($username) && strlen($password) == 40)
    {
      $_SESSION['username'] = urldecode($username);
      $_SESSION['password'] = $password;
    }
  }
}

// Logging out..?
if(!empty($_GET['action']) && $_GET['action'] == 'logout')
{
  // Destroy the session data.
  session_destroy();

  // Delete the cookie.
  setcookie($config['cookie_name'], '', time() - 3600 * 24 * 30);

  $config['logout_message'] = 'You have been logged out.';
}

// Create an instance of the User class!
$user = new User();

/*
  Class: User
*/
class User
{
  // Variable: username
  private $username;

  // Variable: password
  private $password;

  // Variable: is_logged
  private $is_logged;

  /*
    Constructor: __construct

    Parameters:
      none
  */
  public function __construct()
  {
    global $config;

    // Set everything to blanks.
    $this->username = null;
    $this->password = null;
    $this->is_logged = false;

    // Time to validate their credentials.
    if(!empty($_SESSION['username']) && !empty($_SESSION['password']) && !empty($config['users'][$_SESSION['username']]) && $_SESSION['password'] == sha1($config['users'][$_SESSION['username']]))
    {
      // Yup, seems a-okay!
      $this->username = $_SESSION['username'];
      $this->password = $_SESSION['password'];
      $this->is_logged = true;
    }
  }

  /*
    Method: username

    Parameters:
      none

    Returns:
      string
  */
  public function username()
  {
    return $this->is_logged() ? $this->username : false;
  }

  /*
    Method: password

    Parameters:
      none

    Returns:
      string
  */
  public function password()
  {
    return $this->is_logged() ? $this->password : false;
  }

  /*
    Method: real_password

    Parameters:
      none

    Returns:
      string
  */
  public function real_password()
  {
    global $config;

    return $this->is_logged() ? $config['users'][$this->username()] : false;
  }

  /*
    Method: is_logged

    Parameters:
      none

    Returns:
      bool
  */
  public function is_logged()
  {
    return $this->is_logged;
  }

  /*
    Method: is_guest

    Parameters:
      none

    Returns:
      bool
  */
  public function is_guest()
  {
    return !$this->is_logged();
  }
}

/*
  Function: user

  Parameters:
    none

  Returns:
    object
*/
function user()
{
  global $user;

  return $user;
}
?>