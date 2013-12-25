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

// load optional configuration file
$config_filename = './phpliteadmin.config.php';
if (is_readable($config_filename)) {
	include_once $config_filename;
}

//constants 1
define("PROJECT", "phpLiteAdmin");
define("VERSION", "1.9.5");
define("PAGE", basename(__FILE__));
define("FORCETYPE", false); //force the extension that will be used (set to false in almost all circumstances except debugging)
define("SYSTEMPASSWORD", $password); // Makes things easier.
define('PROJECT_URL','http://phpliteadmin.googlecode.com');
define('PROJECT_BUGTRACKER_LINK','<a href="http://code.google.com/p/phpliteadmin/issues/list" target="_blank">http://code.google.com/p/phpliteadmin/issues/list</a>');

// Resource output (css and javascript files)
// we get out of the main code as soon as possible, without inizializing the session
if (isset($_GET['resource'])) {
	Resources::output($_GET['resource']);
	exit();
}

// don't mess with this - required for the login session
ini_set('session.cookie_httponly', '1');
session_start();

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
	if(is_file('languages/lang_'.$language.'.php'))
		include('languages/lang_'.$language.'.php');
	elseif(is_file('lang_'.$language.'.php'))
		include('lang_'.$language.'.php');
}
// version-number added so after updating, old session-data is not used anylonger
// cookies names cannot contain symbols, except underscores
define("COOKIENAME", preg_replace('/[^a-zA-Z0-9_]/', '_', $cookie_name . '_' . VERSION) );

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
	$path = '';
	$stack[] = $dir;
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
	global $charsNum;
	if($charsNum > 10){
		if(strlen($str)>$charsNum) $str = substr($str, 0, $charsNum).'...';
	}
	return $str;
}

function getRowId($table, $where=''){
	global $db;
	$query = "SELECT ROWID FROM ".$db->quote_id($table).$where;
	$result = $db->selectArray($query);
	return $result;
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


$auth = new Authorization(); //create authorization object

// check if user has attempted to log out
if (isset($_POST['logout']))
	$auth->revoke();
// check if user has attempted to log in
else if (isset($_POST['login']) && isset($_POST['password']))
	$auth->attemptGrant($_POST['password'], isset($_POST['remember']));

if ($auth->isAuthorized())
{

	//user is creating a new Database
	if(isset($_POST['new_dbname']))
	{
		if($_POST['new_dbname']=='')
		{
			// TODO: Display an error message (do NOT echo here. echo below in the html-body!)
		}
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
				$td = new Database($tdata);
				$td->query("VACUUM");
			} else
			{
				if(is_file($dbname) || is_dir($dbname)) $dbexists = true;
				else $extension_not_allowed=true;
			}
		}
	}
	
	//if the user wants to scan a directory for databases, do so
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
						$_SESSION[COOKIENAME.'currentDB'] = $database;
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
				continue; //skip if file not found ! - probably a warning can be displayed - later
			$databases[$i]['writable'] = is_writable($databases[$i]['path']);
			$databases[$i]['writable_dir'] = is_writable(dirname($databases[$i]['path']));
			$databases[$i]['readable'] = is_readable($databases[$i]['path']);
		}
		sort($databases);
	}
	// we now have the $databases array set. Check whethet currentDB is a managed Db (is in this array)
	if(isset($_SESSION[COOKIENAME.'currentDB']) && isManagedDB($_SESSION[COOKIENAME.'currentDB']['path']) === false)
		unset($_SESSION[COOKIENAME.'currentDB']);
	
	//user is deleting a database
	if(isset($_GET['database_delete']))
	{
		$dbpath = $_POST['database_delete'];
		// check whether $dbpath really is a db we manage
		$checkDB = isManagedDB($dbpath);
		if($checkDB !== false)
		{
			unlink($dbpath);
			unset($_SESSION[COOKIENAME.'currentDB']);
			unset($databases[$checkDB]);
		} else die($lang['err'].': '.$lang['delete_only_managed']);
	}
	
	//user is renaming a database
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
			else die($lang['err'].': '.$lang['db_moved_outside']);
		}
		
		if(checkDbName($newpath))
		{
			$checkDB = isManagedDB($oldpath);
			if($checkDB !==false )
			{
				rename($oldpath, $newpath);
				$databases[$checkDB]['path'] = $newpath;
				$databases[$checkDB]['name'] = basename($newpath);
				$_SESSION[COOKIENAME.'currentDB'] = $databases[$checkDB]; 
				$justrenamed = true;
			}
			else die($lang['err'].': '.$lang['rename_only_managed']);
		}
		else
		{
			if(is_file($newpath) || is_dir($newpath)) $dbexists = true;
			else $extension_not_allowed = true;	
		}
	}

	
	//user is downloading the exported database file
	if(isset($_POST['export']))
	{
		if($_POST['export_type']=="sql")
		{
			header('Content-Type: text/sql');
			header('Content-Disposition: attachment; filename="'.$_POST['filename'].'.'.$_POST['export_type'].'";');
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
			$db = new Database($_SESSION[COOKIENAME.'currentDB']);
			echo $db->export_sql($tables, $drop, $structure, $data, $transaction, $comments);
		}
		else if($_POST['export_type']=="csv")
		{
			header("Content-type: application/csv");
			header('Content-Disposition: attachment; filename="'.$_POST['filename'].'.'.$_POST['export_type'].'";');
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
			$db = new Database($_SESSION[COOKIENAME.'currentDB']);
			echo $db->export_csv($tables, $field_terminate, $field_enclosed, $field_escaped, $null, $crlf, $fields_in_first_row);
		}
		exit();
	}
	
	//user is importing a file
	if(isset($_POST['import']))
	{
		$db = new Database($_SESSION[COOKIENAME.'currentDB']);
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
}

header('Content-Type: text/html; charset=utf-8');

// here begins the HTML.
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<!-- Copyright <?php echo date("Y").' '.PROJECT.' ('.PROJECT_URL.')'; ?> -->
<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
<link rel="shortcut icon" href="?resource=favicon" />
<title><?php echo PROJECT ?></title>

<?php
if(isset($_GET['theme'])) $theme = basename($_GET['theme']);

// allow themes to be dropped in subfolder "themes"
if(is_file('themes/'.$theme)) $theme = 'themes/'.$theme;

if (file_exists($theme))
	// an external stylesheet exists - import it
	echo "<link href='{$theme}' rel='stylesheet' type='text/css' />", PHP_EOL;
else
	// only use the default stylesheet if an external one does not exist
	echo "<link href='?resource=css' rel='stylesheet' type='text/css' />", PHP_EOL;

