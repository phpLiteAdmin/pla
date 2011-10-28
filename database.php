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

// Error codes:
// Database version could not be determined.
define('ERROR_DB_VERSION_ND', 1);

// Database supplied is not a file.
define('ERROR_DB_NOT_FILE', 2);

// Connection to the database failed.
define('ERROR_DB_CON_FAILED', 3);

/*
  Class: Database
*/
class Database
{
  // Variable: filename
  private $filename;

  // Variable: db
  private $db;

  // Variable: type
  private $type;

  // Variable: version
  private $version;

  /*
    Constructor: __construct

    Parameters:
      string $filename - The path to the database,
      int $version - The SQLite version used, if any.
      string $encryption_key - The encryption key used to secure the SQLite
                               database (only for version 3).
  */
  public function __construct($filename = null, $version = null, $encryption_key = null)
  {
    // Does the file already exist? Is NOT empty?
    if(file_exists($filename) && is_file($filename) && filesize($filename) > 0)
    {
      // No version supplied? Let's see if we can figure it out for
      // ourselves.
      if(empty($version) || !in_array((int)$version, array(2, 3)))
      {
        // Open up the file, read-only!
        $fp = fopen($filename, 'r');

        // First ~40 characters will tell us what we want...
        $data = fread($fp, 40);

        // So, what is it?
        if(stripos($data, '*** this file contains an sqlite 2') !== false)
        {
          // Version 2 it is...
          $version = 2;
        }
        elseif(stripos($data, '*** this file contains an sqlite 3') !== false)
        {
          $version = 3;
        }
        else
        {
          // I have no idea.
          database_error_handle(ERROR_DB_VERSION_ND);
        }
      }
    }
    elseif(!is_file($filename))
    {
      // The file is not, erm, a file.
      database_error_handle(ERROR_DB_NOT_FILE);
    }

    // Time to connect!
    if(class_exists('SQLite3') && (empty($version) || $version == 3))
    {
      $this->db = new SQLite3();

      // Any encryption key given?
      if(!empty($encryption_key))
      {
        $connected = $this->db->open($filename, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, $encryption_key);
      }
      else
      {
        $connected = $this->db->open($filename);
      }

      // Did the connection work?
      if(!$connected)
      {
        // That'd be a NO.
        database_error_handle(ERROR_DB_CON_FAILED, $this->db->lastErrorMsg());
      }

      // We are indeed using SQLite v3.
      $this->version = 3;
      $this->type = 'SQLite3';
    }
    elseif(class_exists('SQLiteDatabase') && (empty($version) || $version == 2))
    {
      // Connect using the SQLite v2 class.
      $this->db = new SQLiteDatabase($filename, 0666, $error_message);

      // Did it work?
      if(!is_object($this->db))
      {
        database_error_handle(ERROR_DB_CON_FAILED, $error_message);
      }

      $this->version = 2;
      $this->type = 'SQLiteDatabase';
    }
    elseif(class_exists('PDO'))
    {
      // PDO is our last hope...
      if(empty($version) || $version == 3)
      {
        try
        {
          if(!empty($encryption_key))
          {
            $this->db = new PDO('sqlite:'. $filename, null, $encryption_key);
          }
          else
          {
            $this->db = new PDO('sqlite:'. $filename);
          }

          $this->version = 3;
          $this->type = 'PDO';
        }
        catch(Exception $e)
        {
          // If this failed and you didn't specify a version, that means one
          // of two things: SQLite 3 is not supported or the database is not
          // SQLite 3 but SQLite 2.
          if($version == 3)
          {
            database_error_handle(ERROR_DB_CON_FAILED, $e->getMessage());
          }

          $this->db = null;
        }
      }

      if(empty($this->db))
      {
        // How about trying SQLite 2?
        try
        {
          $this->db = new PDO('sqlite2:'. $filename);

          $this->version = 2;
          $this->type = 'PDO';
        }
        catch(Exception $e)
        {
          database_error_handle(ERROR_DB_CON_FAILED, $e->getMessage());
        }
      }
    }
    else
    {
      // Interesting... Nothing worked.
      database_error_handle(ERROR_DB_CON_FAILED, 'An unknown error occurred.');
    }

    // Save the file name.
    $this->filename = realpath($filename);
  }

  /*
    Destructor: __destruct
  */
  public function __destruct()
  {
    if($this->connected())
    {
      $this->close();
    }
  }

  /*
    Function: connected
  */
  public function connected()
  {
    return is_object($this->db);
  }

  /*
    Function: type
  */
  public function type()
  {
    return $this->connected() ? $this->type : false;
  }

  /*
    Function: version
  */
  public function version()
  {
    return $this->connected() ? $this->version : false;
  }

  /*
    Function: filename
  */
  public function filename()
  {
    return $this->connected() ? $this->filename : false;
  }

  /*
    Function: label
  */
  public function label()
  {
    global $config;
    static $label_cache = null;

    if(!$this->connected())
    {
      return false;
    }
    elseif(!empty($label_cache))
    {
      return $label_cache;
    }

    // Iterate until we find the database...
    foreach($config['databases'] as $db)
    {
      // Are the paths the same?
      if(realpath($db['filename']) != $this->filename())
      {
        // Nope.
        continue;
      }

      // Found it!
      // But is there a label?
      if(empty($db['label']))
      {
        // There is none, so just use the filename.
        $db['label'] = basename($db['filename']);
      }

      // This way we won't have to do this again next time.
      $label_cache = $db['label'];

      return $label_cache;
    }
  }

  /*
    Function: filesize
  */
  public function filesize()
  {
    if(!$this->connected())
    {
      return false;
    }

    // Get the size of the file.
    $size = filesize($this->filename());

    $sizes = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $total = count($sizes);

    for($i = 0; $size > 1024 && $i < $total; $i++)
    {
      $size /= 1024;
    }

    return round($size, 2). $sizes[$i];
  }

  /*
    Function: last_modified
  */
  public function last_modified()
  {
    if(!$this->connected())
    {
      return false;
    }

    return date('F j, Y \a\t g:i:sa', filemtime($this->filename()));
  }
}

/*
  Function: database_error_handle

  Handles database errors.

  Parameters:
    int $error_code
    string $error_message

  Returns:
    void - Nothing is returned by this function.
*/
function database_error_handle($error_code, $error_message = null)
{
  echo 'Error occurred...';
  exit;
}
?>