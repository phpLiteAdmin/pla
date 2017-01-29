<?php
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
//there is no reason for the average user to edit anything below this comment
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

# REMOVE_FROM_BUILD
// include default configuration and language
include './phpliteadmin.config.sample.php';
include './languages/lang_en.php';

// setup class autoloading
function pla_autoload($classname)
{
	$classfile = __DIR__ . '/classes/' . $classname . '.php';

	if (is_readable($classfile)) {
		include $classfile;
		return true;
	}
	return false;
}
spl_autoload_register('pla_autoload');
# END REMOVE_FROM_BUILD

//- Initialization

// load optional configuration file
$config_filename = './phpliteadmin.config.php';
if (is_readable($config_filename))
{
	include_once $config_filename;
}

//constants 1
define("PROJECT", "phpLiteAdmin");
define("VERSION", "1.9.8-dev");
define("FORCETYPE", false); //force the extension that will be used (set to false in almost all circumstances except debugging)
define("SYSTEMPASSWORD", $password); // Makes things easier.
define('PROJECT_URL','https://www.phpliteadmin.org/');
define('DONATE_URL','https://www.phpliteadmin.org/donate/');
define('VERSION_CHECK_URL','https://www.phpliteadmin.org/current_version.php');
define('PROJECT_BUGTRACKER_LINK','<a href="https://bitbucket.org/phpliteadmin/public/issues?status=new&status=open" target="_blank">https://bitbucket.org/phpliteadmin/public/issues?status=new&status=open</a>');
define('PROJECT_INSTALL_LINK','<a href="https://bitbucket.org/phpliteadmin/public/wiki/Installation" target="_blank">https://bitbucket.org/phpliteadmin/public/wiki/Installation</a>');

// Resource output (css and javascript files)
// we get out of the main code as soon as possible, without inizializing the session
if (isset($_GET['resource']))
{
	Resources::output($_GET['resource']);
	exit();
}

// don't mess with this - required for the login session
ini_set('session.cookie_httponly', '1');
session_start();

// version-number added so after updating, old session-data is not used anylonger
// cookies names cannot contain symbols, except underscores
define("COOKIENAME", preg_replace('/[^a-zA-Z0-9_]/', '_', $cookie_name . '_' . VERSION) );

$params = new GetParameters();

if($debug==true)
{
	ini_set("display_errors", 1);
	error_reporting(E_STRICT | E_ALL);
} else
{
	@ini_set("display_errors", 0);
}

// start the timer to record page load time
$pageTimer = new MicroTimer();

// load language file
if($language != 'en') {
 	$temp_lang=$lang;
	if(is_file('languages/lang_'.$language.'.php'))
		include('languages/lang_'.$language.'.php');
	elseif(is_file('lang_'.$language.'.php'))
		include('lang_'.$language.'.php');
	$lang = array_merge($temp_lang, $lang);
	unset($temp_lang);
}

// stripslashes if MAGIC QUOTES is turned on
// This is only a workaround. Please better turn off magic quotes!
// This code is from http://php.net/manual/en/security.magicquotes.disabling.php
if (get_magic_quotes_gpc()) {
	$process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
	while (list($key, $val) = each($process)) {
		foreach ($val as $k => $v) {
			unset($process[$key][$k]);
			if (is_array($v)) {
				$process[$key][stripslashes($k)] = $v;
				$process[] = &$process[$key][stripslashes($k)];
			} else {
				$process[$key][stripslashes($k)] = stripslashes($v);
			}
		}
	}
	unset($process);
}


//data types array
$sqlite_datatypes = array("INTEGER", "REAL", "TEXT", "BLOB","NUMERIC","BOOLEAN","DATETIME");

//available SQLite functions array (don't add anything here or there will be problems)
$sqlite_functions = array("abs", "hex", "length", "lower", "ltrim", "random", "round", "rtrim", "trim", "typeof", "upper");

//- Support functions

//function that allows SQL delimiter to be ignored inside comments or strings
function explode_sql($delimiter, $sql)
{
	$ign = array('"' => '"', "'" => "'", "/*" => "*/", "--" => "\n"); // Ignore sequences.
	$out = array();
	$last = 0;
	$slen = strlen($sql);
	$dlen = strlen($delimiter);
	$i = 0;
	while($i < $slen)
	{
		// Split on delimiter
		if($slen - $i >= $dlen && substr($sql, $i, $dlen) == $delimiter)
		{
			array_push($out, substr($sql, $last, $i - $last));
			$last = $i + $dlen;
			$i += $dlen;
			continue;
		}
		// Eat comments and string literals
		foreach($ign as $start => $end)
		{
			$ilen = strlen($start);
			if($slen - $i >= $ilen && substr($sql, $i, $ilen) == $start)
			{
				$i+=strlen($start);
				$elen = strlen($end);
				while($i < $slen)
				{
					if($slen - $i >= $elen && substr($sql, $i, $elen) == $end)
					{
						// SQL comment characters can be escaped by doubling the character. This recognizes and skips those.
						if($start == $end && $slen - $i >= $elen*2 && substr($sql, $i, $elen*2) == $end.$end)
						{
							$i += $elen * 2;
							continue;
						}
						else
						{
							$i += $elen;
							continue 3;
						}
					}
					$i++;
				}
				continue 2;
			}		
		}
		$i++;
	}
	if($last < $slen)
		array_push($out, substr($sql, $last, $slen - $last));
	return $out;
}

//function to scan entire directory tree and subdirectories
function dir_tree($dir)
{
	$path = array();
	$stack = array($dir);
	while($stack)
	{
		$thisdir = array_pop($stack);
		if($dircont = scandir($thisdir))
		{
			$i=0;
			while(isset($dircont[$i]))
			{
				if($dircont[$i] !== '.' && $dircont[$i] !== '..')
				{
					$current_file = $thisdir.DIRECTORY_SEPARATOR.$dircont[$i];
					if(is_file($current_file))
					{
						$path[] = $thisdir.DIRECTORY_SEPARATOR.$dircont[$i];
					}
					elseif (is_dir($current_file))
					{
						$path[] = $thisdir.DIRECTORY_SEPARATOR.$dircont[$i];
						$stack[] = $current_file;
					}
				}
				$i++;
			}
		}
	}
	return $path;
}

//the function echo the help [?] links to the documentation
function helpLink($name)
{
	global $lang;
	return "<a href='?help=1' onclick='openHelp(\"".$name."\"); return false;' class='helpq' title='".$lang['help'].": ".$name."' target='_blank'><span>[?]</span></a>";	
}

// function to encode value into HTML just like htmlentities, but with adjusted default settings
function htmlencode($value, $flags=ENT_QUOTES, $encoding ="UTF-8")
{
	return htmlentities($value, $flags, $encoding);
}

// 22 August 2011: gkf added this function to support display of
//                 default values in the form used to INSERT new data.
function deQuoteSQL($s)
{
	return trim(trim($s), "'");
}

// reduce string chars
function subString($str)
{
	global $charsNum, $params;
	if($charsNum > 10 && (!isset($params->fulltexts) || !$params->fulltexts) && strlen($str)>$charsNum)
	{
		$str = substr($str, 0, $charsNum).'...';
	}
	return $str;
}

// checks the (new) name of a database file  
function checkDbName($name)
{
	global $allowed_extensions;
	$info = pathinfo($name);
	if(isset($info['extension']) && !in_array($info['extension'], $allowed_extensions))
	{
		return false;
	} else
	{
		return (!is_file($name) && !is_dir($name));
	}

}

// check whether a path is a db managed by this tool
// requires that $databases is already filled!
// returns the key of the db if managed, false otherwise.
function isManagedDB($path)
{
	global $databases;
	foreach($databases as $db_key => $database)
	{
		if($path == $database['path'])
		{
			// a db we manage. Thats okay.
			// return the key.
			return $db_key;
		}
	}
	// not a db we manage!
	return false;
}

// from a typename of a colun, get the type of the column's affinty
// see https://www.sqlite.org/datatype3.html section 2.1 for rules
function get_type_affinity($type)
{
	if (preg_match("/INT/i", $type))
		return "INTEGER";
	else if (preg_match("/(?:CHAR|CLOB|TEXT)/i", $type))
		return "TEXT";
	else if (preg_match("/BLOB/i", $type) || $type=="")
		return "NONE";
	else if (preg_match("/(?:REAL|FLOA|DOUB)/i", $type))
		return "REAL";
	else
		return "NUMERIC";
}


//- Check user authentication, login and logout
$auth = new Authorization(); //create authorization object

// check if user has attempted to log out
if (isset($_POST['logout']))
	$auth->revoke();
// check if user has attempted to log in
else if (isset($_POST['login']) && isset($_POST['password']))
	$auth->attemptGrant($_POST['password'], isset($_POST['remember']));

//- Actions on database files and bulk data
if ($auth->isAuthorized())
{

	//- Create a new database
	if(isset($_POST['new_dbname']))
	{
		if($_POST['new_dbname']=='')
			$params->redirect(array('table'=>null), $lang['err'].': '.$lang['db_blank']);
		else
		{
			$str = preg_replace('@[^\w-.]@','', $_POST['new_dbname']);
			$dbname = $str;
			$dbpath = $str;
			if(checkDbName($dbname))
			{
				$tdata = array();	
				$tdata['name'] = $dbname;
				$tdata['path'] = $directory.DIRECTORY_SEPARATOR.$dbpath;
				if(isset($_POST['new_dbtype']))
					$tdata['type'] = $_POST['new_dbtype'];
				else
					$tdata['type'] = 3;
				$td = new Database($tdata);
				$td->query("VACUUM");
			} else
			{
				if(is_file($dbname) || is_dir($dbname))
					$params->redirect(array('view'=>'structure'),$lang['err'].': '.sprintf($lang['db_exists'], htmlencode($dbname)));
				else
					$params->redirect(array('view'=>'structure'),$lang['extension_not_allowed'].': '.implode(', ', array_map('htmlencode', $allowed_extensions)).'<br />'.$lang['add_allowed_extension']); 
			}
		}
	}
	
	//- Scan a directory for databases
	if($directory!==false)
	{
		if($directory[strlen($directory)-1]==DIRECTORY_SEPARATOR) //if user has a trailing slash in the directory, remove it
			$directory = substr($directory, 0, strlen($directory)-1);
			
		if(is_dir($directory)) //make sure the directory is valid
		{
			if($subdirectories===true)
				$arr = dir_tree($directory);
			else
				$arr = scandir($directory);
			$databases = array();
			$j = 0;
			for($i=0; $i<sizeof($arr); $i++) //iterate through all the files in the databases
			{
				if($subdirectories===false)
					$arr[$i] = $directory.DIRECTORY_SEPARATOR.$arr[$i];
				
				if(@!is_file($arr[$i])) continue;
				$con = file_get_contents($arr[$i], NULL, NULL, 0, 60);
				if(strpos($con, "** This file contains an SQLite 2.1 database **", 0)!==false || strpos($con, "SQLite format 3", 0)!==false)
				{
					$databases[$j]['path'] = $arr[$i];
					if($subdirectories===false)
						$databases[$j]['name'] = basename($arr[$i]);
					else
						$databases[$j]['name'] = $arr[$i];
					$databases[$j]['writable'] = is_writable($databases[$j]['path']);
					$databases[$j]['writable_dir'] = is_writable(dirname($databases[$j]['path']));
					$databases[$j]['readable'] = is_readable($databases[$j]['path']);
					$j++;
				}
			}
			// 22 August 2011: gkf fixed bug #50.
			sort($databases);
			if(isset($tdata))
			{
				foreach($databases as $db_id => $database)
				{
					if($database['path'] == $tdata['path'])
					{
						$currentDB = $database;
						$params->database = $database['path'];
						break;
					}
				}
			}
		}
		else //the directory is not valid - display error and exit
		{
			echo "<div class='confirm' style='margin:20px;'>".$lang['not_dir']."</div>";
			exit();
		}
	}
	else
	{
		for($i=0; $i<sizeof($databases); $i++)
		{
			if(!file_exists($databases[$i]['path']))
			{
				// the file does not exist and will be created when clicked, if permissions allow to
				$databases[$i]['writable'] = is_writable(dirname($databases[$i]['path']));
				$databases[$i]['writable_dir'] = is_writable(dirname($databases[$i]['path']));
				$databases[$i]['readable'] = is_writable(dirname($databases[$i]['path']));
			}
			else 
			{
				$databases[$i]['writable'] = is_writable($databases[$i]['path']);
				$databases[$i]['writable_dir'] = is_writable(dirname($databases[$i]['path']));
				$databases[$i]['readable'] = is_readable($databases[$i]['path']);
			}
		}
		sort($databases);
	}
	// we now have the $databases array set. Check whether selected DB is a managed Db (is in this array)
	if(!isset($currentDB) && (isset($_GET['database']) || isset($_POST['database']) ) )
	{
		$selected_db = ( isset($_POST['database']) ? $_POST['database'] : $_GET['database'] );
		$db_key = isManagedDB($selected_db);
		if($db_key!==false) {
			$currentDB = $databases[$db_key];
			$params->database = $databases[$db_key]['path'];
		}
	}
	
	//- Delete an existing database
	if(isset($_GET['database_delete']))
	{
		$dbpath = $_POST['database_delete'];
		// check whether $dbpath really is a db we manage
		$checkDB = isManagedDB($dbpath);
		if($checkDB !== false)
		{
			unlink($dbpath);
			unset($params->database);
			unset($currentDB);
			unset($databases[$checkDB]);
		} else die($lang['err'].': '.$lang['delete_only_managed']);
	}
	
	//- Rename an existing database
	if(isset($_GET['database_rename']))
	{
		$oldpath = $_POST['oldname'];
		$newpath = $_POST['newname'];
		$oldpath_parts = pathinfo($oldpath);
		$newpath_parts = pathinfo($newpath);
		// only rename?
		$newpath = $oldpath_parts['dirname'].DIRECTORY_SEPARATOR.basename($_POST['newname']);
		if($newpath != $_POST['newname'] && $subdirectories)
		{
			// it seems that the file should not only be renamed but additionally moved.
			// we need to make sure it stays within $directory...
			$new_realpath = realpath($newpath_parts['dirname']).DIRECTORY_SEPARATOR;
			$directory_realpath = realpath($directory).DIRECTORY_SEPARATOR;
			if(strpos($new_realpath, $directory_realpath)===0)
			{
				// its okay, the new directory is within $directory
				$newpath =  $_POST['newname'];
			}
			else $params->redirect(array('view'=>'rename'), $lang['err'].': '.$lang['db_moved_outside']);
		}
		
		if(checkDbName($newpath))
		{
			$checkDB = isManagedDB($oldpath);
			if($checkDB !==false )
			{
				rename($oldpath, $newpath);
				$databases[$checkDB]['path'] = $newpath;
				$databases[$checkDB]['name'] = basename($newpath);
				$currentDB = $databases[$checkDB];
				$params->database = $databases[$checkDB]['path'];
				$params->redirect(array('view'=>'rename'), sprintf($lang['db_renamed'], htmlencode($oldpath))." '".htmlencode($newpath)."'.");
			}
			else $params->redirect(array('view'=>'rename'), $lang['err'].': '.$lang['rename_only_managed']);
		}
		else
		{
			if(is_file($newpath) || is_dir($newpath))
				$params->redirect(array('view'=>'rename'), $lang['err'].": " . sprintf($lang['db_exists'], htmlencode($newpath)));
			else
				$params->redirect(array('view'=>'rename'), $lang['err'].": " . $lang['extension_not_allowed'].': '.implode(', ', array_map('htmlencode', $allowed_extensions)).'<br />'.$lang['add_allowed_extension']);
		}
	}

	
	//- Export (download a dump) an existing database
	if(isset($_POST['export']))
	{
		$export_filename = str_replace(array("\r", "\n"), '',$_POST['filename']); // against http header injection (php < 5.1.2 only)
		if($_POST['export_type']=="sql")
		{
			header('Content-Type: text/sql');
			header('Content-Disposition: attachment; filename="'.$export_filename.'.'.$_POST['export_type'].'";');
			if(isset($_POST['tables']))
				$tables = $_POST['tables'];
			else
			{
				$tables = array();
				$tables[0] = $_POST['single_table'];
			}
			$drop = isset($_POST['drop']);
			$structure = isset($_POST['structure']);
			$data = isset($_POST['data']);
			$transaction = isset($_POST['transaction']);
			$comments = isset($_POST['comments']);
			$db = new Database($currentDB);
			echo $db->export_sql($tables, $drop, $structure, $data, $transaction, $comments);
		}
		else if($_POST['export_type']=="csv")
		{
			header("Content-type: application/csv");
			header('Content-Disposition: attachment; filename="'.$export_filename.'.'.$_POST['export_type'].'";');
			header("Pragma: no-cache");
			header("Expires: 0");
			if(isset($_POST['tables']))
				$tables = $_POST['tables'];
			else
			{
				$tables = array();
				$tables[0] = $_POST['single_table'];
			}
			$field_terminate = $_POST['export_csv_fieldsterminated'];
			$field_enclosed = $_POST['export_csv_fieldsenclosed'];
			$field_escaped = $_POST['export_csv_fieldsescaped'];
			$null = $_POST['export_csv_replacenull'];
			$crlf = isset($_POST['export_csv_crlf']);
			$fields_in_first_row = isset($_POST['export_csv_fieldnames']);
			$db = new Database($currentDB);
			echo $db->export_csv($tables, $field_terminate, $field_enclosed, $field_escaped, $null, $crlf, $fields_in_first_row);
		}
		exit();
	}
	
	//- Import a file into an existing database
	if(isset($_POST['import']))
	{
		$db = new Database($currentDB);
		$db->registerUserFunction($custom_functions);
		if($_POST['import_type']=="sql")
		{
			$data = file_get_contents($_FILES["file"]["tmp_name"]);
			$importSuccess = $db->import_sql($data);
		}
		else
		{
			$field_terminate = $_POST['import_csv_fieldsterminated'];
			$field_enclosed = $_POST['import_csv_fieldsenclosed'];
			$field_escaped = $_POST['import_csv_fieldsescaped'];
			$null = $_POST['import_csv_replacenull'];
			$fields_in_first_row = isset($_POST['import_csv_fieldnames']);
			$importSuccess = $db->import_csv($_FILES["file"]["tmp_name"], $_POST['single_table'], $field_terminate, $field_enclosed, $field_escaped, $null, $fields_in_first_row);
		}
	}
	//- Download (backup) a database file (as SQLite file, not as dump)
	if(isset($_GET['download']) && isManagedDB($_GET['download'])!==false)
	{
		header("Content-type: application/octet-stream");
		header('Content-Disposition: attachment; filename="'.basename($_GET['download']).'";');
		header("Pragma: no-cache");
		header("Expires: 0");
		readfile($_GET['download']);
		exit;
	}
}

//- Select database (from session or first available)
if(!isset($currentDB) && count($databases)>0)
{
	//set the current database to the first existing one in the array (default)
	$currentDB = reset($databases);
	$params->database = $currentDB['path']; 
}

if(isset($currentDB))
{
	//- Open database (creates a Database object)
	$db = new Database($currentDB); //create the Database object
	$db->registerUserFunction($custom_functions);
}