if(isset($_GET['help'])) //this page is used as the popup help section
{
	//help section array
	$help = array
	(
		$lang['help1'] => sprintf($lang['help1_x'], PROJECT, PROJECT, PROJECT), $lang['help2'] => $lang['help2_x'], $lang['help3'] => $lang['help3_x'], 
		$lang['help4'] => $lang['help4_x'], $lang['help5'] => $lang['help5_x'], $lang['help6'] => $lang['help6_x'],
		$lang['help7'] => $lang['help7_x'], $lang['help8'] => $lang['help8_x'], $lang['help9'] => $lang['help9_x']
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

if(!$auth->isAuthorized()) //user is not authorized - display the login screen
{
	echo "<div id='loginBox'>";
	echo "<h1><span id='logo'>".PROJECT."</span> <span id='version'>v".VERSION."</span></h1>";
	echo "<div style='padding:15px; text-align:center;'>";
	if ($auth->isFailedLogin())
		echo "<span class='warning'>".$lang['passwd_incorrect']."</span><br/><br/>";
	echo "<form action='".PAGE."' method='post'>";
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
}
else //user is authorized - display the main application
{
	if(!isset($_SESSION[COOKIENAME.'currentDB']) && count($databases)>0)
	{
		//set the current database to the first existing one in the array (default)
		$_SESSION[COOKIENAME.'currentDB'] = reset($databases);
	}
	if(sizeof($databases)>0)
		$currentDB = $_SESSION[COOKIENAME.'currentDB'];
	else //the database array is empty - show error and halt execution
	{
		if($directory!==false && is_writable($directory))
		{
			echo "<div class='confirm' style='margin:20px;'>";
			printf($lang['no_db'], PROJECT, PROJECT);
			echo "</div>";	
			if(isset($extension_not_allowed))
			{
				echo "<div class='confirm' style='margin:10px 20px;'>";
				echo $lang['err'].': '.$lang['extension_not_allowed'].': ';
				echo implode(', ', array_map('htmlencode', $allowed_extensions));
				echo '<br />'.$lang['add_allowed_extension'];
				echo "</div><br/>";
			}			
			echo "<fieldset style='margin:15px;'><legend><b>".$lang['db_create']."</b></legend>";
			echo "<form name='create_database' method='post' action='".PAGE."'>";
			echo "<input type='text' name='new_dbname' style='width:150px;'/> <input type='submit' value='".$lang['create']."' class='btn'/>";
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

	if(isset($_POST['database_switch'])) //user is switching database with drop-down menu
	{
		foreach($databases as $db_id => $database)
		{
			if($database['path'] == $_POST['database_switch'])
			{
				$_SESSION[COOKIENAME."currentDB"] = $database;
				break;
			}
		}
		$currentDB = $_SESSION[COOKIENAME.'currentDB'];
	}
	else if(isset($_GET['switchdb']))
	{
		foreach($databases as $db_id => $database)
		{
			if($database['path'] == $_GET['switchdb'])
			{
				$_SESSION[COOKIENAME."currentDB"] = $database;
				break;
			}
		}
		$currentDB = $_SESSION[COOKIENAME.'currentDB'];
	}
	if(isset($_SESSION[COOKIENAME.'currentDB']) && in_array($_SESSION[COOKIENAME.'currentDB'], $databases))
		$currentDB = $_SESSION[COOKIENAME.'currentDB'];

	//create the objects
	$db = new Database($currentDB); //create the Database object
	$db->registerUserFunction($custom_functions);

	//switch board for various operations a user could have requested - these actions are invisible and produce no output
	if(isset($_GET['action']) && isset($_GET['confirm']))
	{
		switch($_GET['action'])
		{
			//table actions
			/////////////////////////////////////////////// create table
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
							if($_POST[$i.'_type']=="INTEGER" && is_numeric($_POST[$i.'_defaultvalue']))
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
				if($result===false)
					$error = true;
				$completed = $lang['tbl']." '".htmlencode($_POST['tablename'])."' ".$lang['created'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				break;
			/////////////////////////////////////////////// empty table
			case "table_empty":
				$query = "DELETE FROM ".$db->quote_id($_POST['tablename']);
				$result = $db->query($query);
				if($result===false)
					$error = true;
				$query = "VACUUM";
				$result = $db->query($query);
				if($result===false)
					$error = true;
				$completed = $lang['tbl']." '".htmlencode($_POST['tablename'])."' ".$lang['emptied'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				break;
			/////////////////////////////////////////////// create view
			case "view_create":
				$query = "CREATE VIEW ".$db->quote($_POST['viewname'])." AS ".$_POST['select'];
				$result = $db->query($query);
				if($result===false)
					$error = true;
				$completed = $lang['view']." '".htmlencode($_POST['viewname'])."' ".$lang['created'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				break;
			/////////////////////////////////////////////// drop table
			case "table_drop":
				$query = "DROP TABLE ".$db->quote_id($_POST['tablename']);
				$result=$db->query($query);
				if($result===false)
					$error = true;
				$completed = $lang['tbl']." '".htmlencode($_POST['tablename'])."' ".$lang['dropped'].".";
				break;
			/////////////////////////////////////////////// drop view
			case "view_drop":
				$query = "DROP VIEW ".$db->quote_id($_POST['viewname']);
				$result=$db->query($query);
				if($result===false)
					$error = true;
				$completed = $lang['view']." '".htmlencode($_POST['viewname'])."' ".$lang['dropped'].".";
				break;
			/////////////////////////////////////////////// rename table
			case "table_rename":
				$query = "ALTER TABLE ".$db->quote_id($_POST['oldname'])." RENAME TO ".$db->quote($_POST['newname']);
				if($db->getVersion()==3)
					$result = $db->query($query, true);
				else
					$result = $db->query($query, false);
				if($result===false)
					$error = true;
				$completed = $lang['tbl']." '".htmlencode($_POST['oldname'])."' ".$lang['renamed']." '".htmlencode($_POST['newname'])."'.<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				break;
			//row actions
			/////////////////////////////////////////////// create row
			case "row_create":
				$completed = "";
				$num = $_POST['numRows'];
				$fields = explode(":", $_POST['fields']);
				$z = 0;
				
				$query = "PRAGMA table_info(".$db->quote_id($_GET['table']).")";
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
							// PHP replaces space with underscore
							$fields[$j] = str_replace(" ","_",$fields[$j]);
							
							$null = isset($_POST[$i.":".$fields[$j]."_null"]);
							if(!$null)
							{
								if(!isset($_POST[$i.":".$fields[$j]]) && $debug)
								{
									echo "MISSING POST INDEX (".$i.":".$fields[$j].")<br><pre />";
									var_dump($_POST);
									echo "</pre><hr />";
								} 
								$value = $_POST[$i.":".$fields[$j]];
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
							$function = $_POST["function_".$i."_".$fields[$j]];
							if($function!="")
								$query_vals .= $function."(";
							if(($type=="TEXT" || $type=="BLOB") && !$null)
								$query_vals .= $db->quote($value);
							elseif(($type=="INTEGER" || $type=="REAL") && $value=="")
								$query_vals .= "NULL";
							elseif($null)
								$query_vals .= "NULL";
							else
								$query_vals .= $db->quote($value);
							if($function!="")
								$query_vals .= ")";
							$query_vals .= ",";
						}
						$query = "INSERT INTO ".$db->quote_id($_GET['table']);
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
				$completed = $z." ".$lang['rows']." ".$lang['inserted'].".<br/><br/>".$completed;
				break;
			/////////////////////////////////////////////// delete row
			case "row_delete":
				$pks = explode(":", $_GET['pk']);
				$query = "DELETE FROM ".$db->quote_id($_GET['table'])." WHERE ROWID = ".$pks[0];
				for($i=1; $i<sizeof($pks); $i++)
				{
					$query .= " OR ROWID = ".$pks[$i];
				}
				$result = $db->query($query);
				if($result===false)
					$error = true;
				$completed = sizeof($pks)." ".$lang['rows']." ".$lang['deleted'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				break;
			/////////////////////////////////////////////// edit row
			case "row_edit":
				$pks = explode(":", $_GET['pk']);
				$fields = explode(":", $_POST['fieldArray']);
				
				$z = 0;
				
				$query = "PRAGMA table_info(".$db->quote_id($_GET['table']).")";
				$result = $db->selectArray($query);
				
				if(isset($_POST['new_row']))
					$completed = "";
				else
					$completed = sizeof($pks)." ".$lang['rows']." ".$lang['affected'].".<br/><br/>";

				for($i=0; $i<sizeof($pks); $i++)
				{
					if(isset($_POST['new_row']))
					{
						$query = "INSERT INTO ".$db->quote_id($_GET['table'])." (";
						for($j=0; $j<sizeof($fields); $j++)
						{
							$query .= $db->quote_id($fields[$j]).",";
						}
						$query = substr($query, 0, sizeof($query)-2);
						$query .= ") VALUES (";
						for($j=0; $j<sizeof($fields); $j++)
						{
							$field_index = str_replace(" ","_",$fields[$j]);
							$value = $_POST[$pks[$i].":".$field_index];
							$null = isset($_POST[$pks[$i].":".$field_index."_null"]);
							$type = $result[$j][2];
							$function = $_POST["function_".$pks[$i]."_".$field_index];
							if($function!="")
								$query .= $function."(";
								//di - messed around with this logic for null values
							if(($type=="TEXT" || $type=="BLOB") && $null==false)
								$query .= $db->quote($value);
							else if(($type=="INTEGER" || $type=="REAL") && $null==false && $value=="")
								$query .= "NULL";
							else if($null==true)
								$query .= "NULL";
							else
								$query .= $db->quote($value);
							if($function!="")
								$query .= ")";
							$query .= ",";
						}
						$query = substr($query, 0, sizeof($query)-2);
						$query .= ")";
						$result1 = $db->query($query);
						if($result1===false)
							$error = true;
						$z++;
					}
					else
					{
						$query = "UPDATE ".$db->quote_id($_GET['table'])." SET ";
						for($j=0; $j<sizeof($fields); $j++)
						{
							if(!is_numeric($pks[$i])) continue;
							$field_index = str_replace(" ","_",$fields[$j]);
							$function = $_POST["function_".$pks[$i]."_".$field_index];
							$null = isset($_POST[$pks[$i].":".$field_index."_null"]);
							$query .= $db->quote_id($fields[$j])."=";
							if($function!="")
								$query .= $function."(";
							if($null)
								$query .= "NULL";
							else
								$query .= $db->quote($_POST[$pks[$i].":".$field_index]);
							if($function!="")
								$query .= ")";
							$query .= ", ";
						}
						$query = substr($query, 0, sizeof($query)-3);
						$query .= " WHERE ROWID = ".$pks[$i];
						$result1 = $db->query($query);
						if($result1===false)
						{
							$error = true;
						}
					}
					$completed .= "<span style='font-size:11px;'>".htmlencode($query)."</span><br/>";
				}
				if(isset($_POST['new_row']))
					$completed = $z." ".$lang['rows']." ".$lang['inserted'].".<br/><br/>".$completed;
				break;
			//column actions
			/////////////////////////////////////////////// create column
			case "column_create":
				$num = intval($_POST['rows']);
				for($i=0; $i<$num; $i++)
				{
					if($_POST[$i.'_field']!="")
					{
						$query = "ALTER TABLE ".$db->quote_id($_GET['table'])." ADD ".$db->quote($_POST[$i.'_field'])." ";
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
							if($_POST[$i.'_type']=="INTEGER" && is_numeric($_POST[$i.'_defaultvalue']))
								$query .= "DEFAULT ".$_POST[$i.'_defaultvalue']."  ";
							else
								$query .= "DEFAULT ".$db->quote($_POST[$i.'_defaultvalue'])." ";
						}
						if($db->getVersion()==3 &&
							($_POST[$i.'_defaultoption']=='defined' || $_POST[$i.'_defaultoption']=='none' || $_POST[$i.'_defaultoption']=='NULL'))
							// Sqlite3 cannot add columns with default values that are not constant, so use AlterTable-workaround
							$result = $db->query($query, true);
						else
							$result = $db->query($query, false);
						if($result===false)
							$error = true;
					}
				}
				$completed = $lang['tbl']." '".htmlencode($_GET['table'])."' ".$lang['altered'].".";
				break;
			/////////////////////////////////////////////// delete column
			case "column_delete":
				$pks = explode(":", $_GET['pk']);
				$query = "ALTER TABLE ".$db->quote_id($_GET['table']).' DROP '.$db->quote_id($pks[0]);
				for($i=1; $i<sizeof($pks); $i++)
				{
					$query .= ", DROP ".$db->quote_id($pks[$i]);
				}
				$result = $db->query($query);
				if($result===false)
					$error = true;
				$completed = $lang['tbl']." '".htmlencode($_GET['table'])."' ".$lang['altered'].".";
				break;
			/////////////////////////////////////////////// edit column
			case "column_edit":
				$query = "ALTER TABLE ".$db->quote_id($_GET['table']).' CHANGE '.$db->quote_id($_POST['oldvalue'])." ".$db->quote($_POST['0_field'])." ".$_POST['0_type'];
				$result = $db->query($query);
				if($result===false)
					$error = true;
				$completed = $lang['tbl']." '".htmlencode($_GET['table'])."' ".$lang['altered'].".";
				break;
			/////////////////////////////////////////////// delete trigger
			case "trigger_delete":
				$query = "DROP TRIGGER ".$db->quote_id($_GET['pk']);
				$result = $db->query($query);
				if($result===false)
					$error = true;
				$completed = $lang['trigger']." '".htmlencode($_GET['pk'])."' ".$lang['deleted'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				break;
			/////////////////////////////////////////////// delete index
			case "index_delete":
				$query = "DROP INDEX ".$db->quote_id($_GET['pk']);
				$result = $db->query($query);
				if($result===false)
					$error = true;
				$completed = $lang['index']." '".htmlencode($_GET['pk'])."' ".$lang['deleted'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				break;
			/////////////////////////////////////////////// create trigger
			case "trigger_create":
				$str = "CREATE TRIGGER ".$db->quote($_POST['trigger_name']);
				if($_POST['beforeafter']!="")
					$str .= " ".$_POST['beforeafter'];
				$str .= " ".$_POST['event']." ON ".$db->quote_id($_GET['table']);
				if(isset($_POST['foreachrow']))
					$str .= " FOR EACH ROW";
				if($_POST['whenexpression']!="")
					$str .= " WHEN ".$_POST['whenexpression'];
				$str .= " BEGIN";
				$str .= " ".$_POST['triggersteps'];
				$str .= " END";
				$query = $str;
				$result = $db->query($query);
				if($result===false)
					$error = true;
				$completed = $lang['trigger']." ".$lang['created'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				break;
			/////////////////////////////////////////////// create index
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
					$str .= "INDEX ".$db->quote($_POST['name'])." ON ".$db->quote_id($_GET['table'])." (";
					$str .= $db->quote_id($_POST['0_field']).$_POST['0_order'];
					for($i=1; $i<$num; $i++)
					{
						if($_POST[$i.'_field']!="")
							$str .= ", ".$db->quote_id($_POST[$i.'_field']).$_POST[$i.'_order'];
					}
					$str .= ")";
					$query = $str;
					$result = $db->query($query);
					if($result===false)
						$error = true;
					$completed = $lang['index']." ".$lang['created'].".<br/><span style='font-size:11px;'>".htmlencode($query)."</span>";
				}
				break;
		}
	}

	echo '<table class="body_tbl" width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td valign="top" class="left_td" style="width:100px; padding:9px 2px 9px 9px;">';
	echo "<div id='leftNav'>";
	echo "<h1><a href='".PAGE."'>";
	echo "<span id='logo'>".PROJECT."</span> <span id='version'>v".VERSION."</span>";
	echo "</a></h1>";
	echo "<div id='headerlinks'>";
	echo "<a href='javascript:void' onclick='openHelp(\"top\");'>".$lang['docu']."</a> | ";
	echo "<a href='http://www.gnu.org/licenses/gpl.html' target='_blank'>".$lang['license']."</a> | ";
	echo "<a href='".PROJECT_URL."' target='_blank'>".$lang['proj_site']."</a>";
	echo "</div>";
	echo "<fieldset style='margin:15px;'><legend><b>".$lang['db_ch']."</b></legend>";
	if(sizeof($databases)<10) //if there aren't a lot of databases, just show them as a list of links instead of drop down menu
	{
		$i=0;
		foreach($databases as $database)
		{
			$i++;
			echo '[' . ($database['readable'] ? 'r':' ' ) . ($database['writable'] && $database['writable_dir'] ? 'w':' ' ) . '] ';
			$url_path = str_replace(DIRECTORY_SEPARATOR,'/',$database['path']);
			if($database == $_SESSION[COOKIENAME.'currentDB'])
				echo "<a href='?switchdb=".urlencode($database['path'])."' class='active_db'>".htmlencode($database['name'])."</a>  (<a href='".htmlencode($url_path)."' title='".$lang['backup']."'>&darr;</a>)";
			else
				echo "<a href='?switchdb=".urlencode($database['path'])."'>".htmlencode($database['name'])."</a>  (<a href='".htmlencode($url_path)."' title='".$lang['backup']."'>&darr;</a>)";
			if($i<sizeof($databases))
				echo "<br/>";
		}
	}
	else //there are a lot of databases - show a drop down menu
	{
		echo "<form action='".PAGE."' method='post'>";
		echo "<select name='database_switch'>";
		foreach($databases as $database)
		{
			$perms_string = htmlencode('[' . ($database['readable'] ? 'r':' ' ) . ($database['writable'] && $database['writable_dir'] ? 'w':' ' ) . '] ');
			if($database == $_SESSION[COOKIENAME.'currentDB'])
				echo "<option value='".htmlencode($database['path'])."' selected='selected'>".$perms_string.htmlencode($database['name'])."</option>";
			else
				echo "<option value='".htmlencode($database['path'])."'>".$perms_string.htmlencode($database['name'])."</option>";
		}
		echo "</select> ";
		echo "<input type='submit' value='".$lang['go']."' class='btn'>";
		echo "</form>";
	}
	echo "</fieldset>";
	echo "<fieldset style='margin:15px;'><legend>";
	echo "<a href='".PAGE."'";
	if(!isset($_GET['table']))
		echo " class='active_table'";
	echo ">".htmlencode($currentDB['name'])."</a>";
	echo "</legend>";
	//Display list of tables
	$query = "SELECT type, name FROM sqlite_master WHERE type='table' OR type='view' ORDER BY name";
	$result = $db->selectArray($query);
	$j=0;
	for($i=0; $i<sizeof($result); $i++)
	{
		if(substr($result[$i]['name'], 0, 7)!="sqlite_" && $result[$i]['name']!="")
		{
			echo "<span class='sidebar_table'>[".$lang[$result[$i]['type']=='table'?'tbl':'view']."]</span> ";
			echo "<a href='?action=row_view&amp;table=".urlencode($result[$i]['name']).($result[$i]['type']=='view'?'&amp;view=1':'')."'";
			if(isset($_GET['table']) && $_GET['table']==$result[$i]['name'])
				echo " class='active_table'";
			echo ">".htmlencode($result[$i]['name'])."</a><br/>";
			$j++;
		}
	}
	if($j==0)
		echo $lang['no_tbl'];
	echo "</fieldset>";
	
	if($directory!==false && is_writable($directory))
	{
		echo "<fieldset style='margin:15px;'><legend><b>".$lang['db_create']."</b> ".helpLink($lang['help2'])."</legend>"; 
		echo "<form name='create_database' method='post' action='".PAGE."'>";
		echo "<input type='text' name='new_dbname' style='width:150px;'/> <input type='submit' value='".$lang['create']."' class='btn'/>";
		echo "</form>";
		echo "</fieldset>";
	}
	
	echo "<div style='text-align:center;'>";
	echo "<form action='".PAGE."' method='post'>";
	echo "<input type='submit' value='".$lang['logout']."' name='logout' class='btn'/>";
	echo "</form>";
	echo "</div>";
	echo "</div>";
	echo '</td><td valign="top" id="main_column" class="right_td" style="padding:9px 2px 9px 9px;">';

	//breadcrumb navigation
	echo "<a href='".PAGE."'>".htmlencode($currentDB['name'])."</a>";
	if(isset($_GET['table']))
		echo " &rarr; <a href='?table=".urlencode($_GET['table'])."&amp;action=row_view'>".htmlencode($_GET['table'])."</a>";
	echo "<br/><br/>";

	//user has performed some action so show the resulting message
	if(isset($_GET['confirm']))
	{
		echo "<div id='main'>";
		echo "<div class='confirm'>";
		if(isset($error) && $error) //an error occured during the action, so show an error message
			echo $lang['err'].": ".$db->getError().".<br/>".$lang['bug_report'].' '.PROJECT_BUGTRACKER_LINK;
		else //action was performed successfully - show success message
			echo $completed;
		echo "</div>";
		if($_GET['action']=="row_delete" || $_GET['action']=="row_create" || $_GET['action']=="row_edit")
			echo "<br/><br/><a href='?table=".urlencode($_GET['table'])."&amp;action=row_view'>".$lang['return']."</a>";
		else if($_GET['action']=="column_create" || $_GET['action']=="column_delete" || $_GET['action']=="column_edit" || $_GET['action']=="index_create" || $_GET['action']=="index_delete" || $_GET['action']=="trigger_delete" || $_GET['action']=="trigger_create")
			echo "<br/><br/><a href='?table=".urlencode($_GET['table'])."&amp;action=column_view'>".$lang['return']."</a>";
		else
			echo "<br/><br/><a href='".PAGE."'>".$lang['return']."</a>";
		echo "</div>";
	}

	//show the various tab views for a table
	if(!isset($_GET['confirm']) && isset($_GET['table']) && isset($_GET['action']) && ($_GET['action']=="table_export" || $_GET['action']=="table_import" || $_GET['action']=="table_sql" || $_GET['action']=="row_view" || $_GET['action']=="row_create" || $_GET['action']=="column_view" || $_GET['action']=="table_rename" || $_GET['action']=="table_search" || $_GET['action']=="table_triggers"))
	{
		if(!isset($_GET['view']))
		{
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=row_view' ";
			if($_GET['action']=="row_view")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['browse']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=column_view' ";
			if($_GET['action']=="column_view")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['struct']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_sql' ";
			if($_GET['action']=="table_sql")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['sql']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_search' ";
			if($_GET['action']=="table_search")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['srch']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=row_create' ";
			if($_GET['action']=="row_create")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['insert']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_export' ";
			if($_GET['action']=="table_export")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['export']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_import' ";
			if($_GET['action']=="table_import")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['import']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_rename' ";
			if($_GET['action']=="table_rename")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['rename']."</a>";
			echo "<a href='?action=table_empty&amp;table=".urlencode($_GET['table'])."' ";
			echo "class='tab empty'";
			echo ">".$lang['empty']."</a>";
			echo "<a href='?action=table_drop&amp;table=".urlencode($_GET['table'])."' ";
			echo "class='tab drop'";
			echo ">".$lang['drop']."</a>";
			echo "<div style='clear:both;'></div>";
		}
		else
		{
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=row_view&amp;view=1' ";
			if($_GET['action']=="row_view")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['browse']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=column_view&amp;view=1' ";
			if($_GET['action']=="column_view")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['struct']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_sql&amp;view=1' ";
			if($_GET['action']=="table_sql")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['sql']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_search&amp;view=1' ";
			if($_GET['action']=="table_search")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['srch']."</a>";
			echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_export&amp;view=1' ";
			if($_GET['action']=="table_export")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['export']."</a>";
			echo "<a href='?action=view_drop&amp;table=".urlencode($_GET['table'])."&amp;view=1' ";
			echo "class='tab drop'";
			echo ">".$lang['drop']."</a>";
			echo "<div style='clear:both;'></div>";
		}
	}

	//switch board for the page display
	if(isset($_GET['action']) && !isset($_GET['confirm']))
	{
		echo "<div id='main'>";
		switch($_GET['action'])
		{
			//table actions
			/////////////////////////////////////////////// create table
			case "table_create":
				$query = "SELECT name FROM sqlite_master WHERE type='table' AND name=".$db->quote($_POST['tablename']);
				$results = $db->selectArray($query);
				if(sizeof($results)>0)
					$exists = true;
				else
					$exists = false;
				echo "<h2>".$lang['create_tbl'].": '".htmlencode($_POST['tablename'])."'</h2>";
				if($_POST['tablefields']=="" || intval($_POST['tablefields'])<=0)
					echo $lang['specify_fields'];
				else if($_POST['tablename']=="")
					echo $lang['specify_tbl'];
				else if($exists)
					echo $lang['tbl_exists'];
				else
				{
					$num = intval($_POST['tablefields']);
					$name = $_POST['tablename'];
					echo "<form action='?action=table_create&amp;confirm=1' method='post'>";
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
					echo "<a href='".PAGE."'>".$lang['cancel']."</a>";
					echo "</td>";
					echo "</tr>";
					echo "</table>";
					echo "</form>";
					if($db->getType() != "SQLiteDatabase") echo "<script type='text/javascript'>window.onload=initAutoincrement;</script>";
				}
				break;
			/////////////////////////////////////////////// perform SQL query on table
			case "table_sql":
				$isSelect = false;
				if(isset($_POST['query']) && $_POST['query']!="")
				{
					$delimiter = $_POST['delimiter'];
					$queryStr = $_POST['queryval'];
					$query = explode_sql($delimiter, $queryStr); //explode the query string into individual queries based on the delimiter

					for($i=0; $i<sizeof($query); $i++) //iterate through the queries exploded by the delimiter
					{
						if(str_replace(" ", "", str_replace("\n", "", str_replace("\r", "", $query[$i])))!="") //make sure this query is not an empty string
						{
							$queryTimer = new MicroTimer();
							if(preg_match('/^\s*(?:select|pragma|explain)\s/i', $query[$i])===1)   // pragma and explain often return rows just like select
							{
								$isSelect = true;
								$result = $db->selectArray($query[$i], "assoc");
							}
							else
							{
								$isSelect = false;
								$result = $db->query($query[$i]);
							}
							$queryTimer->stop();

							echo "<div class='confirm'>";
							echo "<b>";
							if($result!==false)
							{
								if($isSelect)
								{
									$affected = sizeof($result);
									echo $lang['showing']." ".$affected." ".$lang['rows'].". ";
								}
								else
								{
									$affected = $db->getAffectedRows();
									echo $affected." ".$lang['rows']." ".$lang['affected'].". ";
								}
								printf($lang['query_time'], $queryTimer);
								echo "</b><br/>";
							}
							else
							{
								echo $lang['err'].": ".$db->getError().".</b><br/>";
							}
							echo "<span style='font-size:11px;'>".htmlencode($query[$i])."</span>";
							echo "</div><br/>";
							if($isSelect)
							{
								if(sizeof($result)>0)
								{
									$headers = array_keys($result[0]);

									echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
									echo "<tr>";
									for($j=0; $j<sizeof($headers); $j++)
									{
										echo "<td class='tdheader'>";
										echo htmlencode($headers[$j]);
										echo "</td>";
									}
									echo "</tr>";
									for($j=0; $j<sizeof($result); $j++)
									{
										$tdWithClass = "<td class='td".($j%2 ? "1" : "2")."'>";
										echo "<tr>";
										for($z=0; $z<sizeof($headers); $z++)
										{
											echo $tdWithClass;
											if($result[$j][$headers[$z]]==="")
												echo "&nbsp;";
											elseif($result[$j][$headers[$z]]===NULL)
												echo "<i class='null'>NULL</i>";
											else
												echo subString(htmlencode($result[$j][$headers[$z]]));
											echo "</td>";
										}
										echo "</tr>";
									}
									echo "</table><br/><br/>";
								}
							}
						}
					}
				}
				else
				{
					$delimiter = ";";
					$queryStr = "SELECT * FROM ".$db->quote_id($_GET['table'])." WHERE 1";
				}

				echo "<fieldset>";
				echo "<legend><b>".sprintf($lang['run_sql'],htmlencode($db->getName()))."</b></legend>";
				if(!isset($_GET['view']))
					echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=table_sql' method='post'>";
				else
					echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=table_sql&amp;view=1' method='post'>";
				echo "<div style='float:left; width:70%;'>";
				echo "<textarea style='width:97%; height:300px;' name='queryval' id='queryval' cols='50' rows='8'>".htmlencode($queryStr)."</textarea>";
				echo "</div>";
				echo "<div style='float:left; width:28%; padding-left:10px;'>";
				echo $lang['fields']."<br/>";
				echo "<select multiple='multiple' style='width:100%;' id='fieldcontainer'>";
				$query = "PRAGMA table_info(".$db->quote_id($_GET['table']).")";
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
			/////////////////////////////////////////////// empty table
			case "table_empty":
				echo "<form action='?action=table_empty&amp;confirm=1' method='post'>";
				echo "<input type='hidden' name='tablename' value='".htmlencode($_GET['table'])."'/>";
				echo "<div class='confirm'>";
				echo sprintf($lang['ques_empty'], htmlencode($_GET['table']))."<br/><br/>";
				echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
				echo "<a href='".PAGE."'>".$lang['cancel']."</a>";
				echo "</div>";
				break;
			/////////////////////////////////////////////// drop table
			case "table_drop":
				echo "<form action='?action=table_drop&amp;confirm=1' method='post'>";
				echo "<input type='hidden' name='tablename' value='".htmlencode($_GET['table'])."'/>";
				echo "<div class='confirm'>";
				echo sprintf($lang['ques_drop'], htmlencode($_GET['table']))."<br/><br/>";
				echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
				echo "<a href='".PAGE."'>".$lang['cancel']."</a>";
				echo "</div>";
				break;
			/////////////////////////////////////////////// drop view
			case "view_drop":
				echo "<form action='?action=view_drop&amp;confirm=1' method='post'>";
				echo "<input type='hidden' name='viewname' value='".htmlencode($_GET['table'])."'/>";
				echo "<div class='confirm'>";
				echo sprintf($lang['ques_drop_view'], htmlencode($_GET['table']))."<br/><br/>";
				echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
				echo "<a href='".PAGE."'>".$lang['cancel']."</a>";
				echo "</div>";
				break;
			/////////////////////////////////////////////// export table
			case "table_export":
				echo "<form method='post' action='".PAGE."'>";
				echo "<fieldset style='float:left; width:260px; margin-right:20px;'><legend><b>".$lang['export']."</b></legend>";
				echo "<input type='hidden' value='".htmlencode($_GET['table'])."' name='single_table'/>";
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
				echo "<input type='text' name='filename' value='".htmlencode($name)."_".htmlencode($_GET['table'])."_".date("Y-m-d").".dump' style='width:400px;'/> <input type='submit' name='export' value='".$lang['export']."' class='btn'/>";
				echo "</fieldset>";
				echo "</form>";
				echo "<div class='confirm' style='margin-top: 2em'>".sprintf($lang['backup_hint'], "<a href='".htmlencode(str_replace(DIRECTORY_SEPARATOR,'/',$currentDB['path']))."' title='".$lang['backup']."'>".$lang["backup_hint_linktext"]."</a>")."</div>";
				break;
			/////////////////////////////////////////////// import table
			case "table_import":
				if(isset($_POST['import']))
				{
					echo "<div class='confirm'>";
					if($importSuccess===true)
						echo $lang['import_suc'];
					else
						echo $lang['err'].': '.$importSuccess;
					echo "</div><br/>";
				}
				echo "<form method='post' action='?table=".urlencode($_GET['table'])."&amp;action=table_import' enctype='multipart/form-data'>";
				echo "<fieldset style='float:left; width:260px; margin-right:20px;'><legend><b>".$lang['import_into']." ".htmlencode($_GET['table'])."</b></legend>";
				echo "<label><input type='radio' name='import_type' checked='checked' value='sql' onclick='toggleImports(\"sql\");'/> ".$lang['sql']."</label>";
				echo "<br/><label><input type='radio' name='import_type' value='csv' onclick='toggleImports(\"csv\");'/> ".$lang['csv']."</label>";
				echo "</fieldset>";
				
				echo "<fieldset style='float:left; max-width:350px;' id='importoptions_sql'><legend><b>".$lang['options']."</b></legend>";
				echo $lang['no_opt'];
				echo "</fieldset>";
				
				echo "<fieldset style='float:left; max-width:350px; display:none;' id='importoptions_csv'><legend><b>".$lang['options']."</b></legend>";
				echo "<input type='hidden' value='".htmlencode($_GET['table'])."' name='single_table'/>";
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
			/////////////////////////////////////////////// rename table
			case "table_rename":
				echo "<form action='?action=table_rename&amp;confirm=1' method='post'>";
				echo "<input type='hidden' name='oldname' value='".htmlencode($_GET['table'])."'/>";
				printf($lang['rename_tbl'], htmlencode($_GET['table']));
				echo " <input type='text' name='newname' style='width:200px;'/> <input type='submit' value='".$lang['rename']."' name='rename' class='btn'/>";
				echo "</form>";
				break;
			/////////////////////////////////////////////// search table
			case "table_search":
				$foundVal = array();
				$fieldArr = array();
				if(isset($_GET['done']))
				{
					$table = $_GET['table'];
					$query = "PRAGMA table_info(".$db->quote_id($table).")";
					$result = $db->selectArray($query);
					$j = 0;
					$arr = array();
					for($i=0; $i<sizeof($result); $i++)
					{
						$field = $result[$i][1];
						$field_index = str_replace(" ","_",$field);
						$operator = $_POST[$field_index.":operator"];
						$value = $_POST[$field_index];
						if($value!="" || $operator=="!= ''" || $operator=="= ''")
						{
							if($operator=="= ''" || $operator=="!= ''")
								$arr[$j] = $db->quote_id($field)." ".$operator;
							
							else{
								if($operator == "LIKE%"){ 
									$operator = "LIKE";
									if(!preg_match('/(^%)|(%$)/', $value)) $value = '%'.$value.'%';
								}
								$fieldArr[] = $field;
								$foundVal[] = $value;
								$arr[$j] = $db->quote_id($field)." ".$operator." ".$db->quote($value);
							}
							$j++;
						}
					}
					$query = "SELECT * FROM ".$db->quote_id($table);
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
					$queryTimer = new MicroTimer();
					$result = $db->selectArray($query,"assoc");
					$queryTimer->stop();

					echo "<div class='confirm'>";
					echo "<b>";
					if($result!==false)
					{
						$affected = sizeof($result);
						echo $lang['showing']." ".$affected." ".$lang['rows'].". ";
						printf($lang['query_time'], $queryTimer);
						echo "</b><br/>";
					}
					else
					{
						echo $lang['err'].": ".$db->getError().".</b><br/>".$lang['bug_report'].' '.PROJECT_BUGTRACKER_LINK.'<br/>';
					}
					echo "<span style='font-size:11px;'>".htmlencode($query)."</span>";
					echo "</div><br/>";

					if(sizeof($result)>0)
					{
						$headers = array_keys($result[0]);

						echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
						echo "<tr>";
						echo "<td>&nbsp;</td><td>&nbsp;</td>"; 
						for($j=0; $j<sizeof($headers); $j++)
						{
							echo "<td class='tdheader'>";
							echo htmlencode($headers[$j]);
							echo "</td>";
						}
						echo "</tr>";

						$pkid = getRowId($table, $whereTo);

						for($j=0; $j<sizeof($result); $j++)
						{
							$pk = $pkid[$j][0];
							$tdWithClass = "<td class='td".($j%2 ? "1" : "2")."'>";
							$cVal = 0;
							echo "<tr>";
							echo $tdWithClass."<a href='?table=".urlencode($table)."&amp;action=row_editordelete&amp;pk=".urlencode($pk)."&amp;type=edit' title='".$lang['edit']."' class='edit'><span>".$lang['edit']."</span></a></td>"; 
							echo $tdWithClass."<a href='?table=".urlencode($table)."&amp;action=row_editordelete&amp;pk=".urlencode($pk)."&amp;type=delete' title='".$lang['del']."' class='delete'><span>".$lang['del']."</span></a></td>";
							for($z=0; $z<sizeof($headers); $z++)
							{
								echo $tdWithClass;
								$fldResult = $result[$j][$headers[$z]];
								if(!empty($foundVal) and in_array($headers[$z], $fieldArr)){
									$foundVal = str_replace('%', '', $foundVal);
									$fldResult = str_ireplace($foundVal[$cVal], '[fnd]'.$foundVal[$cVal].'[/fnd]', $fldResult);
									$cVal++;
								}
								echo str_replace(array('[fnd]', '[/fnd]'), array('<u class="found">', '</u>'), htmlencode($fldResult));
								echo "</td>";
							}
							echo "</tr>";
						}
						echo "</table><br/><br/>";
					}
					
					if(!isset($_GET['view']))
						echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_search'>".$lang['srch_again']."</a>";
					else
						echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=table_search&amp;view=1'>".$lang['srch_again']."</a>";
				}
				else
				{
					$query = "PRAGMA table_info(".$db->quote_id($_GET['table']).")";
					$result = $db->selectArray($query);
					
					if(!isset($_GET['view']))
						echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=table_search&amp;done=1' method='post'>";
					else
						echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=table_search&amp;view=1&amp;done=1' method='post'>";
						
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
					  $type = $result[$i][2];
					  $tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
					  $tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
					  echo "<tr>";
					  echo $tdWithClassLeft;
					  echo $field;
					  echo "</td>";
					  echo $tdWithClassLeft;
					  echo $type;
					  echo "</td>";
					  echo $tdWithClassLeft;
					  echo "<select name='".htmlencode($field).":operator' onchange='checkLike(\"".htmlencode($field)."_search\", this.options[this.selectedIndex].value); '>";
					  echo "<option value='='>=</option>";
					  if($type=="INTEGER" || $type=="REAL")
					  {
						  echo "<option value='&gt;'>&gt;</option>";
						  echo "<option value='&gt;='>&gt;=</option>";
						  echo "<option value='&lt;'>&lt;</option>";
						  echo "<option value='&lt;='>&lt;=</option>";
					  }
					  else if($type=="TEXT" || $type=="BLOB")
					  {
						  echo "<option value='= '''>= ''</option>";
						  echo "<option value='!= '''>!= ''</option>";
					  }
					  echo "<option value='!='>!=</option>";
					  if($type=="TEXT" || $type=="BLOB")
						  echo "<option value='LIKE' selected='selected'>LIKE</option>";
					  else
						  echo "<option value='LIKE'>LIKE</option>";
					  echo "<option value='LIKE%'>LIKE %...%</option>";
					  echo "<option value='NOT LIKE'>NOT LIKE</option>";
					  echo "</select>";
					  echo "</td>";
					  echo $tdWithClassLeft;
					  if($type=="INTEGER" || $type=="REAL" || $type=="NULL")
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
			//row actions
			/////////////////////////////////////////////// view row
			case "row_view":
				$table = $_GET['table'];
				$is_view = isset($_GET['view']) ? '&amp;view=1' : '';

				if(!isset($_POST['startRow']))
					$_POST['startRow'] = 0;

				if(isset($_POST['numRows']))
					$_SESSION[COOKIENAME.'numRows'] = $_POST['numRows'];

				if(!isset($_SESSION[COOKIENAME.'numRows']))
					$_SESSION[COOKIENAME.'numRows'] = $rowsNum;
				
				if(isset($_SESSION[COOKIENAME.'currentTable']) && $_SESSION[COOKIENAME.'currentTable']!=$table)
				{
					unset($_SESSION[COOKIENAME.'sortRows']);
					unset($_SESSION[COOKIENAME.'orderRows']);	
				}
				if(isset($_POST['viewtype']))
				{
					$_SESSION[COOKIENAME.'viewtype'] = $_POST['viewtype'];	
				}
				
				$rowCount = $db->numRows($table);
				$lastPage = intval($rowCount / $_SESSION[COOKIENAME.'numRows']);
				$remainder = intval($rowCount % $_SESSION[COOKIENAME.'numRows']);
				if($remainder==0)
					$remainder = $_SESSION[COOKIENAME.'numRows'];
				
				echo "<div style=''>";
				//previous button
				if($_POST['startRow']>0)
				{
					echo "<div style='float:left;'>";
					echo "<form action='?action=row_view&amp;table=".urlencode($table).$is_view."' method='post'>";
					echo "<input type='hidden' name='startRow' value='0'/>";
					echo "<input type='hidden' name='numRows' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
					echo "<input type='submit' value='&larr;&larr;' name='previous' class='btn'/> ";
					echo "</form>";
					echo "</div>";
					echo "<div style='float:left; overflow:hidden; margin-right:20px;'>";
					echo "<form action='?action=row_view&amp;table=".urlencode($table).$is_view."' method='post'>";
					echo "<input type='hidden' name='startRow' value='".intval($_POST['startRow']-$_SESSION[COOKIENAME.'numRows'])."'/>";
					echo "<input type='hidden' name='numRows' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
					echo "<input type='submit' value='&larr;' name='previous_full' class='btn'/> ";
					echo "</form>";
					echo "</div>";
				}
				
				//show certain number buttons
				echo "<div style='float:left;'>";
				echo "<form action='?action=row_view&amp;table=".urlencode($table).$is_view."' method='post'>";
				echo "<input type='submit' value='".$lang['show']." : ' name='show' class='btn'/> ";
				echo "<input type='text' name='numRows' style='width:50px;' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
				echo $lang['rows_records'];

				if(intval($_POST['startRow']+$_SESSION[COOKIENAME.'numRows']) < $rowCount)
					echo "<input type='text' name='startRow' style='width:90px;' value='".intval($_POST['startRow']+$_SESSION[COOKIENAME.'numRows'])."'/>";
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
				if(intval($_POST['startRow']+$_SESSION[COOKIENAME.'numRows'])<$rowCount)
				{
					echo "<div style='float:left; margin-left:20px; '>";
					echo "<form action='?action=row_view&amp;table=".urlencode($table).$is_view."' method='post'>";
					echo "<input type='hidden' name='startRow' value='".intval($_POST['startRow']+$_SESSION[COOKIENAME.'numRows'])."'/>";
					echo "<input type='hidden' name='numRows' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
					echo "<input type='submit' value='&rarr;' name='next' class='btn'/> ";
					echo "</form>";
					echo "</div>";
					echo "<div style='float:left; '>";
					echo "<form action='?action=row_view&amp;table=".urlencode($table).$is_view."' method='post'>";
					echo "<input type='hidden' name='startRow' value='".intval($rowCount-$remainder)."'/>";
					echo "<input type='hidden' name='numRows' value='".$_SESSION[COOKIENAME.'numRows']."'/> ";
					echo "<input type='submit' value='&rarr;&rarr;' name='next_full' class='btn'/> ";
					echo "</form>";
					echo "</div>";
				}
				echo "<div style='clear:both;'></div>";
				echo "</div>";
				
				if(!isset($_GET['sort']))
					$_GET['sort'] = NULL;
				if(!isset($_GET['order']))
					$_GET['order'] = NULL;

				$numRows = $_SESSION[COOKIENAME.'numRows'];
				$startRow = $_POST['startRow'];
				if(isset($_GET['sort']))
				{
					$_SESSION[COOKIENAME.'sortRows'] = $_GET['sort'];
					$_SESSION[COOKIENAME.'currentTable'] = $table;
				}
				if(isset($_GET['order']))
				{
					$_SESSION[COOKIENAME.'orderRows'] = $_GET['order'];
					$_SESSION[COOKIENAME.'currentTable'] = $table;
				}
				$_SESSION[COOKIENAME.'numRows'] = $numRows;
				$query = "SELECT *, ROWID FROM ".$db->quote_id($table);
				$queryDisp = "SELECT * FROM ".$db->quote_id($table);
				$queryAdd = "";
				if(isset($_SESSION[COOKIENAME.'sortRows']))
					$queryAdd .= " ORDER BY ".$db->quote_id($_SESSION[COOKIENAME.'sortRows']);
				if(isset($_SESSION[COOKIENAME.'orderRows']))
					$queryAdd .= " ".$_SESSION[COOKIENAME.'orderRows'];
				$queryAdd .= " LIMIT ".$startRow.", ".$numRows;
				$query .= $queryAdd;
				$queryDisp .= $queryAdd;
				$queryTimer = new MicroTimer();
				$arr = $db->selectArray($query);
				$queryTimer->stop();

				if(sizeof($arr)>0)
				{
					echo "<br/><div class='confirm'>";
					echo "<b>".$lang['showing_rows']." ".$startRow." - ".($startRow + sizeof($arr)-1).", ".$lang['total'].": ".$rowCount." ";
					printf($lang['query_time'], $queryTimer);
					echo "</b><br/>";
					echo "<span style='font-size:11px;'>".htmlencode($queryDisp)."</span>";
					echo "</div><br/>";
					
					if(isset($_GET['view']))
					{
						echo sprintf($lang['readonly_tbl'], htmlencode($_GET['table']))." <a href='http://en.wikipedia.org/wiki/View_(database)' target='_blank'>http://en.wikipedia.org/wiki/View_(database)</a>"; 
						echo "<br/><br/>";	
					}
					
					$query = "PRAGMA table_info(".$db->quote_id($table).")";
					$result = $db->selectArray($query);
					$rowidColumn = sizeof($result);
					
					if(!isset($_SESSION[COOKIENAME.'viewtype']) || $_SESSION[COOKIENAME.'viewtype']=="table")
					{
						echo "<form action='?action=row_editordelete&amp;table=".urlencode($table).$is_view."' method='post' name='checkForm'>";
						echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
						echo "<tr>";
						if(!isset($_GET['view']))
							echo "<td colspan='3'></td>";
	
						for($i=0; $i<sizeof($result); $i++)
						{
							echo "<td class='tdheader'>";
							if(!isset($_GET['view']))
								echo "<a href='?action=row_view&amp;table=".urlencode($table)."&amp;sort=".urlencode($result[$i]['name']);
							else
								echo "<a href='?action=row_view&amp;table=".urlencode($table)."&amp;view=1&amp;sort=".urlencode($result[$i]['name']);
							if(isset($_SESSION[COOKIENAME.'sortRows']))
								$orderTag = ($_SESSION[COOKIENAME.'sortRows']==$result[$i]['name'] && $_SESSION[COOKIENAME.'orderRows']=="ASC") ? "DESC" : "ASC";
							else
								$orderTag = "ASC";
							echo "&amp;order=".$orderTag;
							echo "'>".$result[$i]['name']."</a>";
							if(isset($_SESSION[COOKIENAME.'sortRows']) && $_SESSION[COOKIENAME.'sortRows']==$result[$i]['name'])
								echo (($_SESSION[COOKIENAME.'orderRows']=="ASC") ? " <b>&uarr;</b>" : " <b>&darr;</b>");
							echo "</td>";
						}
						echo "</tr>";
	
						for($i=0; $i<sizeof($arr); $i++)
						{
							// -g-> $pk will always be the last column in each row of the array because we are doing a "SELECT *, ROWID FROM ..."
							$pk = $arr[$i][$rowidColumn];
							$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
							$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
							echo "<tr>";
							if(!isset($_GET['view']))
							{
								echo $tdWithClass;
								echo "<input type='checkbox' name='check[]' value='".htmlencode($pk)."' id='check_".htmlencode($i)."'/>";
								echo "</td>";
								echo $tdWithClass;
								// -g-> Here, we need to put the ROWID in as the link for both the edit and delete.
								echo "<a href='?table=".urlencode($table)."&amp;action=row_editordelete&amp;pk=".urlencode($pk)."&amp;type=edit' title='".$lang['edit']."' class='edit'><span>".$lang['edit']."</span></a>";
								echo "</td>";
								echo $tdWithClass;
								echo "<a href='?table=".urlencode($table)."&amp;action=row_editordelete&amp;pk=".urlencode($pk)."&amp;type=delete' title='".$lang['del']."' class='delete'><span>".$lang['del']."</span></a>";
								echo "</td>";
							}
							for($j=0; $j<sizeof($result); $j++)
							{
								if(strtolower($result[$j]['type'])=="integer" || strtolower($result[$j]['type'])=="float" || strtolower($result[$j]['type'])=="real")
									echo $tdWithClass;
								else
									echo $tdWithClassLeft;
								if($arr[$i][$j]==="")
									echo "&nbsp;";
								elseif($arr[$i][$j]===NULL)
									echo "<i class='null'>NULL</i>";
								else
									echo subString(htmlencode($arr[$i][$j]));
								echo "</td>";
							}
							echo "</tr>";
						}
						echo "</table>";
						if(!isset($_GET['view']))
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
					{
						if(!isset($_SESSION[COOKIENAME.$_GET['table'].'chartlabels']))
						{
							// No label-column set. Try to pick a text-column as label-column.
							for($i=0; $i<sizeof($result); $i++)
							{
								$col_type = strtolower($result[$i]['type']);
								if(strpos($col_type, 'text')!==false || strpos($col_type, 'char')!==false || strpos($col_type, 'clob')!==false)
								{
									$_SESSION[COOKIENAME.$_GET['table'].'chartlabels'] = $i;
									break;
								}
							}
						}
						if(!isset($_SESSION[COOKIENAME.'chartlabels']))
							// no text column found, use the first column
							$_SESSION[COOKIENAME.'chartlabels'] = 0;
							
						if(!isset($_SESSION[COOKIENAME.$_GET['table'].'chartvalues']))
						{
							// No value-column set. Pick the first numeric column if possible.
							// If not possible, pick the first column that is not the label-column.
							
							$potential_value_column = null;
							for($i=0; $i<sizeof($result); $i++)
							{
								if($potential_value_column===null && $i != $_SESSION[COOKIENAME.$_GET['table'].'chartlabels'])
									// the first column (of any type) that is not the label-column
									$potential_value_column = $i;
								// check if the col is numeric
								$col_type = strtolower($result[$i]['type']);  
								if(strpos($col_type, 'int')!==false || strpos($col_type, 'real')!==false || strpos($col_type, 'floa')!==false || strpos($col_type, 'doub')!==false)
								{
									// this is defined as a numeric column, so prefer this as a value column over $potential_value_column
									$_SESSION[COOKIENAME.$_GET['table'].'chartvalues'] = $i;
									break;
								}
							}
							if(!isset($_SESSION[COOKIENAME.$_GET['table'].'chartvalues']))
							{
								// we did not find a numeric column
								if($potential_value_column!==null)
									// use the $potential_value_column, i.e. the second column which is not the label-column
									$_SESSION[COOKIENAME.$_GET['table'].'chartvalues'] = $potential_value_column;
								else
									// it's hopeless, there is only 1 column
									$_SESSION[COOKIENAME.$_GET['table'].'chartvalues'] = 0;  
							}
						}
						
						if(!isset($_SESSION[COOKIENAME.'charttype']))
							$_SESSION[COOKIENAME.'charttype'] = 'bar';
							
						if(isset($_POST['chartsettings']))
						{
							$_SESSION[COOKIENAME.'charttype'] = $_POST['charttype'];	
							$_SESSION[COOKIENAME.$_GET['table'].'chartlabels'] = $_POST['chartlabels'];
							$_SESSION[COOKIENAME.$_GET['table'].'chartvalues'] = $_POST['chartvalues'];
						}
						//begin chart view
						?>
						<script type='text/javascript' src='https://www.google.com/jsapi'></script>
						<script type='text/javascript'>
						google.load('visualization', '1.0', {'packages':['corechart']});
						google.setOnLoadCallback(drawChart);
						function drawChart()
						{
							var data = new google.visualization.DataTable();
							data.addColumn('string', '<?php echo $result[$_SESSION[COOKIENAME.$_GET['table'].'chartlabels']]['name']; ?>');
							data.addColumn('number', '<?php echo $result[$_SESSION[COOKIENAME.$_GET['table'].'chartvalues']]['name']; ?>');
							data.addRows([
							<?php
							for($i=0; $i<sizeof($arr); $i++)
							{
								$label = str_replace("'", "", htmlencode($arr[$i][$_SESSION[COOKIENAME.$_GET['table'].'chartlabels']]));
								$value = htmlencode($arr[$i][$_SESSION[COOKIENAME.$_GET['table'].'chartvalues']]);
								
								if($value==NULL || $value=="")
									$value = 0;
									
								echo "['".$label."', ".$value."]";
								if($i<sizeof($arr)-1)
									echo ",";
							}
							$height = (sizeof($arr)+1) * 30;
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
								'title':'<?php echo $result[$_SESSION[COOKIENAME.$_GET['table'].'chartlabels']]['name']." vs ".$result[$_SESSION[COOKIENAME.$_GET['table'].'chartvalues']]['name']; ?>'
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
						echo "<form action='?action=row_view&amp;table=".urlencode($_GET['table']).$is_view."' method='post'>";
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
							if(isset($_SESSION[COOKIENAME.$_GET['table'].'chartlabels']) && $_SESSION[COOKIENAME.$_GET['table'].'chartlabels']==$i)
								echo "<option value='".$i."' selected='selected'>".htmlencode($result[$i]['name'])."</option>";
							else
								echo "<option value='".$i."'>".htmlencode($result[$i]['name'])."</option>";
						}
						echo "</select>";
						echo "<br/><br/>";
						echo $lang['val'].": <select name='chartvalues'>";
						for($i=0; $i<sizeof($result); $i++)
						{
							if(isset($_SESSION[COOKIENAME.$_GET['table'].'chartvalues']) && $_SESSION[COOKIENAME.$_GET['table'].'chartvalues']==$i)
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
				elseif(!isset($_GET['view']))
				{
					echo "<br/><br/>".$lang['empty_tbl']." <a href='?table=".urlencode($_GET['table'])."&amp;action=row_create'>".$lang['click']."</a> ".$lang['insert_rows'];
				}

				break;
			/////////////////////////////////////////////// create row
			case "row_create":
				$fieldStr = "";
				echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=row_create' method='post'>";
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
				$query = "PRAGMA table_info(".$db->quote_id($_GET['table']).")";
				$result = $db->selectArray($query);
				echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=row_create&amp;confirm=1' method='post'>";
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
						$field_html = htmlencode($field);
						if($j==0)
							$fieldStr .= ":".$field;
						$type = strtolower($result[$i]['type']);
						$scalarField = $type=="integer" || $type=="real" || $type=="null";
						$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
						$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
						echo "<tr>";
						echo $tdWithClassLeft;
						echo $field_html;
						echo "</td>";
						echo $tdWithClassLeft;
						echo $type;
						echo "</td>";
						echo $tdWithClassLeft;
						echo "<select name='function_".$j."_".$field_html."' onchange='notNull(\"row_".$j."_field_".$i."_null\");'>";
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
								echo "<input type='checkbox' name='".$j.":".$field_html."_null' id='row_".$j."_field_".$i."_null' checked='checked' onclick='disableText(this, \"row_".$j."_field_".$i."_value\");'/>";
							else
								echo "<input type='checkbox' name='".$j.":".$field_html."_null' id='row_".$j."_field_".$i."_null' onclick='disableText(this, \"row_".$j."_field_".$i."_value\");'/>";
						}
						echo "</td>";
						echo $tdWithClassLeft;
						$type = strtolower($type);
						if($result[$i]['dflt_value'] === "NULL")
							$dflt_value = "";
						else
							$dflt_value = htmlencode(deQuoteSQL($result[$i]['dflt_value']));
						
						if($scalarField)
							echo "<input type='text' id='row_".$j."_field_".$i."_value' name='".$j.":".$field_html."' value='".$dflt_value."' onblur='changeIgnore(this, \"row_".$j."_ignore\");' onclick='notNull(\"row_".$j."_field_".$i."_null\");'/>";
						else
							echo "<textarea id='row_".$j."_field_".$i."_value' name='".$j.":".$field_html."' rows='5' cols='60' onclick='notNull(\"row_".$j."_field_".$i."_null\");' onblur='changeIgnore(this, \"row_".$j."_ignore\");'>".$dflt_value."</textarea>";
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
			/////////////////////////////////////////////// edit or delete row
			case "row_editordelete":
				if(isset($_POST['check']))
					$pks = $_POST['check'];
				else if(isset($_GET['pk']))
					$pks = array($_GET['pk']);
				else $pks[0] = "";
				$str = $pks[0];
				$pkVal = $pks[0];
				for($i=1; $i<sizeof($pks); $i++)
				{
					$str .= ", ".$pks[$i];
					$pkVal .= ":".$pks[$i];
				}
				if($str=="") //nothing was selected so show an error
				{
					echo "<div class='confirm'>";
					echo $lang['err'].": ".$lang['no_sel'];
					echo "</div>";
					echo "<br/><br/><a href='?table=".urlencode($_GET['table'])."&amp;action=row_view'>".$lang['return']."</a>";
				}
				else
				{
					if((isset($_POST['type']) && $_POST['type']=="edit") || (isset($_GET['type']) && $_GET['type']=="edit")) //edit
					{
						echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=row_edit&amp;confirm=1&amp;pk=".urlencode($pkVal)."' method='post'>";
						$query = "PRAGMA table_info(".$db->quote_id($_GET['table']).")";
						$result = $db->selectArray($query);

						//build the POST array of fields
						$fieldStr = $result[0][1];
						for($j=1; $j<sizeof($result); $j++)
							$fieldStr .= ":".$result[$j][1];

						echo "<input type='hidden' name='fieldArray' value='".htmlencode($fieldStr)."'/>";

						for($j=0; $j<sizeof($pks); $j++)
						{
							if(!is_numeric($pks[$j])) continue;
							$query = "SELECT * FROM ".$db->quote_id($_GET['table'])." WHERE ROWID = ".$pks[$j];
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
								$type = $result[$i][2];
								$value = $result1[$i];
								$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
								$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
								echo "<tr>";
								echo $tdWithClass;
								echo $field;
								echo "</td>";
								echo $tdWithClass;
								echo $type;
								echo "</td>";
								echo $tdWithClassLeft;
								echo "<select name='function_".htmlencode($pks[$j])."_".htmlencode($field)."' onchange='notNull(\"".htmlencode($pks[$j]).":".htmlencode($field)."_null\");'>";
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
										echo "<input type='checkbox' name='".htmlencode($pks[$j]).":".htmlencode($field)."_null' id='".htmlencode($pks[$j]).":".htmlencode($field)."_null' checked='checked'/>";
									else
										echo "<input type='checkbox' name='".htmlencode($pks[$j]).":".htmlencode($field)."_null' id='".htmlencode($pks[$j]).":".htmlencode($field)."_null'/>";
								}
								echo "</td>";
								echo $tdWithClassLeft;
								if($type=="INTEGER" || $type=="REAL" || $type=="NULL")
									echo "<input type='text' name='".htmlencode($pks[$j]).":".htmlencode($field)."' value='".htmlencode($value)."' onblur='changeIgnore(this, \"".$j."\", \"".htmlencode($pks[$j]).":".htmlencode($field)."_null\")' />";
								else
									echo "<textarea name='".htmlencode($pks[$j]).":".htmlencode($field)."' rows='1' cols='60' class='".htmlencode($field)."_textarea' onblur='changeIgnore(this, \"".$j."\", \"".htmlencode($pks[$j]).":".htmlencode($field)."_null\")'>".htmlencode($value)."</textarea>";
								echo "</td>";
								echo "</tr>";
							}
							echo "<tr>";
							echo "<td class='tdheader' style='text-align:right;' colspan='5'>";
							// Note: the 'Save changes' button must be first in the code so it is the one used when submitting the form with the Enter key (issue #215)
							echo "<input type='submit' value='".$lang['save_ch']."' class='btn'/> ";
							echo "<input type='submit' name='new_row' value='".$lang['new_insert']."' class='btn'/> ";
							echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=row_view'>".$lang['cancel']."</a>";
							echo "</td>";
							echo "</tr>";
							echo "</table>";
							echo "<br/>";
						}
						echo "</form>";
					}
					else //delete
					{
						echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=row_delete&amp;confirm=1&amp;pk=".urlencode($pkVal)."' method='post'>";
						echo "<div class='confirm'>";
						printf($lang['ques_del_rows'], htmlencode($str), htmlencode($_GET['table']));
						echo "<br/><br/>";
						echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
						echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=row_view'>".$lang['cancel']."</a>";
						echo "</div>";
					}
				}
				break;
			//column actions
			/////////////////////////////////////////////// view column
			case "column_view":
				$query = "PRAGMA table_info(".$db->quote_id($_GET['table']).")";
				$result = $db->selectArray($query);

				echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=column_delete' method='post' name='checkForm'>";
				echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
				echo "<tr>";
				if(!isset($_GET['view']))
					echo "<td colspan='3'></td>";
				echo "<td class='tdheader'>".$lang['col']." #</td>";
				echo "<td class='tdheader'>".$lang['fld']."</td>";
				echo "<td class='tdheader'>".$lang['type']."</td>";
				echo "<td class='tdheader'>Not Null</td>";
				echo "<td class='tdheader'>".$lang['def_val']."</td>";
				echo "<td class='tdheader'>".$lang['prim_key']."</td>";
				echo "</tr>";

				for($i=0; $i<sizeof($result); $i++)
				{
					$colVal = $result[$i][0];
					$fieldVal = $result[$i][1];
					$typeVal = $result[$i][2];
					$notnullVal = $result[$i][3];
					$defaultVal = $result[$i][4];
					$primarykeyVal = $result[$i][5];

					if(intval($notnullVal)!=0)
						$notnullVal = $lang['yes'];
					else
						$notnullVal = $lang['no'];
					if(intval($primarykeyVal)!=0)
						$primarykeyVal = $lang['yes'];
					else
						$primarykeyVal = $lang['no'];

					$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
					$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
					echo "<tr>";
					if(!isset($_GET['view']))
					{
						echo $tdWithClass;
						echo "<input type='checkbox' name='check[]' value='".htmlencode($fieldVal)."' id='check_".$i."'/>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=column_edit&amp;pk=".urlencode($fieldVal)."' title='".$lang['edit']."' class='edit'><span>".$lang['edit']."</span></a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=column_delete&amp;pk=".urlencode($fieldVal)."' title='".$lang['del']."' class='delete'><span>".$lang['del']."</span></a>";
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
				if(!isset($_GET['view']))
				{
					echo "<a onclick='checkAll()'>".$lang['chk_all']."</a> / <a onclick='uncheckAll()'>".$lang['unchk_all']."</a> <i>".$lang['with_sel'].":</i> ";
					echo "<select name='massType'>";
					//echo "<option value='edit'>".$lang['edit']."</option>";
					echo "<option value='delete'>".$lang['del']."</option>";
					echo "</select> ";
					echo "<input type='hidden' name='structureDel' value='true'/>";
					echo "<input type='submit' value='".$lang['go']."' name='massGo' class='btn'/>";
				}
				echo "</form>";
				if(!isset($_GET['view']))
				{
					echo "<br/>";
					echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=column_create' method='post'>";
					echo "<input type='hidden' name='tablename' value='".htmlencode($_GET['table'])."'/>";
					echo $lang['add']." <input type='text' name='tablefields' style='width:30px;' value='1'/> ".$lang['tbl_end']." <input type='submit' value='".$lang['go']."' name='addfields' class='btn'/>";
					echo "</form>";
				}
				
				$query = "SELECT sql FROM sqlite_master WHERE name=".$db->quote($_GET['table']);
				$master = $db->selectArray($query);
				
				echo "<br/>";
				if(!isset($_GET['view']))
					$type = "table";
				else
					$type = "view";
				echo "<br/>";
				echo "<div class='confirm'>";
				echo "<b>".$lang['query_used_'.$type]."</b><br/>";
				echo "<span style='font-size:11px;'>".htmlencode($master[0]['sql'])."</span>";
				echo "</div>";
				echo "<br/>";
				if(!isset($_GET['view']))
				{
					echo "<br/><hr/><br/>";
					//$query = "SELECT * FROM sqlite_master WHERE type='index' AND tbl_name='".$_GET['table']."'";
					$query = "PRAGMA index_list(".$db->quote_id($_GET['table']).")";
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
							echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=index_delete&amp;pk=".urlencode($result[$i]['name'])."' title='".$lang['del']."' class='delete'><span>".$lang['del']."</span></a>";
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
					
					$query = "SELECT * FROM sqlite_master WHERE type='trigger' AND tbl_name=".$db->quote($_GET['table'])." ORDER BY name";
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
							echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=trigger_delete&amp;pk=".urlencode($result[$i]['name'])."' title='".$lang['del']."' class='delete'><span>".$lang['del']."</span></a>";
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
					
					echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=index_create' method='post'>";
					echo "<input type='hidden' name='tablename' value='".htmlencode($_GET['table'])."'/>";
					echo "<br/><div class='tdheader'>";
					echo $lang['create_index2']." <input type='text' name='numcolumns' style='width:30px;' value='1'/> ".$lang['cols']." <input type='submit' value='".$lang['go']."' name='addindex' class='btn'/>";
					echo "</div>";
					echo "</form>";
					
					echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=trigger_create' method='post'>";
					echo "<input type='hidden' name='tablename' value='".htmlencode($_GET['table'])."'/>";
					echo "<br/><div class='tdheader'>";
					echo $lang['create_trigger2']." <input type='submit' value='".$lang['go']."' name='addindex' class='btn'/>";
					echo "</div>";
					echo "</form>";
				}
				break;
			/////////////////////////////////////////////// create column
			case "column_create":
				echo "<h2>".sprintf($lang['new_fld'],htmlencode($_POST['tablename']))."</h2>";
				if($_POST['tablefields']=="" || intval($_POST['tablefields'])<=0)
					echo $lang['specify_fields'];
				else if($_POST['tablename']=="")
					echo $lang['specify_tbl'];
				else
				{
					$num = intval($_POST['tablefields']);
					$name = $_POST['tablename'];
					echo "<form action='?table=".urlencode($_POST['tablename'])."&amp;action=column_create&amp;confirm=1' method='post'>";
					echo "<input type='hidden' name='tablename' value='".htmlencode($name)."'/>";
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
					echo "<a href='?table=".urlencode($_POST['tablename'])."&amp;action=column_view'>".$lang['cancel']."</a>";
					echo "</td>";
					echo "</tr>";
					echo "</table>";
					echo "</form>";
				}
				break;
			/////////////////////////////////////////////// delete column
			case "column_delete":
				if(isset($_POST['check']))
					$pks = $_POST['check'];
				elseif(isset($_GET['pk']))
					$pks = array($_GET['pk']);
				else $pks = array();
				
				if(sizeof($pks)==0) //nothing was selected so show an error
				{
					echo "<div class='confirm'>";
					echo $lang['err'].": ".$lang['no_sel'];
					echo "</div>";
					echo "<br/><br/><a href='?table=".urlencode($_GET['table'])."&amp;action=column_view'>".$lang['return']."</a>";
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
					echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=column_delete&amp;confirm=1&amp;pk=".urlencode($pkVal)."' method='post'>";
					echo "<div class='confirm'>";
					printf($lang['ques_del_col'], htmlencode($str), htmlencode($_GET['table']));
					echo "<br/><br/>";
					echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
					echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=column_view'>".$lang['cancel']."</a>";
					echo "</div>";
				}
				break;
			/////////////////////////////////////////////// edit column
			case "column_edit":
				echo "<h2>".sprintf($lang['edit_col'], htmlencode($_GET['pk']))." ".$lang['on_tbl']." '".htmlencode($_GET['table'])."'</h2>";
				echo $lang['sqlite_limit']."<br/><br/>";
				if(!isset($_GET['pk']))
					echo $lang['specify_col'];
				else if(!isset($_GET['table']) || $_GET['table']=="")
					echo $lang['specify_tbl'];
				else
				{
					$query = "PRAGMA table_info(".$db->quote_id($_GET['table']).")";
					$result = $db->selectArray($query);

					for($i=0; $i<sizeof($result); $i++)
					{
						if($result[$i][1]==$_GET['pk'])
						{
							$colVal = $result[$i][0];
							$fieldVal = $result[$i][1];
							$typeVal = $result[$i][2];
							$notnullVal = $result[$i][3];
							$defaultVal = $result[$i][4];
							$primarykeyVal = $result[$i][5];
							break;
						}
					}
					
					$name = $_GET['table'];
					echo "<form action='?table=".urlencode($name)."&amp;action=column_edit&amp;confirm=1' method='post'>";
					echo "<input type='hidden' name='tablename' value='".htmlencode($name)."'/>";
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
					echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=column_view'>".$lang['cancel']."</a>";
					echo "</td>";
					echo "</tr>";
					echo "</table>";
					echo "</form>";
				}
				break;
			/////////////////////////////////////////////// delete index
			case "index_delete":
				echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=index_delete&amp;pk=".urlencode($_GET['pk'])."&amp;confirm=1' method='post'>";
				echo "<div class='confirm'>";
				echo sprintf($lang['ques_del_index'], htmlencode($_GET['pk']))."<br/><br/>";
				echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
				echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=column_view'>".$lang['cancel']."</a>";
				echo "</div>";
				echo "</form>";
				break;
			/////////////////////////////////////////////// delete trigger
			case "trigger_delete":
				echo "<form action='?table=".urlencode($_GET['table'])."&amp;action=trigger_delete&amp;pk=".urlencode($_GET['pk'])."&amp;confirm=1' method='post'>";
				echo "<div class='confirm'>";
				echo sprintf($lang['ques_del_trigger'], htmlencode($_GET['pk']))."<br/><br/>";
				echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
				echo "<a href='?table=".urlencode($_GET['table'])."&amp;action=column_view'>".$lang['cancel']."</a>";
				echo "</div>";
				echo "</form>";
				break;
			/////////////////////////////////////////////// create trigger
			case "trigger_create":
				echo "<h2>".$lang['create_trigger']." '".htmlencode($_POST['tablename'])."'</h2>";
				if($_POST['tablename']=="")
					echo $lang['specify_tbl'];
				else
				{
					echo "<form action='?table=".urlencode($_POST['tablename'])."&amp;action=trigger_create&amp;confirm=1' method='post'>";
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
					echo "<a href='?table=".urlencode($_POST['tablename'])."&amp;action=column_view'>".$lang['cancel']."</a>";
					echo "</form>";
				}
				break;
			/////////////////////////////////////////////// create index
			case "index_create":
				echo "<h2>".$lang['create_index']." '".htmlencode($_POST['tablename'])."'</h2>";
				if($_POST['numcolumns']=="" || intval($_POST['numcolumns'])<=0)
					echo $lang['specify_fields'];
				else if($_POST['tablename']=="")
					echo $lang['specify_tbl'];
				else
				{
					echo "<form action='?table=".urlencode($_POST['tablename'])."&amp;action=index_create&amp;confirm=1' method='post'>";
					$num = intval($_POST['numcolumns']);
					$query = "PRAGMA table_info(".$db->quote_id($_POST['tablename']).")";

					$result = $db->selectArray($query);
					echo "<fieldset><legend>".$lang['define_index']."</legend>";
					echo $lang['index_name'].": <input type='text' name='name'/><br/>";
					echo $lang['dup_val'].": ";
					echo "<select name='duplicate'>";
					echo "<option value='yes'>".$lang['allow']."</option>";
					echo "<option value='no'>".$lang['not_allow']."</option>";
					echo "</select><br/>";
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
					echo "<a href='?table=".urlencode($_POST['tablename'])."&amp;action=column_view'>".$lang['cancel']."</a>";
					echo "</form>";
				}
				break;
		}
		echo "</div>";
	}
	
	$view = "structure";
		
	if(!isset($_GET['table']) && !isset($_GET['confirm']) && (!isset($_GET['action']) || (isset($_GET['action']) && $_GET['action']!="table_create"))) //the absence of these fields means we are viewing the database homepage
	{
		if(isset($_GET['view']))
			$view = $_GET['view'];
		else
			$view = "structure";

		echo "<a href='?view=structure' ";
		if($view=="structure")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">".$lang['struct']."</a>";
		echo "<a href='?view=sql' ";
		if($view=="sql")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">".$lang['sql']."</a>";
		echo "<a href='?view=export' ";
		if($view=="export")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">".$lang['export']."</a>";
		echo "<a href='?view=import' ";
		if($view=="import")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">".$lang['import']."</a>";
		echo "<a href='?view=vacuum' ";
		if($view=="vacuum")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">".$lang['vac']."</a>";
		if($directory!==false && is_writable($directory))
		{
			echo "<a href='?view=rename' ";
			if($view=="rename")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">".$lang['db_rename']."</a>";
			
			echo "<a href='?view=delete' title='".$lang['db_del']."' ";
			if($view=="delete")
				echo "class='tab_pressed delete_db'";
			else
				echo "class='tab delete_db'";
			echo "><span>".$lang['db_del']."</span></a>";
		}
		echo "<div style='clear:both;'></div>";
		echo "<div id='main'>";

		if($view=="structure") //database structure - view of all the tables
		{
			$query = "SELECT sqlite_version() AS sqlite_version";
			$queryVersion = $db->select($query);
			$realVersion = $queryVersion['sqlite_version'];
			
			if(isset($dbexists))
			{
				echo "<div class='confirm' style='margin:10px 20px;'>";
				echo $lang['err'].': '.sprintf($lang['db_exists'], htmlencode($dbname));
				echo "</div><br/>";
			}
			
			if($db->isWritable() && !$db->isDirWritable())
			{
				echo "<div class='confirm' style='margin:10px 20px;'>";
				echo $lang['attention'].': '.$lang['directory_not_writable'];
				echo "</div><br/>";
			}
			
			if(isset($extension_not_allowed))
			{
				echo "<div class='confirm' style='margin:10px 20px;'>";
				echo $lang['extension_not_allowed'].': ';
				echo implode(', ', array_map('htmlencode', $allowed_extensions));
				echo '<br />'.$lang['add_allowed_extension'];
				echo "</div><br/>";
			}

			if ($auth->isPasswordDefault())
			{
				echo "<div class='confirm' style='margin:20px 0px;'>";
				echo sprintf($lang['warn_passwd'],(is_readable('phpliteadmin.config.php')?'phpliteadmin.config.php':PAGE))."<br />".$lang['warn0'];
				echo "</div>";
			}
			
			echo "<b>".$lang['db_name']."</b>: ".htmlencode($db->getName())."<br/>";
			echo "<b>".$lang['db_path']."</b>: ".htmlencode($db->getPath())."<br/>";
			echo "<b>".$lang['db_size']."</b>: ".$db->getSize()." KB<br/>";
			echo "<b>".$lang['db_mod']."</b>: ".$db->getDate()."<br/>";
			echo "<b>".$lang['sqlite_v']."</b>: ".$realVersion."<br/>";
			echo "<b>".$lang['sqlite_ext']."</b> ".helpLink($lang['help1']).": ".$db->getType()."<br/>"; 
			echo "<b>".$lang['php_v']."</b>: ".phpversion()."<br/><br/>";
			
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
				echo "<a href='?sort=type";
				if(isset($_SESSION[COOKIENAME.'sortTables']))
					$orderTag = ($_SESSION[COOKIENAME.'sortTables']=="type" && $_SESSION[COOKIENAME.'orderTables']=="ASC") ? "DESC" : "ASC";
				else
					$orderTag = "ASC";
				echo "&amp;order=".$orderTag;
				echo "'>".$lang['type']."</a> ".helpLink($lang['help3']); 
				if(isset($_SESSION[COOKIENAME.'sortTables']) && $_SESSION[COOKIENAME.'sortTables']=="type")
					echo (($_SESSION[COOKIENAME.'orderTables']=="ASC") ? " <b>&uarr;</b>" : " <b>&darr;</b>");
				echo "</td>";
				
				echo "<td class='tdheader'>";
				echo "<a href='?sort=name";
				if(isset($_SESSION[COOKIENAME.'sortTables']))
					$orderTag = ($_SESSION[COOKIENAME.'sortTables']=="name" && $_SESSION[COOKIENAME.'orderTables']=="ASC") ? "DESC" : "ASC";
				else
					$orderTag = "ASC";
				echo "&amp;order=".$orderTag;
				echo "'>".$lang['name']."</a>";
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
						$records = "<a href='?forceCount=1'>?</a>";
					}
					else
						$totalRecords += $records;
					$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
					$tdWithClassLeft = "<td class='td".($i%2 ? "1" : "2")."' style='text-align:left;'>";
					
					if($result[$i]['type']=="table")
					{
						echo "<tr>";
						echo $tdWithClassLeft;
						echo $lang['tbl'];
						echo "</td>";
						echo $tdWithClassLeft;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=row_view'>".htmlencode($result[$i]['name'])."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=row_view'>".$lang['browse']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=column_view'>".$lang['struct']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_sql'>".$lang['sql']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_search'>".$lang['srch']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=row_create'>".$lang['insert']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_export'>".$lang['export']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_import'>".$lang['import']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_rename'>".$lang['rename']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_empty' class='empty'>".$lang['empty']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_drop' class='drop'>".$lang['drop']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo $records;
						echo "</td>";
						echo "</tr>";
					}
					else
					{
						echo "<tr>";
						echo $tdWithClassLeft;
						echo "View";
						echo "</td>";
						echo $tdWithClassLeft;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=row_view&amp;view=1'>".htmlencode($result[$i]['name'])."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=row_view&amp;view=1'>".$lang['browse']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=column_view&amp;view=1'>".$lang['struct']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_sql&amp;view=1'>".$lang['sql']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_search&amp;view=1'>".$lang['srch']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=table_export&amp;view=1'>".$lang['export']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "";
						echo "</td>";
						echo $tdWithClass;
						echo "";
						echo "</td>";
						echo $tdWithClass;
						echo "";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='?table=".urlencode($result[$i]['name'])."&amp;action=view_drop&amp;view=1' class='drop'>".$lang['drop']."</a>";
						echo "</td>";
						echo $tdWithClass;
						echo $records;
						echo "</td>";
						echo "</tr>";
					}
				}
				echo "<tr>";
				echo "<td class='tdheader' colspan='12'>".sizeof($result)." total</td>";
				echo "<td class='tdheader' colspan='1' style='text-align:right;'>".$totalRecords.($skippedTables?" <a href='?forceCount=1'>+ ?</a>":"")."</td>";
				echo "</tr>";
				echo "</table>";
				echo "<br/>";
				if($skippedTables)
					echo "<div class='confirm' style='margin-bottom:20px;'>".sprintf($lang["counting_skipped"],"<a href='?forceCount=1'>","</a>")."</div>";
			}
			echo "<fieldset>";
			echo "<legend><b>".$lang['create_tbl_db']." '".htmlencode($db->getName())."'</b></legend>";
			echo "<form action='?action=table_create' method='post'>";
			echo $lang['name'].": <input type='text' name='tablename' style='width:200px;'/> ";
			echo $lang['fld_num'].": <input type='text' name='tablefields' style='width:90px;'/> ";
			echo "<input type='submit' name='createtable' value='".$lang['go']."' class='btn'/>";
			echo "</form>";
			echo "</fieldset>";
			echo "<br/>";
			echo "<fieldset>";
			echo "<legend><b>".$lang['create_view']." '".htmlencode($db->getName())."'</b></legend>";
			echo "<form action='?action=view_create&amp;confirm=1' method='post'>";
			echo $lang['name'].": <input type='text' name='viewname' style='width:200px;'/> ";
			echo $lang['sel_state']." ".helpLink($lang['help4']).": <input type='text' name='select' style='width:400px;'/> "; 
			echo "<input type='submit' name='createtable' value='".$lang['go']."' class='btn'/>";
			echo "</form>";
			echo "</fieldset>";
		}
		else if($view=="sql") //database SQL editor
		{
			$isSelect = false;
			if(isset($_POST['query']) && $_POST['query']!="")
			{
				$delimiter = $_POST['delimiter'];
				$queryStr = $_POST['queryval'];
				$query = explode_sql($delimiter, $queryStr); //explode the query string into individual queries based on the delimiter

				for($i=0; $i<sizeof($query); $i++) //iterate through the queries exploded by the delimiter
				{
					if(str_replace(" ", "", str_replace("\n", "", str_replace("\r", "", $query[$i])))!="") //make sure this query is not an empty string
					{
						$queryTimer = new MicroTimer();
						if(preg_match('/^\s*(?:select|pragma|explain)\s/i', $query[$i])===1)   // pragma and explain often return rows just like select
						{
							$isSelect = true;
							$result = $db->selectArray($query[$i], "assoc");
						}
						else
						{
							$isSelect = false;
							$result = $db->query($query[$i]);
						}
						$queryTimer->stop();

						echo "<div class='confirm'>";
						echo "<b>";
						// 22 August 2011: gkf fixed bugs 46, 51 and 52.
						if($result!==false)
						{
							if($isSelect)
							{
								$affected = sizeof($result);
								printf($lang['show_rows'], $affected);
							}
							else
							{
								$affected = $db->getAffectedRows();
								echo $affected." ".$lang['rows_aff']." ";
							}
							printf($lang['query_time'], $queryTimer);
							echo "</b><br/>";
						}
						else
						{
							echo $lang['err'].": ".$db->getError()."</b><br/>";
						}
						echo "<span style='font-size:11px;'>".htmlencode($query[$i])."</span>";
						echo "</div><br/>";
						if($isSelect)
						{
							if(sizeof($result)>0)
							{
								$headers = array_keys($result[0]);

								echo "<table border='0' cellpadding='2' cellspacing='1' class='viewTable'>";
								echo "<tr>";
								for($j=0; $j<sizeof($headers); $j++)
								{
									echo "<td class='tdheader'>";
									echo htmlencode($headers[$j]);
									echo "</td>";
								}
								echo "</tr>";
								for($j=0; $j<sizeof($result); $j++)
								{
									$tdWithClass = "<td class='td".($j%2 ? "1" : "2")."'>";
									echo "<tr>";
									for($z=0; $z<sizeof($headers); $z++)
									{
										echo $tdWithClass;
										if($result[$j][$headers[$z]]==="")
											echo "&nbsp;";
										elseif($result[$j][$headers[$z]]===NULL)
											echo "<i class='null'>NULL</i>";
										else
											echo subString(htmlencode($result[$j][$headers[$z]]));
										echo "</td>";
									}
									echo "</tr>";
								}
								echo "</table><br/><br/>";
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
			echo "<form action='?view=sql' method='post'>";
			echo "<textarea style='width:100%; height:300px;' name='queryval' cols='50' rows='8'>".htmlencode($queryStr)."</textarea>";
			echo $lang['delimit']." <input type='text' name='delimiter' value='".htmlencode($delimiter)."' style='width:50px;'/> ";
			echo "<input type='submit' name='query' value='".$lang['go']."' class='btn'/>";
			echo "</form>";
			echo "</fieldset>";
		}
		else if($view=="vacuum")
		{
			if(isset($_POST['vacuum']))
			{
				$query = "VACUUM";
				$db->query($query);
				echo "<div class='confirm'>";
				printf($lang['db_vac'], htmlencode($db->getName()));
				echo "</div><br/>";
			}
			echo "<form method='post' action='?view=vacuum'>";
			printf($lang['vac_desc'],htmlencode($db->getName()));
			echo "<br/><br/>";
			echo "<input type='submit' value='".$lang['vac']."' name='vacuum' class='btn'/>";
			echo "</form>";
		}
		else if($view=="export")
		{
			echo "<form method='post' action='?view=export'>";
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
		}
		else if($view=="import")
		{
			if(isset($_POST['import']))
			{
				echo "<div class='confirm'>";
				if($importSuccess===true)
					echo $lang['import_suc'];
				else
					echo $importSuccess;
				echo "</div><br/>";
			}
			
			echo "<form method='post' action='?view=import' enctype='multipart/form-data'>";
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
			if(isset($extension_not_allowed))
			{
				echo "<div class='confirm'>";
				echo $lang['extension_not_allowed'].': ';
				echo implode(', ', array_map('htmlencode', $allowed_extensions));
				echo '<br />'.$lang['add_allowed_extension'];
				echo "</div><br/>";
			}
			if(isset($dbexists))
			{
				echo "<div class='confirm'>";
				if($oldpath==$newpath)
					echo $lang['err'].": ".$lang['warn_dumbass'];
				else{
					echo $lang['err'].": "; 
					printf($lang['db_exists'], htmlencode($newpath));
				}
				echo "</div><br/>";
			}
			if(isset($justrenamed))
			{
				echo "<div class='confirm'>";
				printf($lang['db_renamed'], htmlencode($oldpath));
				echo " '".htmlencode($newpath)."'.";
				echo "</div><br/>";
			}
			echo "<form action='?view=rename&amp;database_rename=1' method='post'>";
			echo "<input type='hidden' name='oldname' value='".htmlencode($db->getPath())."'/>";
			echo $lang['db_rename']." '".htmlencode($db->getPath())."' ".$lang['to']." <input type='text' name='newname' style='width:200px;' value='".htmlencode($db->getPath())."'/> <input type='submit' value='".$lang['rename']."' name='rename' class='btn'/>";
			echo "</form>";	
		}
		else if($view=="delete")
		{
			echo "<form action='?database_delete=1' method='post'>";
			echo "<div class='confirm'>";
			echo sprintf($lang['ques_del_db'],htmlencode($db->getPath()))."<br/><br/>";
			echo "<input name='database_delete' value='".htmlencode($db->getPath())."' type='hidden'/>";
			echo "<input type='submit' value='".$lang['confirm']."' class='btn'/> ";
			echo "<a href='".PAGE."'>".$lang['cancel']."</a>";
			echo "</div>";
			echo "</form>";	
		}

		echo "</div>";
	}

	echo "<br/>";
	echo "<span style='font-size:11px;'>".$lang['powered']." <a href='".PROJECT_URL."' target='_blank' style='font-size:11px;'>".PROJECT."</a> | ";
	printf($lang['page_gen'], $pageTimer);
	echo "</span>";
	echo "</td></tr></table>";
	$db->close(); //close the database
}
echo "</body>";
echo "</html>";

// end of main code
