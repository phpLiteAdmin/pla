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

// Keep track of how long this page takes to load.
define('STARTTIME', microtime(true), true);

// CONFIGURATION OPTIONS BELOW! Please be sure to look them over and change
// the default password, otherwise you could be causing a security risk!

// Accounts for logging into phpLiteAdmin can be added, edited, and removed
// here. The index is the username, and the value is the password.
$config['users'] = array(
                     'admin' => 'admin',
                     // Ex: 'theUserName' => 'theirPassword',
                   );

// These are the databases which are accessible through the phpLiteAdmin
// interface, and you can assign which users can access them.
// Each database requires its own array, containing the following indices:
//   string filename - The path to the SQLite database.
//   string label - The friendly name (label) for this database. (optional)
//   mixed access - An array containing the usernames of those who can
//                  access the database. For example: array('admin', 'u1')
//                  would mean that admin and u1 could read and write to the
//                  database. If you want every user to be able to access
//                  the database, set it to a string value of all, or do not
//                  specify this index.
//   int version - The version of SQLite the database uses (2 or 3), this is
//                 is optional and phpLiteAdmin will attempt to identify the
//                 version if none is supplied. However, it is recommended
//                 you supply the version for speed reasons. Note: If no
//                 version is supplied and the database does not exist, the
//                 database will be created using the newest version of
//                 SQLite supported.
//   string encryption_key - The encryption key used to secure the SQLite
//                           database, this is only for SQLite v3 databases.
$config['databases'] = array(
                         array(
                           'filename' => 'database.db',
                           'label' => 'Database #1',
                           'access' => array('admin'),
                         ),
                       );

// The name of the cookie which remembers the users log in information.
$config['cookie_name'] = 'phpLiteAdmin563';

// Whether or not to use a persistent handle to the SQLite databases, which
// can speed up load times... though for possible implications, see:
// <http://www.php.net/sqlite_popen>.
$config['persist'] = true;

// Allow people (who are logged in, of course) to view the information which
// is spewed out by the phpinfo() function in PHP.
$config['phpinfo'] = true;

// Lock down this phpLiteAdmin install? 1 = yes, 0 = no. If this is set to
// 1, then absolutely no one can log in no matter what, unless they change
// this back to 0.
$config['lock_down'] = 0;

// DO NOT EDIT BELOW THIS LINE! (Well, unless you know what you are doing!)

// Start the session.
session_start();

// Magic quotes is the root of all evil!
system_remove_magic();

// Magic quotes @ runtime could also be running as well, which is very
// simple to turn off.
if(function_exists('set_magic_quotes_runtime') && get_magic_quotes_runtime() == 1)
{
  @set_magic_quotes_runtime(false);
}

// Set a few useful constants.
define('VERSION', '2.0dev', true);
define('BASEURL', $_SERVER['PHP_SELF'], true);
define('BASEDIR', realpath(dirname(__FILE__)), true);

# COMPILE START
require_once('user.php');
# COMPILE END

// dummy, to be removed!
function system_remove_magic()
{

}
?>