// collect parameters early, just once
$target_table = isset($_GET['table']) ? $_GET['table'] : null;
// are we working on a view? let's check once here
$target_table_type = !is_null($target_table) ? $db->getTypeOfTable($target_table) : null;
if(is_null($target_table_type) && !is_null($target_table))
	$params->redirect(array('table'=>null), $lang['err'].': '.sprintf($lang['tbl_inexistent'], htmlencode($target_table)));	
$params->table = $target_table; 

//- Switch on $_GET['action'] for operations without output
if(isset($_GET['action']) && isset($_GET['confirm']))
{
	switch($_GET['action'])
	{
	//- Table actions

		//- Create table (=table_create)
		case "table_create":
			$num = intval($_POST['rows']);
			$name = $_POST['tablename'];
			$primary_keys = array();
			for($i=0; $i<$num; $i++)
			{
				if($_POST[$i.'_field']!="" && isset($_POST[$i.'_primarykey']))
				{
					$primary_keys[] = $_POST[$i.'_field'];
				}
			}
			$query = "CREATE TABLE ".$db->quote($name)." (";
			for($i=0; $i<$num; $i++)
			{
				if($_POST[$i.'_field']!="")
				{
					$query .= $db->quote($_POST[$i.'_field'])." ";
					$query .= $_POST[$i.'_type']." ";
					if(isset($_POST[$i.'_primarykey']))
					{
						if(count($primary_keys)==1)
						{
							$query .= "PRIMARY KEY "; 
							if(isset($_POST[$i.'_autoincrement']) && $db->getType() != "SQLiteDatabase")
								$query .=  "AUTOINCREMENT ";
						}
						$query .= "NOT NULL ";
					}
					if(!isset($_POST[$i.'_primarykey']) && isset($_POST[$i.'_notnull']))
						$query .= "NOT NULL ";
					if($_POST[$i.'_defaultoption']!='defined' && $_POST[$i.'_defaultoption']!='none' && $_POST[$i.'_defaultoption']!='expr')
						$query .= "DEFAULT ".$_POST[$i.'_defaultoption']." ";
					elseif($_POST[$i.'_defaultoption']=='expr')
						$query .= "DEFAULT (".$_POST[$i.'_defaultvalue'].") ";
					elseif(isset($_POST[$i.'_defaultvalue']) && $_POST[$i.'_defaultoption']=='defined')
					{
						$typeAffinity = get_type_affinity($_POST[$i.'_type']);
						if(($typeAffinity=="INTEGER" || $typeAffinity=="REAL" || $typeAffinity=="NUMERIC") && is_numeric($_POST[$i.'_defaultvalue']))
							$query .= "DEFAULT ".$_POST[$i.'_defaultvalue']."  ";
						else
							$query .= "DEFAULT ".$db->quote($_POST[$i.'_defaultvalue'])." ";
					}
					$query = substr($query, 0, sizeof($query)-2);
					$query .= ", ";
				}
			}
			if (count($primary_keys)>1)
			{
				$compound_key = "";
				foreach ($primary_keys as $primary_key)
				{
					$compound_key .= ($compound_key=="" ? "" : ", ") . $db->quote($primary_key);
				}
				$query .= "PRIMARY KEY (".$compound_key."), ";
			}
			$query = substr($query, 0, sizeof($query)-3);
			$query .= ")";
			$result = $db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['tbl']." '".htmlencode($_POST['tablename'])."' ".$lang['created'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
			$params->redirect(($result===false ? array() : array('action'=>'column_view', 'table'=>$name) ), $completed);
			break;

		//- Empty table (=table_empty)
		case "table_empty":
			$query1 = "DELETE FROM ".$db->quote_id($_GET['table']);
			$result = $db->query($query1);
			$query2 = "VACUUM";
			$result = $db->query($query2);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['tbl']." '".htmlencode($_GET['table'])."' ".$lang['emptied'].".<br/><span style='font-size:11px;'>".htmlencode($query1).";<br />".htmlencode($query2).";</span>";
			$params->redirect(($result===false ? array() : array('action'=>'row_view') ), $completed);
			break;

		//- Create view (=view_create)
		case "view_create":
			$query = "CREATE VIEW ".$db->quote($_POST['viewname'])." AS ".$_POST['select'];
			$result = $db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['view']." '".htmlencode($_POST['viewname'])."' ".$lang['created'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
			$params->redirect(($result===false ? array() : array('action'=>'column_view', 'table'=>$_POST['viewname']) ), $completed);
			break;

		//- Drop table (=table_drop)
		case "table_drop":
			$query = "DROP TABLE ".$db->quote_id($_GET['table']);
			$result=$db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
			{
				$target_table = null;
				$completed = $lang['tbl']." '".htmlencode($_GET['table'])."' ".$lang['dropped'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";;
			}
			$params->redirect(array('table'=>null), $completed);
			break;

		//- Drop view (=view_drop)
		case "view_drop":
			$query = "DROP VIEW ".$db->quote_id($_POST['viewname']);
			$result=$db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['view']." '".htmlencode($_POST['viewname'])."' ".$lang['dropped'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
			$params->redirect(array(), $completed);
			break;

		//- Rename table (=table_rename)
		case "table_rename":
			$query = "ALTER TABLE ".$db->quote_id($_GET['table'])." RENAME TO ".$db->quote($_POST['newname']);
			$type = $db->getTypeOfTable($_GET['table']);
			if($db->getVersion()==3 && $type=='table') // SQLite 3 can rename tables, not views
				$result = $db->query($query, true);
			else
				$result = $db->query($query, false); // workaround can rename tables of sqlite2 and views of both sqlite versions
			if($result === false)
				$completed = $db->getError(true);
			else
			{
				$completed = $lang['tbl']." '".htmlencode($_GET['table'])."' ".$lang['renamed']." '".htmlencode($_POST['newname'])."'.<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				$target_table = $_POST['newname'];
			}
			$params->redirect(array('action'=>'row_view', 'table'=>$_POST['newname']), $completed);
			break;

	//- Row actions

		//- Create row (=row_create)
		case "row_create":
			$completed = "";
			$num = $_GET['numRows'];
			$fields = explode(":", $_POST['fields']);
			$z = 0;
			
			$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
			$result = $db->selectArray($query);
			
			for($i=0; $i<$num; $i++)
			{
				if(!isset($_POST[$i.":ignore"]))
				{
					$query_cols = "";
					$query_vals = "";
					$all_default = true;
					for($j=0; $j<sizeof($fields); $j++)
					{
						if($result[$j]['name']!=$fields[$j])
							die($lang['err'].' - schema missmatch');
						
						$null = isset($_POST[$i.":".$j."_null"]);
						if(!$null)
							$value = $_POST[$i.":".$j];
						else
							$value = "";
						if($value===$result[$j]['dflt_value'])
						{
							// if the value is the default value, skip it
							continue;
						} else
							$all_default = false;
						$query_cols .= $db->quote_id($fields[$j]).",";
						
						$type = $result[$j]['type'];
						$typeAffinity = get_type_affinity($type);
						$function = $_POST["function_".$i."_".$j];
						if($function!="")
							$query_vals .= $function."(";
						if(($typeAffinity=="TEXT" || $typeAffinity=="NONE") && !$null)
							$query_vals .= $db->quote($value);
						elseif(($typeAffinity=="INTEGER" || $typeAffinity=="REAL"|| $typeAffinity=="NUMERIC") && $value=="")
							$query_vals .= "NULL";
						elseif($null)
							$query_vals .= "NULL";
						else
							$query_vals .= $db->quote($value);
						if($function!="")
							$query_vals .= ")";
						$query_vals .= ",";
					}
					$query = "INSERT INTO ".$db->quote_id($target_table);
					if(!$all_default)
					{
						$query_cols = substr($query_cols, 0, strlen($query_cols)-1);
						$query_vals = substr($query_vals, 0, strlen($query_vals)-1);
					
						$query.=" (". $query_cols . ") VALUES (". $query_vals. ")";
					} else {
						$query .= " DEFAULT VALUES";
					}
					$result1 = $db->query($query);
					if($result1===false)
						$error = true;
					$completed .= "<span style='font-size:11px;'>".htmlencode($query)."</span><br/>";
					$z++;
				}
			}
			if($error)
				$completed = $db->getError(true);
			else
				$completed = $z." ".$lang['rows']." ".$lang['inserted'].".<br/><br/>".$completed;
			$params->redirect(array('action'=>'row_view'), $completed);
			break;

		//- Delete row (=row_delete)
		case "row_delete":
			$pks = json_decode($_GET['pk']);
			
			$query = "DELETE FROM ".$db->quote_id($target_table)." WHERE (".$db->wherePK($target_table,json_decode($pks[0])).")";
			for($i=1; $i<sizeof($pks); $i++)
			{
				$query .= " OR (".$db->wherePK($target_table,json_decode($pks[$i])).")";
			}
			$result = $db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = sizeof($pks)." ".$lang['rows']." ".$lang['deleted'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
			$params->redirect(array('action'=>'row_view'), $completed);
			break;

		//- Edit row (=row_edit)
		case "row_edit":
			$pks = json_decode($_GET['pk']);
			$fields = explode(":", $_POST['fieldArray']);
			
			$z = 0;
			
			$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
			$result = $db->selectArray($query);
			
			if(isset($_POST['new_row']))
				$completed = "";
			else
				$completed = sizeof($pks)." ".$lang['rows']." ".$lang['affected'].".<br/><br/>";

			for($i=0; $i<sizeof($pks); $i++)
			{
				if(isset($_POST['new_row']))
				{
					$query_cols = "";
					$query_vals = "";
					$all_default = true;
					for($j=0; $j<sizeof($fields); $j++)
					{
						if($result[$j]['name']!=$fields[$j])
							die($lang['err'].' - schema missmatch');
						$null = isset($_POST[$j."_null"][$i]);
						if(!$null)
						{
							$value = $_POST[$j][$i];
						}
						else
							$value = "";
						if($value===$result[$j]['dflt_value'])
						{
							// if the value is the default value, skip it
							continue;
						} else
							$all_default = false;
						$query_cols .= $db->quote_id($fields[$j]).",";
						
						$type = $result[$j]['type'];
						$typeAffinity = get_type_affinity($type);
						$function = $_POST["function_".$j][$i];
						if($function!="")
							$query_vals .= $function."(";
						if(($typeAffinity=="TEXT" || $typeAffinity=="NONE") && !$null)
							$query_vals .= $db->quote($value);
						elseif(($typeAffinity=="INTEGER" || $typeAffinity=="REAL"|| $typeAffinity=="NUMERIC") && $value=="")
							$query_vals .= "NULL";
						elseif($null)
							$query_vals .= "NULL";
						else
							$query_vals .= $db->quote($value);
						if($function!="")
							$query_vals .= ")";
						$query_vals .= ",";
					}
					$query = "INSERT INTO ".$db->quote_id($target_table);
					if(!$all_default)
					{
						$query_cols = substr($query_cols, 0, strlen($query_cols)-1);
						$query_vals = substr($query_vals, 0, strlen($query_vals)-1);
					
						$query.=" (". $query_cols . ") VALUES (". $query_vals. ")";
					} else {
						$query .= " DEFAULT VALUES";
					}
					$result1 = $db->query($query);
					if($result1===false)
						$error = true;
					$z++;
				}
				else
				{
					$query = "UPDATE ".$db->quote_id($target_table)." SET ";
					for($j=0; $j<sizeof($fields); $j++)
					{
						$function = $_POST["function_".$j][$i];
						$null = isset($_POST[$j."_null"][$i]);
						$query .= $db->quote_id($fields[$j])."=";
						if($function!="")
							$query .= $function."(";
						if($null)
							$query .= "NULL";
						else
							$query .= $db->quote($_POST[$j][$i]);
						if($function!="")
							$query .= ")";
						$query .= ", ";
					}
					$query = substr($query, 0, sizeof($query)-3);
					$query .= " WHERE ".$db->wherePK($target_table, json_decode($pks[$i]));
					$result1 = $db->query($query);
					if($result1===false)
					{
						$error = true;
					}
				}
				$completed .= "<span style='font-size:11px;'>".htmlencode($query)."</span><br/>";
			}
			if($error)
				$completed = $db->getError(true);
			elseif(isset($_POST['new_row']))
				$completed = $z." ".$lang['rows']." ".$lang['inserted'].".<br/><br/>".$completed;
			$params->redirect(array('action'=>'row_view'), $completed);
			break;

	//- Column actions

		//- Create column (=column_create)
		case "column_create":
			$num = intval($_POST['rows']);
			for($i=0; $i<$num; $i++)
			{
				if($_POST[$i.'_field']!="")
				{
					$query = "ALTER TABLE ".$db->quote_id($target_table)." ADD ".$db->quote($_POST[$i.'_field'])." ";
					$query .= $_POST[$i.'_type']." ";
					if(isset($_POST[$i.'_primarykey']))
						$query .= "PRIMARY KEY ";
					if(isset($_POST[$i.'_notnull']))
						$query .= "NOT NULL ";
					if($_POST[$i.'_defaultoption']!='defined' && $_POST[$i.'_defaultoption']!='none' && $_POST[$i.'_defaultoption']!='expr')
						$query .= "DEFAULT ".$_POST[$i.'_defaultoption']." ";
					elseif($_POST[$i.'_defaultoption']=='expr')
						$query .= "DEFAULT (".$_POST[$i.'_defaultvalue'].") ";
					elseif(isset($_POST[$i.'_defaultvalue']) && $_POST[$i.'_defaultoption']=='defined')
					{
						$typeAffinity = get_type_affinity($_POST[$i.'_type']);
						if(($typeAffinity=="INTEGER" || $typeAffinity=="REAL" || $typeAffinity=="NUMERIC") && is_numeric($_POST[$i.'_defaultvalue']))
							$query .= "DEFAULT ".$_POST[$i.'_defaultvalue']."  ";
						else
							$query .= "DEFAULT ".$db->quote($_POST[$i.'_defaultvalue'])." ";
					}
					if($db->getVersion()==3 &&
						($_POST[$i.'_defaultoption']=='defined' || $_POST[$i.'_defaultoption']=='none' || $_POST[$i.'_defaultoption']=='NULL')
						// Sqlite3 cannot add columns with default values that are not constant
						&& !isset($_POST[$i.'_primarykey'])
						// sqlite3 cannot add primary key columns
						&& (!isset($_POST[$i.'_notnull']) || $_POST[$i.'_defaultoption']!='none')
						// SQLite3 cannot add NOT NULL columns without DEFAULT even if the table is empty
						)
						// use SQLITE3 ALTER TABLE ADD COLUMN 
						$result = $db->query($query, true);
					else
						// use ALTER TABLE workaround
						$result = $db->query($query, false);
					if($result===false)
						$error = true;
				}
			}
			if($error)
				$completed = $db->getError(true);
			else
				$completed = $lang['tbl']." '".htmlencode($target_table)."' ".$lang['altered'].".";
			$params->redirect(array('action'=>'column_view'), $completed);
			break;

		//- Delete column (=column_delete)
		case "column_delete":
			$pks = explode(":", $_GET['pk']);
			$query = "ALTER TABLE ".$db->quote_id($target_table).' DROP '.$db->quote_id($pks[0]);
			for($i=1; $i<sizeof($pks); $i++)
			{
				$query .= ", DROP ".$db->quote_id($pks[$i]);
			}
			$result = $db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['tbl']." '".htmlencode($target_table)."' ".$lang['altered'].".";
			$params->redirect(array('action'=>'column_view'), $completed);
			break;

		//- Add a primary key (=primarykey_add)
		case "primarykey_add":
			$pks = explode(":", $_GET['pk']);
			$query = "ALTER TABLE ".$db->quote_id($target_table).' ADD PRIMARY KEY ('.$db->quote_id($pks[0]);
			for($i=1; $i<sizeof($pks); $i++)
			{
				$query .= ", ".$db->quote_id($pks[$i]);
			}
			$query .= ")";
			$result = $db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['tbl']." '".htmlencode($target_table)."' ".$lang['altered'].".";
			$params->redirect(array('action'=>'column_view'), $completed);
			break;

		//- Edit column (=column_edit)
		case "column_edit":
			$query = "ALTER TABLE ".$db->quote_id($target_table).' CHANGE '.$db->quote_id($_POST['oldvalue'])." ".$db->quote($_POST['0_field'])." ".$_POST['0_type'];
			$result = $db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['tbl']." '".htmlencode($target_table)."' ".$lang['altered'].".";
			$params->redirect(array('action'=>'column_view'), $completed);
			break;

		//- Delete trigger (=trigger_delete)
		case "trigger_delete":
			$query = "DROP TRIGGER ".$db->quote_id($_GET['pk']);
			$result = $db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['trigger']." '".htmlencode($_GET['pk'])."' ".$lang['deleted'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
			$params->redirect(array('action'=>'column_view'), $completed);
			break;

		//- Delete index (=index_delete)
		case "index_delete":
			$query = "DROP INDEX ".$db->quote_id($_GET['pk']);
			$result = $db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['index']." '".htmlencode($_GET['pk'])."' ".$lang['deleted'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
			$params->redirect(array('action'=>'column_view'), $completed);
			break;

		//- Create trigger (=trigger_create)
		case "trigger_create":
			$str = "CREATE TRIGGER ".$db->quote($_POST['trigger_name']);
			if($_POST['beforeafter']!="")
				$str .= " ".$_POST['beforeafter'];
			$str .= " ".$_POST['event']." ON ".$db->quote_id($target_table);
			if(isset($_POST['foreachrow']))
				$str .= " FOR EACH ROW";
			if($_POST['whenexpression']!="")
				$str .= " WHEN ".$_POST['whenexpression'];
			$str .= " BEGIN";
			$str .= " ".$_POST['triggersteps'];
			$str .= " END";
			$query = $str;
			$result = $db->query($query);
			if($result === false)
				$completed = $db->getError(true);
			else
				$completed = $lang['trigger']." ".$lang['created'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
			$params->redirect(array('action'=>'column_view'), $completed);
			break;

		//- Create index (=index_create)
		case "index_create":
			$num = $_POST['num'];
			if($_POST['name']=="")
			{
				$completed = $lang['blank_index'];
			}
			else if($_POST['0_field']=="")
			{
				$completed = $lang['one_index'];
			}
			else
			{
				$str = "CREATE ";
				if($_POST['duplicate']=="no")
					$str .= "UNIQUE ";
				$str .= "INDEX ".$db->quote($_POST['name'])." ON ".$db->quote_id($target_table)." (";
				$str .= $db->quote_id($_POST['0_field']).$_POST['0_order'];
				for($i=1; $i<$num; $i++)
				{
					if($_POST[$i.'_field']!="")
						$str .= ", ".$db->quote_id($_POST[$i.'_field']).$_POST[$i.'_order'];
				}
				$str .= ")";
				if(isset($_POST['where']) && $_POST['where']!='')
					$str.=" WHERE ".$_POST['where']; 
				$query = $str;
				$result = $db->query($query);
				if($result === false)
					$completed = $db->getError(true);
				else
					$completed = $lang['index']." ".$lang['created'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
			}
			$params->redirect(array('action'=>'column_view'), $completed);
			break;
	}
}

//- HTML: output starts here
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<!-- Copyright <?php echo date("Y").' '.PROJECT.' ('.PROJECT_URL.')'; ?> -->
<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
<link rel="shortcut icon" href="?resource=favicon" />
<title><?php echo PROJECT ?></title>

<?php
//- HTML: css/theme include
if(isset($_GET['theme'])) $theme = basename($_GET['theme']);

// allow themes to be dropped in subfolder "themes"
if(is_file('themes/'.$theme)) $theme = 'themes/'.$theme;

if (file_exists($theme))
	// an external stylesheet exists - import it
	echo "<link href='{$theme}' rel='stylesheet' type='text/css' />", PHP_EOL;
else
	// only use the default stylesheet if an external one does not exist
	echo "<link href='?resource=css' rel='stylesheet' type='text/css' />", PHP_EOL;

// HTML: output help text, then exit
if(isset($_GET['help']))
{
	//help section array
	$help = array
	(
		$lang['help1'] => sprintf($lang['help1_x'], PROJECT, PROJECT, PROJECT), $lang['help2'] => $lang['help2_x'], $lang['help3'] => $lang['help3_x'], 
		$lang['help4'] => $lang['help4_x'], $lang['help5'] => $lang['help5_x'], $lang['help6'] => $lang['help6_x'],
		$lang['help7'] => $lang['help7_x'], $lang['help8'] => $lang['help8_x'], $lang['help9'] => $lang['help9_x'], $lang['help10'] => $lang['help10_x']
	);
	?>
	</head>
	<body style="direction:<?php echo $lang['direction']; ?>;">
	<div id='help_container'>
	<?php
	echo "<div class='help_list'>";
	echo "<span style='font-size:18px;'>".PROJECT." v".VERSION." ".$lang['help_doc']."</span><br/><br/>";
	foreach((array)$help as $key => $val)
	{
		echo "<a href='#".$key."'>".$key."</a><br/>";
	}
	echo "</div>";
	echo "<br/><br/>";
	foreach((array)$help as $key => $val)
	{
		echo "<div class='help_outer'>";
		echo "<a class='headd' name='".$key."'>".$key."</a>";
		echo "<div class='help_inner'>";
		echo $val;
		echo "</div>";
		echo "<a class='help_top' href='#top'>".$lang['back_top']."</a>"; 
		echo "</div>";
	}
	?>
	</div>
	</body>
	</html>
	<?php
	exit();		
}

//- Javascript include
?>
<!-- JavaScript Support -->
<script type='text/javascript' src='?resource=javascript'></script>
</head>
<body style="direction:<?php echo $lang['direction']; ?>;">
<?php
if(ini_get("register_globals") == "on" || ini_get("register_globals")=="1") //check whether register_globals is turned on - if it is, we need to not continue
{
	echo "<div class='confirm' style='margin:20px;'>".$lang['bad_php_directive']."</div>";
	echo "</body></html>";
	exit();
}

//- HTML: login screen if not authorized, exit
if(!$auth->isAuthorized())
{
	echo "<div id='loginBox'>";
	echo "<h1><span id='logo'>".PROJECT."</span> <span id='version'>v".VERSION."</span></h1>";
	echo "<div style='padding:15px; text-align:center;'>";
	if ($auth->isFailedLogin())
		echo "<span class='warning'>".$lang['passwd_incorrect']."</span><br/><br/>";
	echo $params->getForm();
	echo $lang['passwd'].": <input type='password' name='password'/><br/>";
	echo "<label><input type='checkbox' name='remember' value='yes' checked='checked'/> ".$lang['remember']."</label><br/><br/>";
	echo "<input type='submit' value='".$lang['login']."' class='btn'/>";
	echo "<input type='hidden' name='login' value='true' />";
	echo "</form>";
	echo "</div>";
	echo "</div>";
	echo "<br/>";
	echo "<div style='text-align:center;'>";
	echo "<span style='font-size:11px;'>".$lang['powered']." <a href='".PROJECT_URL."' target='_blank' style='font-size:11px;'>".PROJECT."</a> | "; 
	printf($lang['page_gen'], $pageTimer);
	echo "</span></div>";
	echo "</body></html>";
	exit();
}

//- User is authorized, display the main application

if(count($databases)==0) // the database array is empty, offer to create a new database
{
	//- HTML: form to create a new database, exit
	if($directory!==false && is_writable($directory))
	{
		echo "<div class='confirm' style='margin:20px;'>";
		printf($lang['no_db'], PROJECT, PROJECT);
		echo "</div>";
		//if the user has performed some action, show the resulting message
		if(isset($_GET['message']) && isset($_SESSION[COOKIENAME.'messages'][$_GET['message']]))
		{
			echo "<div class='confirm' style='margin:10px 20px;'>";
			echo $_SESSION[COOKIENAME.'messages'][$_GET['message']];
			echo "</div><br />";
			unset($_SESSION[COOKIENAME.'messages'][$_GET['message']]);
		}
		echo "<fieldset style='margin:15px;'><legend><b>".$lang['db_create']."</b></legend>";
		echo $params->getForm(array('table'=>null), 'post', false, 'create_database');
		echo "<input type='text' name='new_dbname' style='width:150px;'/> ";
		if(class_exists('SQLiteDatabase') && (class_exists('SQLite3') || class_exists('PDO')))
		{
			echo "<select name='new_dbtype' class='newDbType'>";
			echo "<option value='3'>SQLite 3</option>";
			echo "<option value='2'>SQLite 2</option>";
			echo "</select>";
		}
		echo "<input type='submit' value='".$lang['create']."' class='btn'/>";
		echo "</form>";
		echo "</fieldset>";
	}
	else
	{
		echo "<div class='confirm' style='margin:20px;'>";
		echo $lang['err'].": ".sprintf($lang['no_db2'], PROJECT);
		echo "</div><br/>";	
	}
	exit();
}

// initialize / change fulltexts parameter
if(isset($_GET['fulltexts']))
	$params->fulltexts = ($_GET['fulltexts'] ? 1 : 0);
else
	$params->fulltexts = 0;

//- HTML: sidebar
echo '<table class="body_tbl" width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td valign="top" class="left_td" style="width:100px; padding:9px 2px 9px 9px;">';
echo "<div id='leftNav'>";
echo "<h1><a href='".$params->getURL()."'>";
echo "<span id='logo'>".PROJECT."</span> <span id='version'>v".VERSION."</span>";
echo "</a></h1>";
echo "<div id='headerlinks'>";
echo "<a href='javascript:void' onclick='openHelp(\"top\");'>".$lang['docu']."</a> | ";
echo "<a href='https://www.gnu.org/licenses/gpl.html' target='_blank'>".$lang['license']."</a> | ";
echo "<a href='".PROJECT_URL."' target='_blank'>".$lang['proj_site']."</a>";
echo "</div>";

//- HTML: database list
$db->print_db_list();
echo "<fieldset style='margin:15px;'><legend>";
echo "<a href='".$params->getURL()."'";
if (!$target_table)
	echo " class='active_table'";
$name = $currentDB['name'];
if(strlen($name)>25)
	$name = "...".substr($name, strlen($name)-22, 22); 
echo ">".htmlencode($name)."</a>";
echo "</legend>";

//- HTML: table list
$query = "SELECT type, name FROM sqlite_master WHERE type='table' OR type='view' ORDER BY name";
$result = $db->selectArray($query);
$j=0;
for($i=0; $i<sizeof($result); $i++)
{
	if(substr($result[$i]['name'], 0, 7)!="sqlite_" && $result[$i]['name']!="")
	{
		echo "<span class='sidebar_table'>";
		echo $params->getLink(array('action'=>'column_view', 'table'=>$result[$i]['name']), "[".$lang[$result[$i]['type']=='table'?'tbl':'view']."]");
		echo "</span> ";
		echo $params->getLink(array('action'=>'row_view', 'table'=>$result[$i]['name']), htmlencode($result[$i]['name']), 
			($target_table == $result[$i]['name'] ? 'active_table' : '') );
		echo "<br/>";
		$j++;
	}
}
if($j==0)
	echo $lang['no_tbl'];
echo "</fieldset>";

//- HTML: form to create a new database
if($directory!==false && is_writable($directory))
{
	echo "<fieldset style='margin:15px;'><legend><b>".$lang['db_create']."</b> ".helpLink($lang['help2'])."</legend>"; 
	echo $params->getForm(array('table'=>null), 'post', false, 'create_database');
	echo "<input type='text' name='new_dbname' style='width:150px;'/>";
	if(class_exists('SQLiteDatabase') && (class_exists('SQLite3') || class_exists('PDO')))
	{
		echo "<select name='new_dbtype' class='newDbType'>";
		echo "<option value='3'>SQLite 3</option>";
		echo "<option value='2'>SQLite 2</option>";
		echo "</select>";
	}
	echo "<input type='submit' value='".$lang['create']."' class='btn'/>";
	echo "</form>";
	echo "</fieldset>";
}

echo "<div style='text-align:center;'>";
echo $params->getForm();
echo "<input type='submit' value='".$lang['logout']."' name='logout' class='btn'/>";
echo "</form>";
echo "</div>";
echo "</div>";
echo '</td><td valign="top" id="main_column" class="right_td" style="padding:9px 2px 9px 9px;">';

//- HTML: breadcrumb navigation
echo $params->getLink(array('table'=>null), htmlencode($currentDB['name']));
if ($target_table)
	echo " &rarr; ".$params->getLink(array('action'=>'row_view'), htmlencode($target_table));
echo "<br/><br/>";

//- Show the various tab views for a table
if($target_table)
{
	//- HTML: tabs
	echo $params->getLink(array('action'=>'row_view'), $lang['browse'], 
		(in_array($_GET['action'], array('row_view', 'row_editordelete') ) ? 'tab_pressed' : 'tab'));
	
	echo $params->getLink(array('action'=>'column_view'), $lang['struct'], 
		(in_array($_GET['action'], array('column_view', 'column_edit', 'column_confirm', 'primarykey_add', 'column_create', 'index_create', 'index_delete', 'trigger_create', 'trigger_delete') ) ? 'tab_pressed' : 'tab'));
	
	echo $params->getLink(array('action'=>'table_sql'), $lang['sql'], 
		($_GET['action']=="table_sql" ? 'tab_pressed' : 'tab'));

	echo $params->getLink(array('action'=>'table_search'), $lang['srch'], 
		($_GET['action']=="table_search" ? 'tab_pressed' : 'tab'));

	if($target_table_type == 'table')
		echo $params->getLink(array('action'=>'row_create'), $lang['insert'], 
			($_GET['action']=="row_create" ? 'tab_pressed' : 'tab'));

	echo $params->getLink(array('action'=>'table_export'), $lang['export'], 
		($_GET['action']=="table_export" ? 'tab_pressed' : 'tab'));

	if($target_table_type == 'table')
		echo $params->getLink(array('action'=>'table_import'), $lang['import'], 
			($_GET['action']=="table_import" ? 'tab_pressed' : 'tab'));

	echo $params->getLink(array('action'=>'table_rename'), $lang['rename'], 
		($_GET['action']=="table_rename" ? 'tab_pressed' : 'tab'));

	if($target_table_type == 'table')
		echo $params->getLink(array('action'=>'table_empty'), $lang['empty'], 
			($_GET['action']=="table_empty" ? 'tab_pressed empty' : 'tab empty'));

	echo $params->getLink(array('action'=>'table_drop'), $lang['drop'], 
			($_GET['action']=="table_drop" ? 'tab_pressed drop' : 'tab drop'));
}
else
//- Show the various tab views for a database
{
	$view = isset($_GET['view']) ? $_GET['view'] : 'structure';

	echo $params->getLink(array('view'=>'structure'), $lang['struct'], ($view=="structure" ? 'tab_pressed': 'tab')  );
	
	echo $params->getLink(array('view'=>'sql'), $lang['sql'], ($view=="sql" ? 'tab_pressed': 'tab')  );
	
	echo $params->getLink(array('view'=>'export'), $lang['export'], ($view=="export" ? 'tab_pressed': 'tab')  );
	
	echo $params->getLink(array('view'=>'import'), $lang['import'], ($view=="import" ? 'tab_pressed': 'tab')  );
	
	echo $params->getLink(array('view'=>'vacuum'), $lang['vac'], ($view=="vacuum" ? 'tab_pressed': 'tab')  );

	if($directory!==false && is_writable($directory))
	{

		echo $params->getLink(array('view'=>'rename'), $lang['db_rename'], ($view=="rename" ? 'tab_pressed': 'tab')  );

		echo $params->getLink(array('view'=>'delete'), "<span>".$lang['db_del']."</span>", ($view=="delete" ? 'tab_pressed delete_db': 'tab delete_db')  );
	}
}	

echo "<div style='clear:both;'></div>";
echo "<div id='main'>";

//- HTML: confirmation panel
//if the user has performed some action, show the resulting message
if(isset($_GET['message']) && isset($_SESSION[COOKIENAME.'messages'][$_GET['message']]))
{
	echo "<div class='confirm'>";
	echo $_SESSION[COOKIENAME.'messages'][$_GET['message']];
	echo "</div><br />";
	unset($_SESSION[COOKIENAME.'messages'][$_GET['message']]);
}


//- Switch on $_GET['action'] for operations with output
if(isset($_GET['action']) && !isset($_GET['confirm']))
{
	switch($_GET['action'])
	{
	//- Table actions

		//- Create table (=table_create)
		case "table_create":
			$query = "SELECT name FROM sqlite_master WHERE type='table' AND name=".$db->quote($_GET['tablename']);
			$results = $db->selectArray($query);
			if(sizeof($results)>0)
				$exists = true;
			else
				$exists = false;
			echo "<h2>".$lang['create_tbl'].": '".htmlencode($_GET['tablename'])."'</h2>";
			if($_GET['tablefields']=="" || intval($_GET['tablefields'])<=0)
				echo $lang['specify_fields'];
			else if($_GET['tablename']=="")
				echo $lang['specify_tbl'];
			else if($exists)
				echo $lang['tbl_exists'];
			else
			{
				$num = intval($_GET['tablefields']);
				$name = $_GET['tablename'];
				echo $params->getForm(array('action'=>'table_create', 'confirm'=>'1'));
				echo "<input type='hidden' name='tablename' value='".htmlencode($name)."'/>";
				echo "<input type='hidden' name='rows' value='".$num."'/>";
				echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
				echo "<tr>";
				$headings = array($lang['fld'], $lang['type'], $lang['prim_key']);
				if($db->getType() != "SQLiteDatabase") $headings[] = $lang['autoincrement'];
				$headings[] = $lang['not_null'];
				$headings[] = $lang['def_val'];
				for($k=0; $k<count($headings); $k++)
					echo "<td class='tdheader'>" . $headings[$k] . "</td>";
				echo "</tr>";

				for($i=0; $i<$num; $i++)
				{
					$tdWithClass = "<td class='td" . ($i%2 ? "1" : "2") . "'>";
					echo "<tr>";
					echo $tdWithClass;
					echo "<input type='text' name='".$i."_field' style='width:200px;'/>";
					echo "</td>";
					echo $tdWithClass;
					echo "<select name='".$i."_type' id='i".$i."_type' onchange='toggleAutoincrement(".$i.");'>";
					foreach ($sqlite_datatypes as $t) {
						echo "<option value='".htmlencode($t)."'>".htmlencode($t)."</option>";
					}
					echo "</select>";
					echo "</td>";
					echo $tdWithClass;
					echo "<label><input type='checkbox' name='".$i."_primarykey' id='i".$i."_primarykey' onclick='toggleNull(".$i."); toggleAutoincrement(".$i.");'/> ".$lang['yes']."</label>";
					echo "</td>";
					if($db->getType() != "SQLiteDatabase")
					{
						echo $tdWithClass;
						echo "<label><input type='checkbox' name='".$i."_autoincrement' id='i".$i."_autoincrement'/> ".$lang['yes']."</label>";
						echo "</td>";
					}
					echo $tdWithClass;
					echo "<label><input type='checkbox' name='".$i."_notnull' id='i".$i."_notnull'/> ".$lang['yes']."</label>";
					echo "</td>";
					echo $tdWithClass;
					echo "<select name='".$i."_defaultoption' id='i".$i."_defaultoption' onchange=\"if(this.value!='defined' && this.value!='expr') document.getElementById('i".$i."_defaultvalue').value='';\">";
					echo "<option value='none'>".$lang['none']."</option><option value='defined'>".$lang['as_defined'].":</option><option>NULL</option><option>CURRENT_TIME</option><option>CURRENT_DATE</option><option>CURRENT_TIMESTAMP</option><option value='expr'>".$lang['expression'].":</option>";
					echo "</select>";
					echo "<input type='text' name='".$i."_defaultvalue' id='i".$i."_defaultvalue' style='width:100px;' onchange=\"if(document.getElementById('i".$i."_defaultoption').value!='expr') document.getElementById('i".$i."_defaultoption').value='defined';\"/>";
					echo "</td>";
					echo "</tr>";
				}
				echo "<tr>";
				echo "<td class='tdheader' style='text-align:right;' colspan='6'>";
				echo "<input type='submit' value='".$lang['create']."' class='btn'/> ";
				echo $params->getLink(array(), $lang['cancel']);
				echo "</td>";
				echo "</tr>";
				echo "</table>";
				echo "</form>";
				if($db->getType() != "SQLiteDatabase") echo "<script type='text/javascript'>window.onload=initAutoincrement;</script>";
			}
			break;

		//- Perform SQL query on table (=table_sql)
		case "table_sql":
			if(isset($_POST['query']) && $_POST['query']!="")
			{
				$delimiter = $_POST['delimiter'];
				$queryStr = $_POST['queryval'];
				//save the queries in history if necessary
				if($maxSavedQueries!=0 && $maxSavedQueries!=false)
				{
					if(!isset($_SESSION[COOKIENAME.'query_history']))
						$_SESSION[COOKIENAME.'query_history'] = array();
					$_SESSION[COOKIENAME.'query_history'][md5(strtolower($queryStr))] = $queryStr;
					if(sizeof($_SESSION[COOKIENAME.'query_history']) > $maxSavedQueries)
						array_shift($_SESSION[COOKIENAME.'query_history']);
				}
				$query = explode_sql($delimiter, $queryStr); //explode the query string into individual queries based on the delimiter

				for($i=0; $i<sizeof($query); $i++) //iterate through the queries exploded by the delimiter
				{
					if(str_replace(" ", "", str_replace("\n", "", str_replace("\r", "", $query[$i])))!="") //make sure this query is not an empty string
					{
						$queryTimer = new MicroTimer();
						$table_result = $db->query($query[$i]);

						echo "<div class='confirm'>";
						echo "<b>".htmlencode($query[$i])."</b>";
						if($table_result === NULL || $table_result === false)
						{
							echo "<br /><b>".$lang['err'].": ".htmlencode($db->getError())."</b></div>";
						}
						echo "</div><br/>";
						if($row = $db->fetch($table_result, 'assoc'))
						{
							$headers = array_keys($row);
							echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
							echo "<tr>";
							for($j=0; $j<sizeof($headers); $j++)
							{
								echo "<td class='tdheader'>";
								echo htmlencode($headers[$j]);
								echo "</td>";
							}
							echo "</tr>";
							$rowCount = 0;
							for(; $rowCount==0 || $row = $db->fetch($table_result, 'assoc'); $rowCount++)
							{
								$tdWithClass = "<td class='td".($rowCount%2 ? "1" : "2")."'>";
								echo "<tr>";
								for($z=0; $z<sizeof($headers); $z++)
								{
									echo $tdWithClass;
									if($row[$headers[$z]]==="")
										echo "&nbsp;";
									elseif($row[$headers[$z]]===NULL)
										echo "<i class='null'>NULL</i>";
									else
										echo htmlencode(subString($row[$headers[$z]]));
									echo "</td>";
								}
								echo "</tr>";
							}
							$queryTimer->stop();
							echo "</table><br/><br/>";
							
						
							if($table_result !== NULL && $table_result !== false)
							{
								echo "<div class='confirm' style='margin-bottom: 2em'>";
								if($rowCount>0 || $db->getAffectedRows()==0)
								{
									printf($lang['show_rows'], $rowCount);
								}
								if($db->getAffectedRows()>0 || $rowCount==0)
								{
									echo $db->getAffectedRows()." ".$lang['rows_aff']." ";
								}
								printf($lang['query_time'], $queryTimer);
								echo "</div>";
							}

							
						}
					}
				}
			}
			else
			{
				$delimiter = ";";
				$queryStr = "SELECT * FROM ".$db->quote_id($target_table)." WHERE 1";
			}

			echo "<fieldset>";
			echo "<legend><b>".sprintf($lang['run_sql'],htmlencode($db->getName()))."</b></legend>";
			echo $params->getForm(array('action'=>'table_sql'));
			if(isset($_SESSION[COOKIENAME.'query_history']) && sizeof($_SESSION[COOKIENAME.'query_history'])>0)
			{
				echo "<b>".$lang['recent_queries']."</b><ul>";
				foreach($_SESSION[COOKIENAME.'query_history'] as $key => $value)
					echo "<li><a onclick='document.getElementById(\"queryval\").value = this.textContent; return false;' href='#'>".htmlencode($value)."</a></li>";
				echo "</ul><br/><br/>";
			}
			echo "<div style='float:left; width:70%;'>";
			echo "<textarea style='width:97%; height:300px;' name='queryval' id='queryval' cols='50' rows='8'>".htmlencode($queryStr)."</textarea>";
			echo "</div>";
			echo "<div style='float:left; width:28%; padding-left:10px;'>";
			echo $lang['fields']."<br/>";
			echo "<select multiple='multiple' style='width:100%;' id='fieldcontainer'>";
			$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
			$result = $db->selectArray($query);
			for($i=0; $i<sizeof($result); $i++)
			{
				echo "<option value='".htmlencode($result[$i][1])."'>".htmlencode($result[$i][1])."</option>";
			}
			echo "</select>";
			echo "<input type='button' value='&lt;&lt;' onclick='moveFields();' class='btn'/>";
			echo "</div>";
			echo "<div style='clear:both;'></div>";
			echo $lang['delimit']." <input type='text' name='delimiter' value='".htmlencode($delimiter)."' style='width:50px;'/> ";
			echo "<input type='submit' name='query' value='".$lang['go']."' class='btn'/>";
			echo "</form>";
			echo "</fieldset>";
			break;

		//- Empty table (=table_empty)
		case "table_empty":
			echo $params->getForm(array('action'=>'table_empty','confirm'=>'1'));
			echo "<div class='confirm'>";
			echo sprintf($lang['ques_empty'], htmlencode($target_table))."<br/><br/>";
			echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
			echo $params->getLink(array('table'=>null), $lang['cancel']);
			echo "</div>";
			break;

		//- Drop table (=table_drop)
		case "table_drop":
			echo $params->getForm(array('action'=>'table_drop','confirm'=>'1'));
			echo "<div class='confirm'>";
			echo sprintf($lang['ques_drop'], htmlencode($target_table))."<br/><br/>";
			echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
			echo $params->getLink(array('table'=>null), $lang['cancel']);
			echo "</div>";
			break;

		//- Drop view (=view_drop)
		case "view_drop":
			echo $params->getForm(array('action'=>'view_drop','confirm'=>'1'));
			echo "<input type='hidden' name='viewname' value='".htmlencode($target_table)."'/>";
			echo "<div class='confirm'>";
			echo sprintf($lang['ques_drop_view'], htmlencode($target_table))."<br/><br/>";
			echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
			echo $params->getLink(array('table'=>null), $lang['cancel']);
			echo "</div>";
			break;

		//- Export table (=table_export)
		case "table_export":
			echo $params->getForm();
			echo "<fieldset style='float:left; width:260px; margin-right:20px;'><legend><b>".$lang['export']."</b></legend>";
			echo "<input type='hidden' value='".htmlencode($target_table)."' name='single_table'/>";
			echo "<label><input type='radio' name='export_type' checked='checked' value='sql' onclick='toggleExports(\"sql\");'/> ".$lang['sql']."</label>";
			echo "<br/><label><input type='radio' name='export_type' value='csv' onclick='toggleExports(\"csv\");'/> ".$lang['csv']."</label>";
			echo "</fieldset>";
			
			echo "<fieldset style='float:left; max-width:350px;' id='exportoptions_sql'><legend><b>".$lang['options']."</b></legend>";
			echo "<label><input type='checkbox' checked='checked' name='structure'/> ".$lang['export_struct']."</label> ".helpLink($lang['help5'])."<br/>";
			echo "<label><input type='checkbox' checked='checked' name='data'/> ".$lang['export_data']."</label> ".helpLink($lang['help6'])."<br/>"; 
			echo "<label><input type='checkbox' name='drop'/> ".$lang['add_drop']."</label> ".helpLink($lang['help7'])."<br/>"; 
			echo "<label><input type='checkbox' checked='checked' name='transaction'/> ".$lang['add_transact']."</label> ".helpLink($lang['help8'])."<br/>";
			echo "<label><input type='checkbox' checked='checked' name='comments'/> ".$lang['comments']."</label> ".helpLink($lang['help9'])."<br/>"; 
			echo "</fieldset>";
			
			echo "<fieldset style='float:left; max-width:350px; display:none;' id='exportoptions_csv'><legend><b>".$lang['options']."</b></legend>";
			echo "<div style='float:left;'>".$lang['fld_terminated']."</div>";
			echo "<input type='text' value=';' name='export_csv_fieldsterminated' style='float:right;'/>";
			echo "<div style='clear:both;'></div>";
			echo "<div style='float:left;'>".$lang['fld_enclosed']."</div>";
			echo "<input type='text' value='\"' name='export_csv_fieldsenclosed' style='float:right;'/>";
			echo "<div style='clear:both;'></div>";
			echo "<div style='float:left;'>".$lang['fld_escaped']."</div>";
			echo "<input type='text' value='\' name='export_csv_fieldsescaped' style='float:right;'/>";
			echo "<div style='clear:both;'></div>";
			echo "<div style='float:left;'>".$lang['rep_null']."</div>";
			echo "<input type='text' value='NULL' name='export_csv_replacenull' style='float:right;'/>";
			echo "<div style='clear:both;'></div>";
			echo "<label><input type='checkbox' name='export_csv_crlf'/> ".$lang['rem_crlf']."</label><br/>";
			echo "<label><input type='checkbox' checked='checked' name='export_csv_fieldnames'/> ".$lang['put_fld']."</label>";
			echo "</fieldset>";
			
			echo "<div style='clear:both;'></div>";
			echo "<br/><br/>";
			echo "<fieldset><legend><b>".$lang['save_as']."</b></legend>";
			$file = pathinfo($db->getPath());
			$name = $file['filename'];
			echo "<input type='text' name='filename' value='".htmlencode($name)."_".htmlencode($target_table)."_".date("Y-m-d").".dump' style='width:400px;'/> <input type='submit' name='export' value='".$lang['export']."' class='btn'/>";
			echo "</fieldset>";
			echo "</form>";
			echo "<div class='confirm' style='margin-top: 2em'>".sprintf($lang['backup_hint'],
				$params->getLink(array('download' => $currentDB['path'], 'token' => $_SESSION[COOKIENAME.'token']), $lang["backup_hint_linktext"], '', $lang['backup']))."</div>";
			break;

		//- Import table (=table_import)
		case "table_import":
			if(isset($_POST['import']))
			{
				echo "<div class='confirm'>";
				if($importSuccess===true)
					echo $lang['import_suc'];
				else
					echo $lang['err'].': '.htmlencode($importSuccess);
				echo "</div><br/>";
			}
			echo $params->getForm(array('action' => 'table_import'), 'post', true);
			echo "<fieldset style='float:left; width:260px; margin-right:20px;'><legend><b>".$lang['import_into']." ".htmlencode($target_table)."</b></legend>";
			echo "<label><input type='radio' name='import_type' checked='checked' value='sql' onclick='toggleImports(\"sql\");'/> ".$lang['sql']."</label>";
			echo "<br/><label><input type='radio' name='import_type' value='csv' onclick='toggleImports(\"csv\");'/> ".$lang['csv']."</label>";
			echo "</fieldset>";
			
			echo "<fieldset style='float:left; max-width:350px;' id='importoptions_sql'><legend><b>".$lang['options']."</b></legend>";
			echo $lang['no_opt'];
			echo "</fieldset>";
			
			echo "<fieldset style='float:left; max-width:350px; display:none;' id='importoptions_csv'><legend><b>".$lang['options']."</b></legend>";
			echo "<input type='hidden' value='".htmlencode($target_table)."' name='single_table'/>";
			echo "<div style='float:left;'>".$lang['fld_terminated']."</div>";
			echo "<input type='text' value=';' name='import_csv_fieldsterminated' style='float:right;'/>";
			echo "<div style='clear:both;'>";
			echo "<div style='float:left;'>".$lang['fld_enclosed']."</div>";
			echo "<input type='text' value='\"' name='import_csv_fieldsenclosed' style='float:right;'/>";
			echo "<div style='clear:both;'>";
			echo "<div style='float:left;'>".$lang['fld_escaped']."</div>";
			echo "<input type='text' value='\' name='import_csv_fieldsescaped' style='float:right;'/>";
			echo "<div style='clear:both;'>";
			echo "<div style='float:left;'>".$lang['rep_null']."</div>";
			echo "<input type='text' value='NULL' name='import_csv_replacenull' style='float:right;'/>";
			echo "<div style='clear:both;'>";
			echo "<label><input type='checkbox' checked='checked' name='import_csv_fieldnames'/> ".$lang['fld_names']."</label>";
			echo "</fieldset>";
			
			echo "<div style='clear:both;'></div>";
			echo "<br/><br/>";
			
			echo "<fieldset><legend><b>".$lang['import_f']."</b></legend>";
			echo "<input type='file' value='".$lang['choose_f']."' name='file' style='background-color:transparent; border-style:none;'/> <input type='submit' value='".$lang['import']."' name='import' class='btn'/>";
			echo "</fieldset>";
			break;

		//- Rename table (=table_rename)
		case "table_rename":
			echo $params->getForm(array('action'=>'table_rename', 'confirm'=>'1'));
			printf($lang['rename_tbl'], htmlencode($target_table));
			echo " <input type='text' name='newname' value='".htmlencode($target_table)."' style='width:200px;'/> <input type='submit' value='".$lang['rename']."' name='rename' class='btn'/>";
			echo "</form>";
			break;

		//- Search table (=table_search)
		case "table_search":
			$searchValues = array();
			$searchOperators = array();
			if(isset($_GET['done']))
			{
				$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
				$result = $db->selectArray($query);
				$primary_key = $db->getPrimaryKey($target_table);
				$j = 0;
				$arr = array();
				for($i=0; $i<sizeof($result); $i++)
				{
					$field = $result[$i][1];
					$field_index = str_replace(" ","_",$field);
					$operator = $_POST[$field_index.":operator"];
					$searchOperators[$field] = $operator;
					$value = $_POST[$field_index];
					if($value!="" || $operator=="!= ''" || $operator=="= ''" || $operator == 'IS NULL' || $operator == 'IS NOT NULL')
					{
						if($operator=="= ''" || $operator=="!= ''" || $operator == 'IS NULL' || $operator == 'IS NOT NULL')
							$arr[$j] = $db->quote_id($field)." ".$operator;
						else{
							if($operator == "LIKE%"){ 
								$operator = "LIKE";
								if(!preg_match('/(^%)|(%$)/', $value)) $value = '%'.$value.'%';
								$searchValues[$field] = array($value);
								$value_quoted = $db->quote($value);
							}
							elseif($operator == 'IN' || $operator == 'NOT IN')
							{
								$value = trim($value, '() ');
								$values = explode(',',$value);
								$values = array_map('trim', $values, array_fill(0,count($values),' \'"'));
								if($operator == 'IN')
									$searchValues[$field] = $values;
								$values = array_map(array($db, 'quote'), $values);
								$value_quoted = '(' .implode(', ', $values) . ')';
							}
							else
							{
								$searchValues[$field] = array($value);
								$value_quoted = $db->quote($value);
							}
							$arr[$j] = $db->quote_id($field)." ".$operator." ".$value_quoted;
						}
						$j++;
					}
				}
				$query = "SELECT *";
				// select the primary key column(s) last (ROWID if there is no PK).
				// this will be used to identify rows, e.g. when editing/deleting rows
				$primary_key = $db->getPrimaryKey($target_table);
				foreach($primary_key as $pk)
				{
					$query.= ', '.$db->quote_id($pk);
					$query.= ', typeof('.$db->quote_id($pk).')';
				}
				$query .= " FROM ".$db->quote_id($target_table);
				$whereTo = '';
				if(sizeof($arr)>0)
				{
					$whereTo .= " WHERE ".$arr[0];
					for($i=1; $i<sizeof($arr); $i++)
					{
						$whereTo .= " AND ".$arr[$i];
					}
				}
				$query .= $whereTo;
				$query_disp = "SELECT * FROM " . $db->quote_id($target_table) . $whereTo;
				$queryTimer = new MicroTimer();
				$arr = $db->selectArray($query);
				$queryTimer->stop();

				echo "<div class='confirm'>";
				echo "<b>";
				if($arr!==false)
				{
					$affected = sizeof($arr);
					echo $lang['showing']." ".$affected." ".$lang['rows'].". ";
					printf($lang['query_time'], $queryTimer);
					echo "</b><br/>";
				}
				else
				{
					echo $lang['err'].": ".htmlencode($db->getError()).".</b><br/>".$lang['bug_report'].' '.PROJECT_BUGTRACKER_LINK.'<br/>';
				}
				echo "<span style='font-size:11px;'>".htmlencode($query_disp)."</span>";
				echo "</div><br/>";

				if(sizeof($arr)>0)
				{
					if($target_table_type == 'view')
					{
						echo sprintf($lang['readonly_tbl'], htmlencode($target_table))." <a href='https://en.wikipedia.org/wiki/View_(SQL)' target='_blank'>https://en.wikipedia.org/wiki/View_(SQL)</a>"; 
						echo "<br/><br/>";	
					}

					echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
					echo "<tr>";
					if($target_table_type == 'table')
					{
						echo "<td colspan='2' class='tdheader' style='text-align:center'>";
						#todo: make sure the search keywords are kept
						#echo "<a href='?action=table_search&amp;done=1&amp;table=".$target_table."&amp;fulltexts=".($_SESSION[COOKIENAME.'fulltexts']?0:1)."' title='".$lang[($_SESSION[COOKIENAME.'fulltexts']?'no_full_texts':'full_texts')]."'>";
						#echo "<b>&".($_SESSION[COOKIENAME.'fulltexts']?'r':'l')."arr;</b> T <b>&".($_SESSION[COOKIENAME.'fulltexts']?'l':'r')."arr;</b></a>";
						echo "</td>";
					}
					
					$header = array();
					for($j=0; $j<sizeof($result); $j++)
					{
						$headers[$j]=$result[$j]['name'];
						echo "<td class='tdheader'>";
						echo htmlencode($headers[$j]);
						echo "</td>";
					}
					echo "</tr>";
					
					$pkFirstCol = sizeof($result)+1;
					for($j=0; $j<sizeof($arr); $j++)
					{
						// -g-> $pk will always be the last columns in each row of the array because we are doing "SELECT *, PK_1, typeof(PK_1), PK2, typeof(PK_2), ... FROM ..."
						$pk_arr = array();
						for($col = $pkFirstCol; array_key_exists($col, $arr[$j]); $col=$col+2)
						{
							// in $col we have the type and in $col-1 the value
							if($arr[$j][$col]=='integer' || $arr[$j][$col]=='real')
								// json encode as int or float, not string
								$pk_arr[] = $arr[$j][$col-1]+0;
							else
								// encode as json string
								$pk_arr[] = $arr[$j][$col-1]; 
						}
						$pk = json_encode($pk_arr);
						$tdWithClass = "<td class='td".($j%2 ? "1" : "2")."'>";
						echo "<tr>";
						if($target_table_type == 'table')
						{
							echo $tdWithClass . $params->getLink(array('action'=>'row_editordelete', 'pk'=>$pk, 'type'=>'edit'), "<span>".$lang['edit']."</span>", 'edit', $lang['edit'])."</td>"; 
							echo $tdWithClass . $params->getLink(array('action'=>'row_editordelete', 'pk'=>$pk, 'type'=>'delete'), "<span>".$lang['del']."</span>", 'delete', $lang['del'])."</td>"; 
						}
						for($z=0; $z<sizeof($result); $z++)
						{
							echo $tdWithClass;
							$fldResult = $arr[$j][$headers[$z]];
							if(isset($searchValues[$headers[$z]]) && is_array($searchValues[$headers[$z]]))
							{
								// build one regex that matches (all) search words
								$regex = '/';
								$vali=0;
								foreach($searchValues[$headers[$z]] as $searchValue)
								{
									if($searchOperators[$headers[$z]] =='LIKE' || $searchOperators[$headers[$z]] == 'LIKE%')
										$regex .= '(?:'.($searchValue[0]=='%'?'':'^'); // does the searchvalue have to occur at the start?
									$regex .= preg_quote(trim($searchValue,'%'),'/');  // the search value
									if($searchOperators[$headers[$z]] =='LIKE' || $searchOperators[$headers[$z]] == 'LIKE%')
										$regex .= (substr($searchValue,-1)=='%'?'':'$').')';  // does the searchvalue have to occur at the end?
									if($vali++<count($searchValues[$headers[$z]]))
										$regex .= '|';    // there is another search value, so we add a | 
								}
								$regex .= '/';
								// LIKE operator is not case sensitive, others are
								if($searchOperators[$headers[$z]] =='LIKE' || $searchOperators[$headers[$z]] == 'LIKE%')
									$regex.= 'i';
								
								// split the string into parts that match and should be highlighted and parts in between
								// $fldBetweenParts: the parts that don't match (might contain empty strings)
								$fldBetweenParts = preg_split($regex, $fldResult); 
								// $fldFoundParts[0]: the parts that match
								preg_match_all($regex, $fldResult, $fldFoundParts);
								
								// stick the parts together
								$fldResult = '';
								foreach($fldBetweenParts as $index => $betweenPart)
								{
									$fldResult .= htmlencode($betweenPart); // part that does not match (might be empty)
									if(isset($fldFoundParts[0][$index]))
										$fldResult .= '<u class="found">'.htmlencode($fldFoundParts[0][$index]).'</u>'; // the part that matched
								}
							}
							echo $fldResult;
							echo "</td>";
						}
						echo "</tr>";
					}
					echo "</table><br/><br/>";
				}
				
				echo $params->getLink(array('action'=>'table_search'), $lang['srch_again']);
			}
			else
			{
				$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
				$result = $db->selectArray($query);
				
				echo $params->getForm(array('action'=>'table_search', 'done'=>'1'));
					
				echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
				echo "<tr>";
				echo "<td class='tdheader'>".$lang['fld']."</td>";
				echo "<td class='tdheader'>".$lang['type']."</td>";
				echo "<td class='tdheader'>".$lang['operator']."</td>";
				echo "<td class='tdheader'>".$lang['val']."</td>";
				echo "</tr>";

				for($i=0; $i<sizeof($result); $i++)
				{
					$field = $result[$i][1];
					$type = $result[$i]['type'];
					$typeAffinity = get_type_affinity($type);
					$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
					$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
					echo "<tr>";
					echo $tdWithClassLeft;
					echo htmlencode($field);
					echo "</td>";
					echo $tdWithClassLeft;
					echo htmlencode($type);
					echo "</td>";
					echo $tdWithClassLeft;
					echo "<select name='".htmlencode($field).":operator' onchange='checkLike(\"".htmlencode($field)."_search\", this.options[this.selectedIndex].value); '>";
					echo "<option value='='>=</option>";
					if($typeAffinity=="INTEGER" || $typeAffinity=="REAL" || $typeAffinity=="NUMERIC")
					{
						echo "<option value='&gt;'>&gt;</option>";
						echo "<option value='&gt;='>&gt;=</option>";
						echo "<option value='&lt;'>&lt;</option>";
						echo "<option value='&lt;='>&lt;=</option>";
					}
					else if($typeAffinity=="TEXT" || $typeAffinity=="NONE")
					{
						echo "<option value='= '''>= ''</option>";
						echo "<option value='!= '''>!= ''</option>";
					}
					echo "<option value='!='>!=</option>";
					if($typeAffinity=="TEXT" || $typeAffinity=="NONE")
						echo "<option value='LIKE' selected='selected'>LIKE</option>";
					else
						echo "<option value='LIKE'>LIKE</option>";
					echo "<option value='LIKE%'>LIKE %...%</option>";
					echo "<option value='NOT LIKE'>NOT LIKE</option>";
					echo "<option value='IN'>IN (..., ...)</option>";
					echo "<option value='NOT IN'>NOT IN (..., ...)</option>";
					echo "<option value='IS NULL'>IS NULL</option>";
					echo "<option value='IS NOT NULL'>IS NOT NULL</option>";
					echo "</select>";
					echo "</td>";
					echo $tdWithClassLeft;
					if($typeAffinity=="INTEGER" || $typeAffinity=="REAL" || $typeAffinity=="NUMERIC")
						echo "<input type='text' id='".htmlencode($field)."_search' name='".htmlencode($field)."'/>";
					else
						echo "<textarea id='".htmlencode($field)."_search' name='".htmlencode($field)."' rows='1' cols='60'></textarea>";
					echo "</td>";
					echo "</tr>";
				}
				echo "<tr>";
				echo "<td class='tdheader' style='text-align:right;' colspan='4'>";
				echo "<input type='submit' value='".$lang['srch']."' class='btn'/>";
				echo "</td>";
				echo "</tr>";
				echo "</table>";
				echo "</form>";
			}
			break;

	//- Row actions

		//- View row (=row_view)
		case "row_view":
			if(!isset($_GET['startRow']))
				$_GET['startRow'] = 0;

			if(isset($_GET['numRows']))
				$_SESSION[COOKIENAME.'numRows'] = intval($_GET['numRows']);

			if(!isset($_SESSION[COOKIENAME.'numRows']))
				$_SESSION[COOKIENAME.'numRows'] = $rowsNum;
			
			if(isset($_SESSION[COOKIENAME.'currentTable']) && $_SESSION[COOKIENAME.'currentTable']!=$target_table)
			{
				unset($_SESSION[COOKIENAME.'sortRows']);
				unset($_SESSION[COOKIENAME.'orderRows']);	
			}
			if(isset($_GET['viewtype']))
			{
				$_SESSION[COOKIENAME.'viewtype'] = $_GET['viewtype'];	
			}
			
			$rowCount = $db->numRows($target_table);
			$lastPage = intval($rowCount / $_SESSION[COOKIENAME.'numRows']);
			$remainder = intval($rowCount % $_SESSION[COOKIENAME.'numRows']);
			if($remainder==0)
				$remainder = $_SESSION[COOKIENAME.'numRows'];
			
			//- HTML: pagination buttons
			echo "<div style=''>";
			//previous button
			if($_GET['startRow']>0)
			{
				echo "<div style='float:left;'>";
				echo $params->getForm(array('action'=>'row_view'),'get');
				echo "<input type='hidden' name='startRow' value='0'/>";
				echo "<input type='hidden' name='numRows' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
				echo "<input type='submit' value='&larr;&larr;' class='btn'/> ";
				echo "</form>";
				echo "</div>";
				echo "<div style='float:left; overflow:hidden; margin-right:20px;'>";
				echo $params->getForm(array('action'=>'row_view'),'get');
				echo "<input type='hidden' name='startRow' value='".max(0,intval($_GET['startRow']-$_SESSION[COOKIENAME.'numRows']))."'/>";
				echo "<input type='hidden' name='numRows' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
				echo "<input type='submit' value='&larr;' class='btn'/> ";
				echo "</form>";
				echo "</div>";
			}
			
			//show certain number buttons
			echo "<div style='float:left;'>";
			echo $params->getForm(array('action'=>'row_view'),'get');
			echo "<input type='submit' value='".$lang['show']." : ' name='show' class='btn'/> ";
			echo "<input type='text' name='numRows' style='width:50px;' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
			echo $lang['rows_records'];

			if(intval($_GET['startRow']+$_SESSION[COOKIENAME.'numRows']) < $rowCount)
				echo "<input type='text' name='startRow' style='width:90px;' value='".intval($_GET['startRow']+$_SESSION[COOKIENAME.'numRows'])."'/>";
			else
				echo "<input type='text' name='startRow' style='width:90px;' value='0'/> ";
			echo $lang['as_a'];
			echo " <select name='viewtype'>";
			if(!isset($_SESSION[COOKIENAME.'viewtype']) || $_SESSION[COOKIENAME.'viewtype']=="table")
			{
				echo "<option value='table' selected='selected'>".$lang['tbl']."</option>";
				echo "<option value='chart'>".$lang['chart']."</option>";
			}
			else
			{
				echo "<option value='table'>".$lang['tbl']."</option>";
				echo "<option value='chart' selected='selected'>".$lang['chart']."</option>";
			}
			echo "</select>";
			echo "</form>";
			echo "</div>";
			
			//next button
			if(intval($_GET['startRow']+$_SESSION[COOKIENAME.'numRows'])<$rowCount)
			{
				echo "<div style='float:left; margin-left:20px; '>";
				echo $params->getForm(array('action'=>'row_view'),'get');
				echo "<input type='hidden' name='startRow' value='".intval($_GET['startRow']+$_SESSION[COOKIENAME.'numRows'])."'/>";
				echo "<input type='hidden' name='numRows' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
				echo "<input type='submit' value='&rarr;' class='btn'/> ";
				echo "</form>";
				echo "</div>";
				echo "<div style='float:left; '>";
				echo $params->getForm(array('action'=>'row_view'),'get');
				echo "<input type='hidden' name='startRow' value='".intval($rowCount-$remainder)."'/>";
				echo "<input type='hidden' name='numRows' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
				echo "<input type='submit' value='&rarr;&rarr;' class='btn'/> ";
				echo "</form>";
				echo "</div>";
			}
			echo "<div style='clear:both;'></div>";
			echo "</div>";

			//- Query execution
			if(!isset($_GET['sort']))
				$_GET['sort'] = NULL;
			if(!isset($_GET['order']))
				$_GET['order'] = NULL;

			$numRows = $_SESSION[COOKIENAME.'numRows'];
			$startRow = $_GET['startRow'];
			if(isset($_GET['sort']))
			{
				$_SESSION[COOKIENAME.'sortRows'] = $_GET['sort'];
				$_SESSION[COOKIENAME.'currentTable'] = $target_table;
			}
			if(isset($_GET['order']))
			{
				$_SESSION[COOKIENAME.'orderRows'] = $_GET['order'];
				$_SESSION[COOKIENAME.'currentTable'] = $target_table;
			}
			$_SESSION[COOKIENAME.'numRows'] = $numRows;
			$query = "SELECT * ";
			// select the primary key column(s) last (ROWID if there is no PK).
			// this will be used to identify rows, e.g. when editing/deleting rows
			$primary_key = $db->getPrimaryKey($target_table);
			foreach($primary_key as $pk)
			{
				$query.= ', '.$db->quote_id($pk);
				$query.= ', typeof('.$db->quote_id($pk).')';
			}
			$query .= " FROM ".$db->quote_id($target_table);
			$queryDisp = "SELECT * FROM ".$db->quote_id($target_table);
			$queryCount = "SELECT MIN(COUNT(*),".$numRows.") AS count FROM ".$db->quote_id($target_table);
			$queryAdd = "";
			if(isset($_SESSION[COOKIENAME.'sortRows']))
				$queryAdd .= " ORDER BY ".$db->quote_id($_SESSION[COOKIENAME.'sortRows']);
			if(isset($_SESSION[COOKIENAME.'orderRows']))
				$queryAdd .= " ".$_SESSION[COOKIENAME.'orderRows'];
			$queryAdd .= " LIMIT ".$startRow.", ".$numRows;
			$query .= $queryAdd;
			$queryDisp .= $queryAdd;
			
			$resultRows = $db->select($queryCount);
			$resultRows = $resultRows['count'];

			//- Show results
			if($resultRows>0)
			{
				$queryTimer = new MicroTimer();
				$table_result = $db->query($query);
				$queryTimer->stop();


				echo "<br/><div class='confirm'>";
				echo "<b>".$lang['showing_rows']." ".$startRow." - ".($startRow + $resultRows-1).", ".$lang['total'].": ".$rowCount." ";
				printf($lang['query_time'], $queryTimer);
				echo "</b><br/>";
				echo "<span style='font-size:11px;'>".htmlencode($queryDisp)."</span>";
				echo "</div><br/>";
				
				if($target_table_type == 'view')
				{
					echo sprintf($lang['readonly_tbl'], htmlencode($target_table))." <a href='https://en.wikipedia.org/wiki/View_(SQL)' target='_blank'>https://en.wikipedia.org/wiki/View_(SQL)</a>"; 
					echo "<br/><br/>";	
				}
				
				$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
				$result = $db->selectArray($query);
				$pkFirstCol = sizeof($result)+1;
				//- Table view
				if(!isset($_SESSION[COOKIENAME.'viewtype']) || $_SESSION[COOKIENAME.'viewtype']=="table")
				{
					echo $params->getForm(array('action'=>'row_editordelete'), 'post', false, 'checkForm');
					echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
					echo "<tr>";
					if($target_table_type == 'table')
					{
						echo "<td colspan='3' class='tdheader' style='text-align:center'>";
						echo "<a href='".$params->getURL(array('action'=>'row_view', 'fulltexts'=>($params->fulltexts?0:1) ))."' title='".$lang[($params->fulltexts?'no_full_texts':'full_texts')]."'>";
						echo "<b>&".($params->fulltexts?'r':'l')."arr;</b> T <b>&".($params->fulltexts?'l':'r')."arr;</b></a>";
						echo "</td>";
					}

					for($i=0; $i<sizeof($result); $i++)
					{
						echo "<td class='tdheader'>";
						if(isset($_SESSION[COOKIENAME.'sortRows']))
							$orderTag = ($_SESSION[COOKIENAME.'sortRows']==$result[$i]['name'] && $_SESSION[COOKIENAME.'orderRows']=="ASC") ? "DESC" : "ASC";
						else
							$orderTag = "ASC";
						echo $params->getLink(array('action'=>'row_view', 'sort'=>$result[$i]['name'], 'order'=>$orderTag ), htmlencode($result[$i]['name']));
						if(isset($_SESSION[COOKIENAME.'sortRows']) && $_SESSION[COOKIENAME.'sortRows']==$result[$i]['name'])
							echo (($_SESSION[COOKIENAME.'orderRows']=="ASC") ? " <b>&uarr;</b>" : " <b>&darr;</b>");
						echo "</td>";
					}
					echo "</tr>";

					for($i=0; $row = $db->fetch($table_result); $i++)
					{
						// -g-> $pk will always be the last columns in each row of the array because we are doing "SELECT *, PK_1, typeof(PK_1), PK2, typeof(PK_2), ... FROM ..."
						$pk_arr = array();
						for($col = $pkFirstCol; array_key_exists($col, $row); $col=$col+2)
						{
							// in $col we have the type and in $col-1 the value
							if($row[$col]=='integer' || $row[$col]=='real')
								// json encode as int or float, not string
								$pk_arr[] = $row[$col-1]+0;
							else
								// encode as json string
								$pk_arr[] = $row[$col-1]; 
						}
						$pk = json_encode($pk_arr);
						$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
						$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
						echo "<tr>";
						if($target_table_type == 'table')
						{
							echo $tdWithClass;
							echo "<input type='checkbox' name='check[]' value='".htmlencode($pk)."' id='check_".htmlencode($i)."'/>";
							echo "</td>";
							echo $tdWithClass;
							// -g-> Here, we need to put the PK in as the link for both the edit and delete.
							echo $params->getLink(array('action'=>'row_editordelete', 'pk'=>$pk, 'type'=>'edit'),"<span>".$lang['edit']."</span>",'edit', $lang['edit']);
							echo "</td>";
							echo $tdWithClass;
							echo $params->getLink(array('action'=>'row_editordelete', 'pk'=>$pk, 'type'=>'delete'),"<span>".$lang['del']."</span>",'delete', $lang['del']);
							echo "</td>";
						}
						for($j=0; $j<sizeof($result); $j++)
						{
							$typeAffinity = get_type_affinity($result[$j]['type']);
							if($typeAffinity=="INTEGER" || $typeAffinity=="REAL" || $typeAffinity=="NUMERIC")
								echo $tdWithClass;
							else
								echo $tdWithClassLeft;
							if($row[$j]==="")
								echo "&nbsp;";
							elseif($row[$j]===NULL)
								echo "<i class='null'>NULL</i>";
							else
								echo htmlencode(subString($row[$j]));
							echo "</td>";
						}
						echo "</tr>";
					}
					echo "</table>";
					if($target_table_type == 'table')
					{
						echo "<a onclick='checkAll()'>".$lang['chk_all']."</a> / <a onclick='uncheckAll()'>".$lang['unchk_all']."</a> <i>".$lang['with_sel'].":</i> ";
						echo "<select name='type'>";
						echo "<option value='edit'>".$lang['edit']."</option>";
						echo "<option value='delete'>".$lang['del']."</option>";
						echo "</select> ";
						echo "<input type='submit' value='".$lang['go']."' name='massGo' class='btn'/>";
					}
					echo "</form>";
				}
				else
				//- Chart view
				{
					if(!isset($_SESSION[COOKIENAME.$target_table.'chartlabels']))
					{
						// No label-column set. Try to pick a text-column as label-column.
						for($i=0; $i<sizeof($result); $i++)
						{
							if(get_type_affinity($result[$i]['type'])=='TEXT')
							{
								$_SESSION[COOKIENAME.$target_table.'chartlabels'] = $i;
								break;
							}
						}
					}
					if(!isset($_SESSION[COOKIENAME.$target_table.'chartlabels']))
						// no text column found, use the first column
						$_SESSION[COOKIENAME.$target_table.'chartlabels'] = 0;
						
					if(!isset($_SESSION[COOKIENAME.$target_table.'chartvalues']))
					{
						// No value-column set. Pick the first numeric column if possible.
						// If not possible, pick the first column that is not the label-column.
						
						$potential_value_column = null;
						for($i=0; $i<sizeof($result); $i++)
						{
							if($potential_value_column===null && $i != $_SESSION[COOKIENAME.$target_table.'chartlabels'])
								// the first column (of any type) that is not the label-column
								$potential_value_column = $i;
							// check if the col is numeric
							$typeAffinity = get_type_affinity($result[$i]['type']);  
							if($typeAffinity=='INTEGER' || $typeAffinity=='REAL' || $typeAffinity=='NUMERIC')
							{
								// this is defined as a numeric column, so prefer this as a value column over $potential_value_column
								$_SESSION[COOKIENAME.$target_table.'chartvalues'] = $i;
								break;
							}
						}
						if(!isset($_SESSION[COOKIENAME.$target_table.'chartvalues']))
						{
							// we did not find a numeric column
							if($potential_value_column!==null)
								// use the $potential_value_column, i.e. the second column which is not the label-column
								$_SESSION[COOKIENAME.$target_table.'chartvalues'] = $potential_value_column;
							else
								// it's hopeless, there is only 1 column
								$_SESSION[COOKIENAME.$target_table.'chartvalues'] = 0;  
						}
					}
					
					if(!isset($_SESSION[COOKIENAME.'charttype']))
						$_SESSION[COOKIENAME.'charttype'] = 'bar';
						
					if(isset($_POST['chartsettings']))
					{
						$_SESSION[COOKIENAME.'charttype'] = $_POST['charttype'];	
						$_SESSION[COOKIENAME.$target_table.'chartlabels'] = $_POST['chartlabels'];
						$_SESSION[COOKIENAME.$target_table.'chartvalues'] = $_POST['chartvalues'];
					}
					//- Chart javascript code
					?>
					<script type='text/javascript' src='https://www.google.com/jsapi'></script>
					<script type='text/javascript'>
					google.load('visualization', '1.0', {'packages':['corechart']});
					google.setOnLoadCallback(drawChart);
					function drawChart()
					{
						var data = new google.visualization.DataTable();
						data.addColumn('string', '<?php echo $result[$_SESSION[COOKIENAME.$target_table.'chartlabels']]['name']; ?>');
						data.addColumn('number', '<?php echo $result[$_SESSION[COOKIENAME.$target_table.'chartvalues']]['name']; ?>');
						data.addRows([
						<?php
						for($i=0; $row = $db->fetch($table_result); $i++)
						{
							$label = str_replace("'", "", htmlencode($row[$_SESSION[COOKIENAME.$target_table.'chartlabels']]));
							$value = htmlencode($row[$_SESSION[COOKIENAME.$target_table.'chartvalues']]);
							
							if($value==NULL || $value=="")
								$value = 0;
								
							echo "['".$label."', ".$value."]";
							if($i<$resultRows-1)
								echo ",";
						}
						$height = ($resultRows+1) * 30;
						if($height>1000)
							$height = 1000;
						else if($height<300)
							$height = 300;
						if($_SESSION[COOKIENAME.'charttype']=="pie")
							$height = 800;
						?>
						]);
						var chartWidth = document.getElementById("main_column").offsetWidth - document.getElementById("chartsettingsbox").offsetWidth - 100;
						if(chartWidth>1000)
							chartWidth = 1000;
							
						var options = 
						{
							'width':chartWidth,
							'height':<?php echo $height; ?>,
							'title':'<?php echo $result[$_SESSION[COOKIENAME.$target_table.'chartlabels']]['name']." vs ".$result[$_SESSION[COOKIENAME.$target_table.'chartvalues']]['name']; ?>'
						};
						<?php
						if($_SESSION[COOKIENAME.'charttype']=="bar")
							echo "var chart = new google.visualization.BarChart(document.getElementById('chart_div'));";
						else if($_SESSION[COOKIENAME.'charttype']=="pie")
							echo "var chart = new google.visualization.PieChart(document.getElementById('chart_div'));";
						else
							echo "var chart = new google.visualization.LineChart(document.getElementById('chart_div'));";
						?>
						chart.draw(data, options);
					}
					</script>
					<div id="chart_div" style="float:left;"><?php echo $lang['no_chart']; ?></div>
					<?php
					echo "<fieldset style='float:right; text-align:center;' id='chartsettingsbox'><legend><b>Chart Settings</b></legend>";
					echo $params->getForm(array('action'=>'row_view'));
					echo $lang['chart_type'].": <select name='charttype'>";
					echo "<option value='bar'";
					if($_SESSION[COOKIENAME.'charttype']=="bar")
						echo " selected='selected'";
					echo ">".$lang['chart_bar']."</option>";
					echo "<option value='pie'";
					if($_SESSION[COOKIENAME.'charttype']=="pie")
						echo " selected='selected'";
					echo ">".$lang['chart_pie']."</option>";
					echo "<option value='line'";
					if($_SESSION[COOKIENAME.'charttype']=="line")
						echo " selected='selected'";
					echo ">".$lang['chart_line']."</option>";
					echo "</select>";
					echo "<br/><br/>";
					echo $lang['lbl'].": <select name='chartlabels'>";
					for($i=0; $i<sizeof($result); $i++)
					{
						if(isset($_SESSION[COOKIENAME.$target_table.'chartlabels']) && $_SESSION[COOKIENAME.$target_table.'chartlabels']==$i)
							echo "<option value='".$i."' selected='selected'>".htmlencode($result[$i]['name'])."</option>";
						else
							echo "<option value='".$i."'>".htmlencode($result[$i]['name'])."</option>";
					}
					echo "</select>";
					echo "<br/><br/>";
					echo $lang['val'].": <select name='chartvalues'>";
					for($i=0; $i<sizeof($result); $i++)
					{
						if(isset($_SESSION[COOKIENAME.$target_table.'chartvalues']) && $_SESSION[COOKIENAME.$target_table.'chartvalues']==$i)
							echo "<option value='".$i."' selected='selected'>".htmlencode($result[$i]['name'])."</option>";
						else
							echo "<option value='".$i."'>".htmlencode($result[$i]['name'])."</option>";
					}
					echo "</select>";
					echo "<br/><br/>";
					echo "<input type='submit' name='chartsettings' value='".$lang['update']."' class='btn'/>";
					echo "</form>";
					echo "</fieldset>";
					echo "<div style='clear:both;'></div>";
					//end chart view
				}
			}
			else if($rowCount>0)//no rows - do nothing
			{
				echo "<br/><br/>".$lang['no_rows'];
			}
			elseif($target_table_type == 'table')
			{
				echo "<br/><br/>".$lang['empty_tbl']." ".$params->getLink(array('action'=>'row_create'), $lang['click']) ." ".$lang['insert_rows'];
			}

			break;

		//- Create new row (=row_create)
		case "row_create":
			$fieldStr = "";
			echo $params->getForm(array('action'=>'row_create'));
			echo $lang['restart_insert'];
			echo " <select name='num'>";
			for($i=1; $i<=40; $i++)
			{
				if(isset($_POST['num']) && $_POST['num']==$i)
					echo "<option value='".$i."' selected='selected'>".$i."</option>";
				else
					echo "<option value='".$i."'>".$i."</option>";
			}
			echo "</select> ";
			echo $lang['rows'];
			echo " <input type='submit' value='".$lang['go']."' class='btn'/>";
			echo "</form>";
			echo "<br/>";
			$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
			$result = $db->selectArray($query);
			echo $params->getForm(array('action'=>'row_create','confirm'=>'1'));
			if(isset($_POST['num']))
				$num = $_POST['num'];
			else
				$num = 1;
			echo "<input type='hidden' name='numRows' value='".$num."'/>";
			for($j=0; $j<$num; $j++)
			{
				if($j>0)
					echo "<label><input type='checkbox' value='ignore' name='".$j.":ignore' id='row_".$j."_ignore' checked='checked'/> ".$lang['ignore']."</label><br/>";
				echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
				echo "<tr>";
				echo "<td class='tdheader'>".$lang['fld']."</td>";
				echo "<td class='tdheader'>".$lang['type']."</td>";
				echo "<td class='tdheader'>".$lang['func']."</td>";
				echo "<td class='tdheader'>Null</td>";
				echo "<td class='tdheader'>".$lang['val']."</td>";
				echo "</tr>";

				for($i=0; $i<sizeof($result); $i++)
				{
					$field = $result[$i]['name'];
					if($j==0)
						$fieldStr .= ":".$field;
					$type = strtolower($result[$i]['type']);
					$typeAffinity = get_type_affinity($type);
					$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
					$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
					echo "<tr>";
					echo $tdWithClassLeft;
					echo htmlencode($field);
					echo "</td>";
					echo $tdWithClassLeft;
					echo htmlencode($type);
					echo "</td>";
					echo $tdWithClassLeft;
					echo "<select name='function_".$j."_".$i."' onchange='notNull(\"row_".$j."_field_".$i."_null\");'>";
					echo "<option value=''>&nbsp;</option>";
					foreach (array_merge($sqlite_functions, $custom_functions) as $f) {
						echo "<option value='".htmlencode($f)."'>".htmlencode($f)."</option>";
					}
					echo "</select>";
					echo "</td>";
					//we need to have a column dedicated to nulls -di
					echo $tdWithClassLeft;
					if($result[$i]['notnull']==0)
					{
						if($result[$i]['dflt_value']==="NULL")
							echo "<input type='checkbox' name='".$j.":".$i."_null' id='row_".$j."_field_".$i."_null' checked='checked' onclick='disableText(this, \"row_".$j."_field_".$i."_value\");'/>";
						else
							echo "<input type='checkbox' name='".$j.":".$i."_null' id='row_".$j."_field_".$i."_null' onclick='disableText(this, \"row_".$j."_field_".$i."_value\");'/>";
					}
					echo "</td>";
					echo $tdWithClassLeft;
					if($result[$i]['dflt_value'] === "NULL")
						$dflt_value = "";
					else
						$dflt_value = htmlencode(deQuoteSQL($result[$i]['dflt_value']));
					
					if($typeAffinity=="INTEGER" || $typeAffinity=="REAL" || $typeAffinity=="NUMERIC")
						echo "<input type='text' id='row_".$j."_field_".$i."_value' name='".$j.":".$i."' value='".$dflt_value."' onblur='changeIgnore(this, \"row_".$j."_ignore\");' onclick='notNull(\"row_".$j."_field_".$i."_null\");'/>";
					else
						echo "<textarea id='row_".$j."_field_".$i."_value' name='".$j.":".$i."' rows='5' cols='60' onclick='notNull(\"row_".$j."_field_".$i."_null\");' onblur='changeIgnore(this, \"row_".$j."_ignore\");'>".$dflt_value."</textarea>";
				echo "</td>";
				echo "</tr>";
				}
				echo "<tr>";
				echo "<td class='tdheader' style='text-align:right;' colspan='5'>";
				echo "<input type='submit' value='".$lang['insert']."' class='btn'/>";
				echo "</td>";
				echo "</tr>";
				echo "</table><br/>";
			}
			$fieldStr = substr($fieldStr, 1);
			echo "<input type='hidden' name='fields' value='".htmlencode($fieldStr)."'/>";
			echo "</form>";
			break;

		//- Edit or delete row (=row_editordelete)
		case "row_editordelete":
			if(isset($_POST['check']))
				$pks = $_POST['check'];
			else if(isset($_GET['pk']))
				$pks = array($_GET['pk']);
			else $pks[0] = "";
			$str = $pks[0];
			for($i=1; $i<sizeof($pks); $i++)
			{
				$str .= ", ".$pks[$i];
			}
			if($str=="") //nothing was selected so show an error
			{
				echo "<div class='confirm'>";
				echo $lang['err'].": ".$lang['no_sel'];
				echo "</div>";
				echo "<br/><br/>".$params->getLink(array('action'=>'row_view'),$lang['return']);
			}
			else
			{
				if((isset($_POST['type']) && $_POST['type']=="edit") || (isset($_GET['type']) && $_GET['type']=="edit")) //edit
				{
					echo $params->getForm(array('action'=>'row_edit', 'confirm'=>'1', 'pk'=>json_encode($pks)));
					$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
					$result = $db->selectArray($query);

					//build the POST array of fields
					$fieldStr = $result[0][1];
					for($j=1; $j<sizeof($result); $j++)
						$fieldStr .= ":".$result[$j][1];
						
					$primary_key = $db->getPrimaryKey($target_table);
					
					echo "<input type='hidden' name='fieldArray' value='".htmlencode($fieldStr)."'/>";

					for($j=0; $j<sizeof($pks); $j++)
					{
						$query = "SELECT * FROM ".$db->quote_id($target_table)." WHERE " . $db->wherePK($target_table, json_decode($pks[$j]));
						$result1 = $db->select($query);

						echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
						echo "<tr>";
						echo "<td class='tdheader'>".$lang['fld']."</td>";
						echo "<td class='tdheader'>".$lang['type']."</td>";
						echo "<td class='tdheader'>".$lang['func']."</td>";
						echo "<td class='tdheader'>Null</td>";
						echo "<td class='tdheader'>".$lang['val']."</td>";
						echo "</tr>";

						for($i=0; $i<sizeof($result); $i++)
						{
							$field = $result[$i][1];
							$type = $result[$i]['type'];
							$typeAffinity = get_type_affinity($type);
							$value = $result1[$i];
							$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
							$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
							echo "<tr>";
							echo $tdWithClass;
							echo htmlencode($field);
							echo "</td>";
							echo $tdWithClass;
							echo htmlencode($type);
							echo "</td>";
							echo $tdWithClassLeft;
							echo "<select name='function_".$i."[]' onchange='notNull(\"".$j.":".$i."_null\");'>";
							echo "<option value=''></option>";
							foreach (array_merge($sqlite_functions, $custom_functions) as $f) {
								echo "<option value='".htmlencode($f)."'>".htmlencode($f)."</option>";
							}
							echo "</select>";
							echo "</td>";
							echo $tdWithClassLeft;
							if($result[$i][3]==0)
							{
								if($value===NULL)
									echo "<input type='checkbox' name='".$i."_null[]' id='".$j.":".$i."_null' checked='checked'/>";
								else
									echo "<input type='checkbox' name='".$i."_null[]' id='".$j.":".$i."_null'/>";
							}
							echo "</td>";
							echo $tdWithClassLeft;
							if($typeAffinity=="INTEGER" || $typeAffinity=="REAL" || $typeAffinity=="NUMERIC")
								echo "<input type='text' name='".$i."[]' value='".htmlencode($value)."' onblur='changeIgnore(this, \"".$j."\", \"".$j.":".$i."_null\")' />";
							else
								echo "<textarea name='".$i."[]' rows='1' cols='60' class='".htmlencode($field)."_textarea' onblur='changeIgnore(this, \"".$j."\", \"".$j.":".$i."_null\")'>".htmlencode($value)."</textarea>";
							echo "</td>";
							echo "</tr>";
						}
						echo "<tr>";
						echo "<td class='tdheader' style='text-align:right;' colspan='5'>";
						// Note: the 'Save changes' button must be first in the code so it is the one used when submitting the form with the Enter key (issue #215)
						echo "<input type='submit' value='".$lang['save_ch']."' class='btn'/> ";
						echo "<input type='submit' name='new_row' value='".$lang['new_insert']."' class='btn'/> ";
						echo $params->getLink(array('action'=>'row_view'), $lang['cancel']);
						echo "</td>";
						echo "</tr>";
						echo "</table>";
						echo "<br/>";
					}
					echo "</form>";
				}
				else //delete
				{
					echo $params->getForm(array('action'=>'row_delete', 'confirm'=>'1', 'pk'=>json_encode($pks)));
					echo "<div class='confirm'>";
					printf($lang['ques_del_rows'], htmlencode($str), htmlencode($target_table));
					echo "<br/><br/>";
					echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
					echo $params->getLink(array('action'=>'row_view'), $lang['cancel']);
					echo "</div>";
				}
			}
			break;

	//- Column actions

		//- View table structure (=column_view)
		case "column_view":
			$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
			$result = $db->selectArray($query);

			echo $params->getForm(array('action'=>'column_confirm'), 'get', false, 'checkForm');
			echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
			echo "<tr>";
			if($target_table_type == 'table')
				echo "<td colspan='3'></td>";
			echo "<td class='tdheader'>".$lang['col']." #</td>";
			echo "<td class='tdheader'>".$lang['fld']."</td>";
			echo "<td class='tdheader'>".$lang['type']."</td>";
			echo "<td class='tdheader'>".$lang['not_null']."</td>";
			echo "<td class='tdheader'>".$lang['def_val']."</td>";
			echo "<td class='tdheader'>".$lang['prim_key']."</td>";
			echo "</tr>";

			$noPrimaryKey = true;
			
			for($i=0; $i<sizeof($result); $i++)
			{
				$colVal = $result[$i][0];
				$fieldVal = $result[$i][1];
				$typeVal = $result[$i]['type'];
				$notnullVal = $result[$i][3];
				$defaultVal = $result[$i][4];
				$primarykeyVal = $result[$i][5];

				if(intval($notnullVal)!=0)
					$notnullVal = $lang['yes'];
				else
					$notnullVal = $lang['no'];
				if(intval($primarykeyVal)!=0)
				{
					$primarykeyVal = $lang['yes'];
					$noPrimaryKey = false;
				}
				else
					$primarykeyVal = $lang['no'];

				$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
				$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
				echo "<tr>";
				if($target_table_type == 'table')
				{
					echo $tdWithClass;
					echo "<input type='checkbox' name='check[]' value='".htmlencode($fieldVal)."' id='check_".$i."'/>";
					echo "</td>";
					echo $tdWithClass;
					echo $params->getLink(array('action'=>'column_edit', 'pk'=>$fieldVal),"<span>".$lang['edit']."</span>",'edit', $lang['edit']);
					echo "</td>";
					echo $tdWithClass;
					echo $params->getLink(array('action'=>'column_confirm', 'action2'=>'column_delete', 'pk'=>$fieldVal),"<span>".$lang['del']."</span>",'delete', $lang['del']);
					echo "</td>";
				}
				echo $tdWithClass;
				echo htmlencode($colVal);
				echo "</td>";
				echo $tdWithClassLeft;
				echo htmlencode($fieldVal);
				echo "</td>";
				echo $tdWithClassLeft;
				echo htmlencode($typeVal);
				echo "</td>";
				echo $tdWithClassLeft;
				echo htmlencode($notnullVal);
				echo "</td>";
				echo $tdWithClassLeft;
				if($defaultVal===NULL)
					echo "<i class='null'>".$lang['none']."</i>";
				elseif($defaultVal==="NULL")
					echo "<i class='null'>NULL</i>";
				else
					echo htmlencode($defaultVal);
				echo "</td>";
				echo $tdWithClassLeft;
				echo htmlencode($primarykeyVal);
				echo "</td>";
				echo "</tr>";
			}

			echo "</table>";
			if($target_table_type == 'table')
			{
				echo "<a onclick='checkAll()'>".$lang['chk_all']."</a> / <a onclick='uncheckAll()'>".$lang['unchk_all']."</a> <i>".$lang['with_sel'].":</i> ";
				echo "<select name='action2'>";
				//echo "<option value='edit'>".$lang['edit']."</option>";
				echo "<option value='column_delete'>".$lang['del']."</option>";
				if($noPrimaryKey)
					echo "<option value='primarykey_add'>".$lang['prim_key']."</option>";
				echo "</select> ";
				echo "<input type='submit' value='".$lang['go']."' name='massGo' class='btn'/>";
			}
			echo "</form>";
			if($target_table_type == 'table')
			{
				echo "<br/>";
				echo $params->getForm(array('action'=>'column_create'), 'get');
				echo $lang['add']." <input type='text' name='tablefields' style='width:30px;' value='1'/> ".$lang['tbl_end']." <input type='submit' value='".$lang['go']."' name='addfields' class='btn'/>";
				echo "</form>";
			}
			
			$query = "SELECT sql FROM sqlite_master WHERE name=".$db->quote($target_table);
			$master = $db->selectArray($query);
			
			echo "<br/>";
			echo "<br/>";
			echo "<div class='confirm'>";
			echo "<b>".$lang['query_used_'.$target_table_type]."</b><br/>";
			echo "<span style='font-size:11px;'>".htmlencode($master[0]['sql'])."</span>";
			echo "</div>";
			echo "<br/>";
			if($target_table_type != 'view')
			{
				echo "<br/><hr/><br/>";
				//$query = "SELECT * FROM sqlite_master WHERE type='index' AND tbl_name='".$target_table."'";
				$query = "PRAGMA index_list(".$db->quote_id($target_table).")";
				$result = $db->selectArray($query);
				if(sizeof($result)>0)
				{
					echo "<h2>".$lang['indexes'].":</h2>";
					echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
					echo "<tr>";
					echo "<td colspan='1'>";
					echo "</td>";
					echo "<td class='tdheader'>".$lang['name']."</td>";
					echo "<td class='tdheader'>".$lang['unique']."</td>";
					echo "<td class='tdheader'>".$lang['seq_no']."</td>";
					echo "<td class='tdheader'>".$lang['col']." #</td>";
					echo "<td class='tdheader'>".$lang['fld']."</td>";
					echo "</tr>";
					for($i=0; $i<sizeof($result); $i++)
					{
						if($result[$i]['unique']==0)
							$unique = $lang['no'];
						else
							$unique = $lang['yes'];

						$query = "PRAGMA index_info(".$db->quote_id($result[$i]['name']).")";
						$info = $db->selectArray($query);
						$span = sizeof($info);

						$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
						$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
						$tdWithClassSpan = "<td class='td".($i%2 ? "1" : "2")."' rowspan='".$span."'>";
						$tdWithClassLeftSpan = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;' rowspan='".$span."'>";
						echo "<tr>";
						echo $tdWithClassSpan;
						echo $params->getLink(array('action'=>'index_delete', 'pk'=>$result[$i]['name']), "<span>".$lang['del']."</span>", 'delete', $lang['del']);
						echo "</td>";
						echo $tdWithClassLeftSpan;
						echo $result[$i]['name'];
						echo "</td>";
						echo $tdWithClassLeftSpan;
						echo $unique;
						echo "</td>";
						for($j=0; $j<$span; $j++)
						{
							if($j!=0)
								echo "<tr>";
							echo $tdWithClassLeft;
							echo htmlencode($info[$j]['seqno']);
							echo "</td>";
							echo $tdWithClassLeft;
							echo htmlencode($info[$j]['cid']);
							echo "</td>";
							echo $tdWithClassLeft;
							echo htmlencode($info[$j]['name']);
							echo "</td>";
							echo "</tr>";
						}
					}
					echo "</table><br/><br/>";
				}
				
				$query = "SELECT * FROM sqlite_master WHERE type='trigger' AND tbl_name=".$db->quote($target_table)." ORDER BY name";
				$result = $db->selectArray($query);
				//print_r($result);
				if(sizeof($result)>0)
				{
					echo "<h2>".$lang['triggers'].":</h2>";
					echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
					echo "<tr>";
					echo "<td colspan='1'>";
					echo "</td>";
					echo "<td class='tdheader'>".$lang['name']."</td>";
					echo "<td class='tdheader'>".$lang['sql']."</td>";
					echo "</tr>";
					for($i=0; $i<sizeof($result); $i++)
					{
						$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
						echo "<tr>";
						echo $tdWithClass;
						echo $params->getLink(array('action'=>'trigger_delete', 'pk'=>$result[$i]['name']), "<span>".$lang['del']."</span>", 'delete', $lang['del']);
						echo "</td>";
						echo $tdWithClass;
						echo htmlencode($result[$i]['name']);
						echo "</td>";
						echo $tdWithClass;
						echo htmlencode($result[$i]['sql']);
						echo "</td>";
					}
					echo "</table><br/><br/>";
				}
				
				echo $params->getForm(array('action'=>'index_create'),'get');
				echo "<br/><div class='tdheader'>";
				echo $lang['create_index2']." <input type='text' name='numcolumns' style='width:30px;' value='1'/> ".$lang['cols']." <input type='submit' value='".$lang['go']."' name='addindex' class='btn'/>";
				echo "</div>";
				echo "</form>";
				
				echo $params->getForm(array('action'=>'trigger_create'),'get');
				echo "<br/><div class='tdheader'>";
				echo $lang['create_trigger2']." <input type='submit' value='".$lang['go']."' name='addindex' class='btn'/>";
				echo "</div>";
				echo "</form>";
			}
			break;

		//- Create column (=column_create)
		case "column_create":
			echo "<h2>".sprintf($lang['new_fld'],htmlencode($_GET['table']))."</h2>";
			if($_GET['tablefields']=="" || intval($_GET['tablefields'])<=0)
				echo $lang['specify_fields'];
			else if($_GET['table']=="")
				echo $lang['specify_tbl'];
			else
			{
				$num = intval($_GET['tablefields']);
				$name = $_GET['table'];
				echo $params->getForm(array('action'=>'column_create', 'confirm'=>'1'));
				echo "<input type='hidden' name='rows' value='".$num."'/>";
				echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
				echo "<tr>";
				$headings = array($lang["fld"], $lang["type"], $lang["prim_key"]);    
				if($db->getType() != "SQLiteDatabase") $headings[] = $lang["autoincrement"];
				$headings[] = $lang["not_null"];
				$headings[] = $lang["def_val"];
				
				for($k=0; $k<count($headings); $k++)
					echo "<td class='tdheader'>" . $headings[$k] . "</td>";
				echo "</tr>";

				for($i=0; $i<$num; $i++)
				{
					$tdWithClass = "<td class='td" . ($i%2 ? "1" : "2") . "'>";
					echo "<tr>";
					echo $tdWithClass;
					echo "<input type='text' name='".$i."_field' style='width:200px;'/>";
					echo "</td>";
					echo $tdWithClass;
					echo "<select name='".$i."_type' id='i".$i."_type' onchange='toggleAutoincrement(".$i.");'>";
					foreach ($sqlite_datatypes as $t) {
						echo "<option value='".htmlencode($t)."'>".htmlencode($t)."</option>";
					}
					echo "</select>";
					echo "</td>";
					echo $tdWithClass;
					echo "<label><input type='checkbox' name='".$i."_primarykey'/> ".$lang['yes']."</label>";
					echo "</td>";
					if($db->getType() != "SQLiteDatabase")
					{
						echo $tdWithClass;
						echo "<label><input type='checkbox' name='".$i."_autoincrement' id='i".$i."_autoincrement'/> ".$lang['yes']."</label>";
						echo "</td>";
					}
					echo $tdWithClass;
					echo "<label><input type='checkbox' name='".$i."_notnull'/> ".$lang['yes']."</label>";
					echo "</td>";
					echo $tdWithClass;
					echo "<select name='".$i."_defaultoption' id='i".$i."_defaultoption' onchange=\"if(this.value!='defined' && this.value!='expr') document.getElementById('i".$i."_defaultvalue').value='';\">";
					echo "<option value='none'>".$lang['none']."</option><option value='defined'>".$lang['as_defined'].":</option><option>NULL</option><option>CURRENT_TIME</option><option>CURRENT_DATE</option><option>CURRENT_TIMESTAMP</option><option value='expr'>".$lang['expression'].":</option>";
					echo "</select>";
					echo "<input type='text' name='".$i."_defaultvalue' id='i".$i."_defaultvalue' style='width:100px;' onchange=\"if(document.getElementById('i".$i."_defaultoption').value!='expr') document.getElementById('i".$i."_defaultoption').value='defined';\"/>";
					echo "</td>";
					echo "</tr>";
				}
				echo "<tr>";
				echo "<td class='tdheader' style='text-align:right;' colspan='6'>";
				echo "<input type='submit' value='".$lang['add_flds']."' class='btn'/> ";
				echo $params->getLink(array('action'=>'column_view'), $lang['cancel']);
				echo "</td>";
				echo "</tr>";
				echo "</table>";
				echo "</form>";
			}
			break;

		//- Delete column (=column_confirm)
		case "column_confirm":
			if(isset($_GET['check']))
				$pks = $_GET['check'];
			elseif(isset($_GET['pk']))
				$pks = array($_GET['pk']);
			else $pks = array();
			
			if(sizeof($pks)==0) //nothing was selected so show an error
			{
				echo "<div class='confirm'>";
				echo $lang['err'].": ".$lang['no_sel'];
				echo "</div>";
				echo "<br/><br/>";
				echo $params->getLink(array('action'=>'column_view'), $lang['return']);
			}
			else
			{
				$str = $pks[0];
				$pkVal = $pks[0];
				for($i=1; $i<sizeof($pks); $i++)
				{
					$str .= ", ".$pks[$i];
					$pkVal .= ":".$pks[$i];
				}
				echo $params->getForm(array('action'=>$_GET['action2'], 'confirm'=>'1', 'pk'=>$pkVal));
				echo "<div class='confirm'>";
				printf($lang['ques_'.$_GET['action2']], htmlencode($str), htmlencode($target_table));
				echo "<br/><br/>";
				echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
				echo $params->getLink(array('action'=>'column_view'), $lang['cancel']);
				echo "</div>";
			}
			break;

		//- Edit column (=column_edit)
		case "column_edit":
			echo "<h2>".sprintf($lang['edit_col'], htmlencode($_GET['pk']))." ".$lang['on_tbl']." '".htmlencode($target_table)."'</h2>";
			echo $lang['sqlite_limit']."<br/><br/>";
			if(!isset($_GET['pk']))
				echo $lang['specify_col'];
			else if (!$target_table)
				echo $lang['specify_tbl'];
			else
			{
				$query = "PRAGMA table_info(".$db->quote_id($target_table).")";
				$result = $db->selectArray($query);

				for($i=0; $i<sizeof($result); $i++)
				{
					if($result[$i][1]==$_GET['pk'])
					{
						$colVal = $result[$i][0];
						$fieldVal = $result[$i][1];
						$typeVal = $result[$i]['type'];
						$notnullVal = $result[$i][3];
						$defaultVal = $result[$i][4];
						$primarykeyVal = $result[$i][5];
						break;
					}
				}
				
				if(!isset($fieldVal))
				{
					echo "<div class='confirm'>".$lang['err'].": ".sprintf($lang['col_inexistent'], htmlencode($_GET['pk']))."</div>";
				}
				else
				{
					$name = $target_table;
					echo $params->getForm(array('action'=>'column_edit', 'confirm'=>'1'));
					echo "<input type='hidden' name='oldvalue' value='".htmlencode($_GET['pk'])."'/>";
					echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
					echo "<tr>";
					//$headings = array("Field", "Type", "Primary Key", "Autoincrement", "Not NULL", "Default Value");
					$headings = array($lang["fld"], $lang["type"]);
					for($k=0; $k<count($headings); $k++)
						echo "<td class='tdheader'>".$headings[$k]."</td>";
					echo "</tr>";
				
					$i = 0;
					$tdWithClass = "<td class='td" . ($i%2 ? "1" : "2") . "'>";
					echo "<tr>";
					echo $tdWithClass;
					echo "<input type='text' name='".$i."_field' style='width:200px;' value='".htmlencode($fieldVal)."'/>";
					echo "</td>";
					echo $tdWithClass;
					echo "<select name='".$i."_type' id='i".$i."_type' onchange='toggleAutoincrement(".$i.");'>";
					if(!in_array($typeVal, $sqlite_datatypes))
						echo "<option value='".htmlencode($typeVal)."' selected='selected'>".htmlencode($typeVal)."</option>";
					foreach ($sqlite_datatypes as $t) {
						if($t==$typeVal)
							echo "<option value='".htmlencode($t)."' selected='selected'>".htmlencode($t)."</option>";
						else
							echo "<option value='".htmlencode($t)."'>".htmlencode($t)."</option>";
					}
					echo "</select>";
					echo "</td>";
					/*
					echo $tdWithClass;
					if($primarykeyVal)
						echo "<input type='checkbox' name='".$i."_primarykey' checked='checked'/> Yes";
					else
						echo "<input type='checkbox' name='".$i."_primarykey'/> Yes";
					echo "</td>";
					echo $tdWithClass;
					if(1==2)
						echo "<input type='checkbox' name='".$i."_autoincrement' id='".$i."_autoincrement' checked='checked'/> Yes";
					else
						echo "<input type='checkbox' name='".$i."_autoincrement' id='".$i."_autoincrement'/> Yes";
					echo "</td>";
					echo $tdWithClass;
					if($notnullVal)
						echo "<input type='checkbox' name='".$i."_notnull' checked='checked'/> Yes";
					else
						echo "<input type='checkbox' name='".$i."_notnull'/> Yes";
					echo "</td>";
					echo $tdWithClass;
					echo "<input type='text' name='".$i."_defaultvalue' value='".$defaultVal."' style='width:100px;'/>";
					echo "</td>";
					*/
					echo "</tr>";
	
					echo "<tr>";
					echo "<td class='tdheader' style='text-align:right;' colspan='6'>";
					echo "<input type='submit' value='".$lang['save_ch']."' class='btn'/> ";
					echo $params->getLink(array('action'=>'column_view'), $lang['cancel']);
					echo "</td>";
					echo "</tr>";
					echo "</table>";
					echo "</form>";
				}
			}
			break;

		//- Delete index (=index_delete)
		case "index_delete":
			echo $params->getForm(array('action'=>'index_delete', 'pk'=>$_GET['pk'], 'confirm'=>'1'));
			echo "<div class='confirm'>";
			echo sprintf($lang['ques_del_index'], htmlencode($_GET['pk']))."<br/><br/>";
			echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
			echo $params->getLink(array('action'=>'column_view'), $lang['cancel']);
			echo "</div>";
			echo "</form>";
			break;

		//- Delete trigger (=trigger_delete)
		case "trigger_delete":
			echo $params->getForm(array('action'=>'trigger_delete', 'pk'=>$_GET['pk'], 'confirm'=>'1'));
			echo "<div class='confirm'>";
			echo sprintf($lang['ques_del_trigger'], htmlencode($_GET['pk']))."<br/><br/>";
			echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
			echo $params->getLink(array('action'=>'column_view'), $lang['cancel']);
			echo "</div>";
			echo "</form>";
			break;

		//- Create trigger (=trigger_create)
		case "trigger_create":
			echo "<h2>".$lang['create_trigger']." '".htmlencode($_GET['table'])."'</h2>";
			if($_GET['table']=="")
				echo $lang['specify_tbl'];
			else
			{
				echo $params->getForm(array('action'=>'trigger_create', 'confirm'=>'1'));
				echo $lang['trigger_name'].": <input type='text' name='trigger_name'/><br/><br/>";
				echo "<fieldset><legend>".$lang['db_event']."</legend>";
				echo $lang['before']."/".$lang['after'].": ";
				echo "<select name='beforeafter'>";
				echo "<option value=''></option>";
				echo "<option value='BEFORE'>".$lang['before']."</option>"; 
				echo "<option value='AFTER'>".$lang['after']."</option>"; 
				echo "<option value='INSTEAD OF'>".$lang['instead']."</option>"; 
				echo "</select>";
				echo "<br/><br/>";
				echo $lang['event'].": ";
				echo "<select name='event'>";
				echo "<option value='DELETE'>".$lang['del']."</option>";
				echo "<option value='INSERT'>".$lang['insert']."</option>";
				echo "<option value='UPDATE'>".$lang['update']."</option>";
				echo "</select>";
				echo "</fieldset><br/><br/>";
				echo "<fieldset><legend>".$lang['trigger_act']."</legend>";
				echo "<label><input type='checkbox' name='foreachrow'/> ".$lang['each_row']."</label><br/><br/>";
				echo $lang['when_exp'].":<br/>";
				echo "<textarea name='whenexpression' style='width:500px; height:100px;' rows='8' cols='50'></textarea>";
				echo "<br/><br/>";
				echo $lang['trigger_step'].":<br/>";
				echo "<textarea name='triggersteps' style='width:500px; height:100px;' rows='8' cols='50'></textarea>";
				echo "</fieldset><br/><br/>";
				echo "<input type='submit' value='".$lang['create_trigger2']."' class='btn'/> ";
				echo $params->getLink(array('action'=>'column_view'), $lang['cancel']);
				echo "</form>";
			}
			break;

		//- Create index (=index_create)
		case "index_create":
			echo "<h2>".$lang['create_index']." '".htmlencode($_GET['table'])."'</h2>";
			if($_GET['numcolumns']=="" || intval($_GET['numcolumns'])<=0)
				echo $lang['specify_fields'];
			else if($_GET['table']=="")
				echo $lang['specify_tbl'];
			else
			{
				echo $params->getForm(array('action'=>'index_create', 'confirm'=>'1'));
				$num = intval($_GET['numcolumns']);
				$query = "PRAGMA table_info(".$db->quote_id($_GET['table']).")";

				$result = $db->selectArray($query);
				echo "<fieldset><legend>".$lang['define_index']."</legend>";
				echo "<label for='index_name'>".$lang['index_name'].":</label> <input type='text' name='name' id='index_name'/><br/>";
				echo "<label for='index_duplicate'>".$lang['dup_val'].":</label>";
				echo "<select name='duplicate' id='index_duplicate'>";
				echo "<option value='yes'>".$lang['allow']."</option>";
				echo "<option value='no'>".$lang['not_allow']."</option>";
				echo "</select><br/>";
				if(version_compare($db->getSQLiteVersion(),'3.8.0')>=0)
					echo "<label for='index_where'>WHERE:</label> <input type='text' name='where' id='index_where'/> ".helpLink($lang['help10']);
				echo "</fieldset>";
				echo "<br/>";
				echo "<fieldset><legend>".$lang['define_in_col']."</legend>";
				for($i=0; $i<$num; $i++)
				{
					echo "<select name='".$i."_field'>";
					echo "<option value=''>--".$lang['ignore']."--</option>";
					for($j=0; $j<sizeof($result); $j++)
						echo "<option value='".htmlencode($result[$j][1])."'>".htmlencode($result[$j][1])."</option>";
					echo "</select> ";
					echo "<select name='".$i."_order'>";
					echo "<option value=''></option>";
					echo "<option value=' ASC'>".$lang['asc']."</option>";
					echo "<option value=' DESC'>".$lang['desc']."</option>";
					echo "</select><br/>";
				}
				echo "</fieldset>";
				echo "<br/><br/>";
				echo "<input type='hidden' name='num' value='".$num."'/>";
				echo "<input type='submit' value='".$lang['create_index1']."' class='btn'/> ";
				echo $params->getLink(array('action'=>'column_view'), $lang['cancel']);
				echo "</form>";
			}
			break;
	}
	echo "</div>";
}

//- HMTL: views for databases	
if(!$target_table && !isset($_GET['confirm']) && (!isset($_GET['action']) || (isset($_GET['action']) && $_GET['action']!="table_create"))) //the absence of these fields means we are viewing the database homepage
{
  //- Switch on $view (actually a series of if-else)

	if($view=="structure")
	{
		//- Database structure, shows all the tables (=structure)
	
		if($db->isWritable() && !$db->isDirWritable())
		{
			echo "<div class='confirm' style='margin:10px 20px;'>";
			echo $lang['attention'].': '.$lang['directory_not_writable'];
			echo "</div><br/>";
		}
		
		if ($auth->isPasswordDefault())
		{
			echo "<div class='confirm' style='margin:20px 0px;'>";
			echo sprintf($lang['warn_passwd'],(is_readable('phpliteadmin.config.php')?'phpliteadmin.config.php':basename(__FILE__)))."<br />".$lang['warn0'];
			echo "</div>";
		}
		
		echo "<b>".$lang['db_name']."</b>: ".htmlencode($db->getName())."<br/>";
		echo "<b>".$lang['db_path']."</b>: ".htmlencode($db->getPath())."<br/>";
		echo "<b>".$lang['db_size']."</b>: ".$db->getSize()." KB<br/>";
		echo "<b>".$lang['db_mod']."</b>: ".$db->getDate()."<br/>";
		echo "<b>".$lang['sqlite_v']."</b>: ".$db->getSQLiteVersion()."<br/>";
		echo "<b>".$lang['sqlite_ext']."</b> ".helpLink($lang['help1']).": ".$db->getType()."<br/>"; 
		echo "<b>".$lang['php_v']."</b>: ".phpversion()."<br/>";
		echo "<b>".PROJECT." ".$lang["ver"]."</b>: ".VERSION;
		echo " <a href='".PROJECT_URL."' target='_blank' id='oldVersion' style='display: none;' class='warning'>".$lang['new_version']."</a><br/><br/>";
		echo "<script type='text/javascript'>checkVersion('".VERSION."','".VERSION_CHECK_URL."');</script>";
		
		if(isset($_GET['sort']) && ($_GET['sort']=='type' || $_GET['sort']=='name'))
			$_SESSION[COOKIENAME.'sortTables'] = $_GET['sort'];
		if(isset($_GET['order']) && ($_GET['order']=='ASC' || $_GET['order']=='DESC'))
			$_SESSION[COOKIENAME.'orderTables'] = $_GET['order'];
				
		$query = "SELECT type, name FROM sqlite_master WHERE (type='table' OR type='view') AND name!='' AND name NOT LIKE 'sqlite_%'";
		$queryAdd = "";
		if(isset($_SESSION[COOKIENAME.'sortTables']))
			$queryAdd .= " ORDER BY ".$db->quote_id($_SESSION[COOKIENAME.'sortTables']);
		else
			$queryAdd .= " ORDER BY \"name\"";
		if(isset($_SESSION[COOKIENAME.'orderTables']))
			$queryAdd .= " ".$_SESSION[COOKIENAME.'orderTables'];
		$query .= $queryAdd;
		$result = $db->selectArray($query);

		if(sizeof($result)==0)
			echo $lang['no_tbl']."<br/><br/>";
		else
		{
			echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
			echo "<tr>";
			
			echo "<td class='tdheader'>";
			if(isset($_SESSION[COOKIENAME.'sortTables']))
				$orderTag = ($_SESSION[COOKIENAME.'sortTables']=="type" && $_SESSION[COOKIENAME.'orderTables']=="ASC") ? "DESC" : "ASC";
			else
				$orderTag = "ASC";
			echo $params->getLink(array('sort'=>'type', 'order'=>$orderTag), $lang['type']);
			echo helpLink($lang['help3']); 
			if(isset($_SESSION[COOKIENAME.'sortTables']) && $_SESSION[COOKIENAME.'sortTables']=="type")
				echo (($_SESSION[COOKIENAME.'orderTables']=="ASC") ? " <b>&uarr;</b>" : " <b>&darr;</b>");
			echo "</td>";
			
			echo "<td class='tdheader'>";
			if(isset($_SESSION[COOKIENAME.'sortTables']))
				$orderTag = ($_SESSION[COOKIENAME.'sortTables']=="name" && $_SESSION[COOKIENAME.'orderTables']=="ASC") ? "DESC" : "ASC";
			else
				$orderTag = "ASC";
			echo $params->getLink(array('sort'=>'name', 'order'=>$orderTag), $lang['name']);
			if(isset($_SESSION[COOKIENAME.'sortTables']) && $_SESSION[COOKIENAME.'sortTables']=="name")
				echo (($_SESSION[COOKIENAME.'orderTables']=="ASC") ? " <b>&uarr;</b>" : " <b>&darr;</b>");
			echo "</td>";
			
			echo "<td class='tdheader' colspan='10'>".$lang['act']."</td>";
			echo "<td class='tdheader'>".$lang['rec']."</td>";
			echo "</tr>";
			
			$totalRecords = 0;
			$skippedTables = false;
			for($i=0; $i<sizeof($result); $i++)
			{
				$records = $db->numRows($result[$i]['name'], (!isset($_GET['forceCount'])));
				if($records == '?')
				{
					$skippedTables = true;
					$records = $params->getLink(array('forceCount'=>'1'), '?');
				}
				else
					$totalRecords += $records;
				$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
				$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
				
				echo "<tr>";
				echo $tdWithClassLeft;
				echo ($result[$i]['type']=="table"? $lang['tbl'] : $lang['view']);
				echo "</td>";
				echo $tdWithClassLeft;
				echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'row_view'), htmlencode($result[$i]['name']));
				echo "</td>";
				echo $tdWithClass;
				echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'row_view'), $lang['browse']);
				echo "</td>";
				echo $tdWithClass;
				echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'column_view'), $lang['struct']);
				echo "</td>";
				echo $tdWithClass;
				echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'table_sql'), $lang['sql']);
				echo "</td>";
				echo $tdWithClass;
				echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'table_search'), $lang['srch']);
				echo "</td>";
				echo $tdWithClass;
				if($result[$i]['type']=="table")
					echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'row_create'), $lang['insert']);
				echo "</td>";
				echo $tdWithClass;
				echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'table_export'), $lang['export']);
				echo "</td>";
				echo $tdWithClass;
				if($result[$i]['type']=="table")
					echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'table_import'), $lang['import']);
				echo "</td>";
				echo $tdWithClass;
				echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'table_rename'), $lang['rename']);
				echo "</td>";
				echo $tdWithClass;
				if($result[$i]['type']=="table")
					echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'table_empty'), $lang['empty'], 'empty');
				echo "</td>";
				echo $tdWithClass;
				echo $params->getLink(array('table'=>$result[$i]['name'], 'action'=>'table_drop'), $lang['drop'], 'drop');
				echo "</td>";
				echo $tdWithClass;
				echo $records;
				echo "</td>";
				echo "</tr>";
			}
			echo "<tr>";
			echo "<td class='tdheader' colspan='12'>".sizeof($result)." ".$lang['total']."</td>";
			echo "<td class='tdheader' colspan='1' style='text-align:right;'>".$totalRecords.($skippedTables?" ".$params->getLink(array('forceCount'=>'1'),'+ ?'):"")."</td>";
			echo "</tr>";
			echo "</table>";
			echo "<br/>";
			if($skippedTables)
				echo "<div class='confirm' style='margin-bottom:20px;'>".sprintf($lang["counting_skipped"],"<a href='".$params->getURL(array('forceCount'=>'1'))."'>","</a>")."</div>";
		}
		echo "<fieldset>";
		echo "<legend><b>".$lang['create_tbl_db']." '".htmlencode($db->getName())."'</b></legend>";
		echo $params->getForm(array('action'=>'table_create'), 'get');
		echo $lang['name'].": <input type='text' name='tablename' style='width:200px;'/> ";
		echo $lang['fld_num'].": <input type='text' name='tablefields' style='width:90px;'/> ";
		echo "<input type='submit' name='createtable' value='".$lang['go']."' class='btn'/>";
		echo "</form>";
		echo "</fieldset>";
		echo "<br/>";
		echo "<fieldset>";
		echo "<legend><b>".$lang['create_view']." '".htmlencode($db->getName())."'</b></legend>";
		echo $params->getForm(array('action'=>'view_create', 'confirm'=>'1'));
		echo $lang['name'].": <input type='text' name='viewname' style='width:200px;'/> ";
		echo $lang['sel_state']." ".helpLink($lang['help4']).": <input type='text' name='select' style='width:400px;'/> "; 
		echo "<input type='submit' name='createtable' value='".$lang['go']."' class='btn'/>";
		echo "</form>";
		echo "</fieldset>";
	}
	else if($view=="sql")
	{
		//- Database SQL editor (=sql)
		if(isset($_POST['query']) && $_POST['query']!="")
		{
			$delimiter = $_POST['delimiter'];
			$queryStr = $_POST['queryval'];
			//save the queries in history if necessary
			if($maxSavedQueries!=0 && $maxSavedQueries!=false)
			{
				if(!isset($_SESSION[COOKIENAME.'query_history']))
					$_SESSION[COOKIENAME.'query_history'] = array();
				$_SESSION[COOKIENAME.'query_history'][md5(strtolower($queryStr))] = $queryStr;
				if(sizeof($_SESSION[COOKIENAME.'query_history']) > $maxSavedQueries)
					array_shift($_SESSION[COOKIENAME.'query_history']);
			}
			$query = explode_sql($delimiter, $queryStr); //explode the query string into individual queries based on the delimiter

			for($i=0; $i<sizeof($query); $i++) //iterate through the queries exploded by the delimiter
			{
				if(str_replace(" ", "", str_replace("\n", "", str_replace("\r", "", $query[$i])))!="") //make sure this query is not an empty string
				{
					$queryTimer = new MicroTimer();
					$table_result = $db->query($query[$i]);

					echo "<div class='confirm'>";
					echo "<b>".htmlencode($query[$i])."</b>";
					if($table_result === NULL || $table_result === false)
					{
						echo "<br /><b>".$lang['err'].": ".htmlencode($db->getError())."</b></div>";
					}
					echo "</div><br/>";
					if($row = $db->fetch($table_result, 'assoc'))
					{
						$headers = array_keys($row);
						echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
						echo "<tr>";
						for($j=0; $j<sizeof($headers); $j++)
						{
							echo "<td class='tdheader'>";
							echo htmlencode($headers[$j]);
							echo "</td>";
						}
						echo "</tr>";
						$rowCount = 0;
						for(; $rowCount==0 || $row = $db->fetch($table_result, 'assoc'); $rowCount++)
						{
							$tdWithClass = "<td class='td".($rowCount%2 ? "1" : "2")."'>";
							echo "<tr>";
							for($z=0; $z<sizeof($headers); $z++)
							{
								echo $tdWithClass;
								if($row[$headers[$z]]==="")
									echo "&nbsp;";
								elseif($row[$headers[$z]]===NULL)
									echo "<i class='null'>NULL</i>";
								else
									echo htmlencode(subString($row[$headers[$z]]));
								echo "</td>";
							}
							echo "</tr>";
						}
						$queryTimer->stop();
						echo "</table><br/><br/>";
						
					
						if($table_result !== NULL && $table_result !== false)
						{
							echo "<div class='confirm' style='margin-bottom: 2em'>";
							if($rowCount>0 || $db->getAffectedRows()==0)
							{
								printf($lang['show_rows'], $rowCount);
							}
							if($db->getAffectedRows()>0 || $rowCount==0)
							{
								echo $db->getAffectedRows()." ".$lang['rows_aff']." ";
							}
							printf($lang['query_time'], $queryTimer);
							echo "</div>";
						}

						
					}
				}
			}
		}
		else
		{
			$delimiter = ";";
			$queryStr = "";
		}

		echo "<fieldset>";
		echo "<legend><b>".sprintf($lang['run_sql'],htmlencode($db->getName()))."</b></legend>";
		echo $params->getForm(array('view'=>'sql'));
		if(isset($_SESSION[COOKIENAME.'query_history']) && sizeof($_SESSION[COOKIENAME.'query_history'])>0)
		{
			echo "<b>".$lang['recent_queries']."</b><ul>";
			foreach($_SESSION[COOKIENAME.'query_history'] as $key => $value)
			{
				echo "<li><a onclick='document.getElementById(\"queryval\").value = this.textContent; return false;' href='#'>".htmlencode($value)."</a></li>";
			}
			echo "</ul><br/><br/>";
		}
		echo "<textarea style='width:100%; height:300px;' name='queryval' id='queryval' cols='50' rows='8'>".htmlencode($queryStr)."</textarea>";
		echo $lang['delimit']." <input type='text' name='delimiter' value='".htmlencode($delimiter)."' style='width:50px;'/> ";
		echo "<input type='submit' name='query' value='".$lang['go']."' class='btn'/>";
		echo "</form>";
		echo "</fieldset>";
	}
	else if($view=="vacuum")
	{
		//- Vacuum database confirmation (=vacuum)
		if(isset($_POST['vacuum']))
		{
			$query = "VACUUM";
			$db->query($query);
			echo "<div class='confirm'>";
			printf($lang['db_vac'], htmlencode($db->getName()));
			echo "</div><br/>";
		}
		echo $params->getForm(array('view'=>'vacuum'));
		printf($lang['vac_desc'],htmlencode($db->getName()));
		echo "<br/><br/>";
		echo "<input type='submit' value='".$lang['vac']."' name='vacuum' class='btn'/>";
		echo "</form>";
	}
	else if($view=="export")
	{
		//- Export view (=export)
		echo $params->getForm(array('view'=>'export'));
		echo "<fieldset style='float:left; width:260px; margin-right:20px;'><legend><b>".$lang['export']."</b></legend>";
		echo "<select multiple='multiple' size='10' style='width:240px;' name='tables[]'>";
		$query = "SELECT name FROM sqlite_master WHERE type='table' OR type='view' ORDER BY name";
		$result = $db->selectArray($query);
		for($i=0; $i<sizeof($result); $i++)
		{
			if(substr($result[$i]['name'], 0, 7)!="sqlite_" && $result[$i]['name']!="")
				echo "<option value='".htmlencode($result[$i]['name'])."' selected='selected'>".htmlencode($result[$i]['name'])."</option>";
		}
		echo "</select>";
		echo "<br/><br/>";
		echo "<label><input type='radio' name='export_type' checked='checked' value='sql' onclick='toggleExports(\"sql\");'/> ".$lang['sql']."</label>";
		echo "<br/><label><input type='radio' name='export_type' value='csv' onclick='toggleExports(\"csv\");'/> ".$lang['csv']."</label>";
		echo "</fieldset>";
		
		echo "<fieldset style='float:left; max-width:350px;' id='exportoptions_sql'><legend><b>".$lang['options']."</b></legend>";
		echo "<label><input type='checkbox' checked='checked' name='structure'/> ".$lang['export_struct']."</label> ".helpLink($lang['help5'])."<br/>"; 
		echo "<label><input type='checkbox' checked='checked' name='data'/> ".$lang['export_data']."</label> ".helpLink($lang['help6'])."<br/>";
		echo "<label><input type='checkbox' name='drop'/> ".$lang['add_drop']."</label> ".helpLink($lang['help7'])."<br/>"; 
		echo "<label><input type='checkbox' checked='checked' name='transaction'/> ".$lang['add_transact']."</label> ".helpLink($lang['help8'])."<br/>";
		echo "<label><input type='checkbox' checked='checked' name='comments'/> ".$lang['comments']."</label> ".helpLink($lang['help9'])."<br/>"; 
		echo "</fieldset>";
		
		echo "<fieldset style='float:left; max-width:350px; display:none;' id='exportoptions_csv'><legend><b>".$lang['options']."</b></legend>";
		echo "<div style='float:left;'>".$lang['fld_terminated']."</div>";
		echo "<input type='text' value=';' name='export_csv_fieldsterminated' style='float:right;'/>";
		echo "<div style='clear:both;'>";
		echo "<div style='float:left;'>".$lang['fld_enclosed']."</div>";
		echo "<input type='text' value='\"' name='export_csv_fieldsenclosed' style='float:right;'/>";
		echo "<div style='clear:both;'>";
		echo "<div style='float:left;'>".$lang['fld_escaped']."</div>";
		echo "<input type='text' value='\' name='export_csv_fieldsescaped' style='float:right;'/>";
		echo "<div style='clear:both;'>";
		echo "<div style='float:left;'>".$lang['rep_null']."</div>";
		echo "<input type='text' value='NULL' name='export_csv_replacenull' style='float:right;'/>";
		echo "<div style='clear:both;'>";
		echo "<label><input type='checkbox' name='export_csv_crlf'/> ".$lang['rem_crlf']."</label><br/>";
		echo "<label><input type='checkbox' checked='checked' name='export_csv_fieldnames'/> ".$lang['put_fld']."</label>";
		echo "</fieldset>";
		
		echo "<div style='clear:both;'></div>";
		echo "<br/><br/>";
		echo "<fieldset><legend><b>".$lang['save_as']."</b></legend>";
		$file = pathinfo($db->getPath());
		$name = $file['filename'];
		echo "<input type='text' name='filename' value='".htmlencode($name)."_".date("Y-m-d").".dump' style='width:400px;'/> <input type='submit' name='export' value='".$lang['export']."' class='btn'/>";
		echo "</fieldset>";
		echo "</form>";
		echo "<div class='confirm' style='margin-top: 2em'>".sprintf($lang['backup_hint'], 
			$params->getLink(array('download'=>$currentDB['path'], 'token'=>$_SESSION[COOKIENAME.'token']), $lang["backup_hint_linktext"], '', $lang['backup'])
			)."</div>";
	}
	else if($view=="import")
	{
		//- Import view (=import)
		if(isset($_POST['import']))
		{
			echo "<div class='confirm'>";
			if($importSuccess===true)
				echo $lang['import_suc'];
			else
				echo $importSuccess;
			echo "</div><br/>";
		}
		
		echo $params->getForm(array('view'=>'import'), 'post', true);
		echo "<fieldset style='float:left; width:260px; margin-right:20px;'><legend><b>".$lang['import']."</b></legend>";
		echo "<label><input type='radio' name='import_type' checked='checked' value='sql' onclick='toggleImports(\"sql\");'/> ".$lang['sql']."</label>";
		echo "<br/><label><input type='radio' name='import_type' value='csv' onclick='toggleImports(\"csv\");'/> ".$lang['csv']."</label>";
		echo "</fieldset>";
		
		echo "<fieldset style='float:left; max-width:350px;' id='importoptions_sql'><legend><b>".$lang['options']."</b></legend>";
		echo $lang['no_opt'];
		echo "</fieldset>";
		
		echo "<fieldset style='float:left; max-width:350px; display:none;' id='importoptions_csv'><legend><b>".$lang['options']."</b></legend>";
		echo "<div style='float:left;'>".$lang['csv_tbl']."</div>";
		echo "<select name='single_table' style='float:right;'>";
		$query = "SELECT name FROM sqlite_master WHERE type='table' OR type='view' ORDER BY name";
		$result = $db->selectArray($query);
		for($i=0; $i<sizeof($result); $i++)
		{
			if(substr($result[$i]['name'], 0, 7)!="sqlite_" && $result[$i]['name']!="")
				echo "<option value='".htmlencode($result[$i]['name'])."'>".htmlencode($result[$i]['name'])."</option>";
		}
		echo "</select>";
		echo "<div style='clear:both;'>";
		echo "<div style='float:left;'>".$lang['fld_terminated']."</div>";
		echo "<input type='text' value=';' name='import_csv_fieldsterminated' style='float:right;'/>";
		echo "<div style='clear:both;'>";
		echo "<div style='float:left;'>".$lang['fld_enclosed']."</div>";
		echo "<input type='text' value='\"' name='import_csv_fieldsenclosed' style='float:right;'/>";
		echo "<div style='clear:both;'>";
		echo "<div style='float:left;'>".$lang['fld_escaped']."</div>";
		echo "<input type='text' value='\' name='import_csv_fieldsescaped' style='float:right;'/>";
		echo "<div style='clear:both;'>";
		echo "<div style='float:left;'>".$lang['null_represent']."</div>";
		echo "<input type='text' value='NULL' name='import_csv_replacenull' style='float:right;'/>";
		echo "<div style='clear:both;'>";
		echo "<label><input type='checkbox' checked='checked' name='import_csv_fieldnames'/> ".$lang['fld_names']."</label>";
		echo "</fieldset>";
		
		echo "<div style='clear:both;'></div>";
		echo "<br/><br/>";
		
		echo "<fieldset><legend><b>".$lang['import_f']."</b></legend>";
		echo "<input type='file' value='".$lang['choose_f']."' name='file' style='background-color:transparent; border-style:none;'/> <input type='submit' value='".$lang['import']."' name='import' class='btn'/>";
		echo "</fieldset>";
	}
	else if($view=="rename")
	{
		//- Rename database confirmation (=rename)
		echo $params->getForm(array('view'=>'rename', 'database_rename'=>'1'));
		echo "<input type='hidden' name='oldname' value='".htmlencode($db->getPath())."'/>";
		echo $lang['db_rename']." '".htmlencode($db->getPath())."' ".$lang['to']." <input type='text' name='newname' style='width:200px;' value='".htmlencode($db->getPath())."'/> <input type='submit' value='".$lang['rename']."' name='rename' class='btn'/>";
		echo "</form>";	
	}
	else if($view=="delete")
	{
		//- Delete database confirmation (=delete)
		echo $params->getForm(array('database_delete'=>'1'));
		echo "<div class='confirm'>";
		echo sprintf($lang['ques_del_db'],htmlencode($db->getPath()))."<br/><br/>";
		echo "<input name='database_delete' value='".htmlencode($db->getPath())."' type='hidden'/>";
		echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
		echo $params->getLink(array(), $lang['cancel']);
		echo "</div>";
		echo "</form>";	
	}

	echo "</div>";
}
echo "</div>";

//- HTML: page footer
echo "<br/>";
echo "<span style='font-size:11px;'>".$lang['powered']." <a href='".PROJECT_URL."' target='_blank' style='font-size:11px;'>".PROJECT."</a> | ";
echo $lang['free_software']." <a href='".DONATE_URL."' target='_blank' style='font-size:11px;'>".$lang['please_donate']."</a> | ";
printf($lang['page_gen'], $pageTimer);
echo "</span>";
echo "</td></tr></table>";
$db->close(); //close the database
echo "</body>";
echo "</html>";

//- End of main code
