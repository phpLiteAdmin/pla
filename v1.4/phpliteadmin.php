<?php
/*
 * Project: phpLiteAdmin (http://code.google.com/p/phpliteadmin/)
 * Version: 1.4
 * Summary: PHP-based admin tool to view and edit SQLite databases
 * Last updated: 3/6/11
 * Contributors:
 *    Dane Iracleous (daneiracleous@gmail.com)
 *    George Flanagin & Digital Gaslight, Inc (george@digitalgaslight.com)
 */

//an array of databases that will appear in the application
//if any of the databases do not exist as they are referenced by their path, they will be created automatically if possible
$databases = array
(
	array
	(
		"path"=> "database1.sqlite", //path to database file on server relative to this file
		"name"=> "Database 1", //name of database to appear in application
		"version"=> 3 //SQLite version of database (important!)
	),
	array
	(
		"path"=> "database2.sqlite",
		"name"=> "Database 2",
		"version"=> 3
	),
	array
	(
		"path"=> "database3.sqlite",
		"name"=> "Database 3",
		"version"=> 3
	),
	array
	(
		"path"=> "database4.sqlite",
		"name"=> "Database 4",
		"version"=> 2
	)
);

//password to gain access (please change this to something more secure than 'admin')
$password = "admin";




//End of user-editable fields and beginning of user-unfriendly source code

//ini_set("display_errors", 1);
//error_reporting(E_STRICT | E_ALL);
session_start();
$startTimeTot = microtime(true); //start the timer to record page load time

//build the basename of this file
$nameArr = explode("?", $_SERVER['PHP_SELF']);
$thisName = $nameArr[0];
$nameArr = explode("/", $thisName);
$thisName = $nameArr[sizeof($nameArr)-1];

//constants
define("PROJECT", "phpLiteAdmin");
define("VERSION", "1.4");
define("PAGE", $thisName);

//
// Authorization class
// Maintains user's logged-in state and security of application
//
class Authorization
{
	public function grant()
	{
		$_SESSION['auth'] = true;
	}
	public function revoke()
	{
		unset($_SESSION['auth']);
	}
	public function isAuthorized()
	{
		return isset($_SESSION['auth']);
	}
}

//
// Database class
// Generic database abstraction class to manage interaction with database without worrying about SQLite vs. PHP versions
//
class Database 
{
	protected $db; //reference to the DB object
	protected $type; //the extension for PHP that handles SQLite
	protected $data;
	protected $lastResult;
	
	public function __construct($data) 
	{
		$this->data = $data;
		try
		{
			if(file_exists($this->data["path"]) && !is_writable($this->data["path"])) //make sure the containing directory is writable
			{
				echo "<div class='confirm' style='margin:20px;'>";
				echo "The database, '".$this->data["path"]."', is not writable. The application is unusable until you make it writable.";
				echo "</div><br/>";
				exit();
			}
			
			if(class_exists("PDO") && $this->data["version"]==3) //first choice is PDO
			{
				$this->type = "PDO";
				$this->db = new PDO("sqlite:".$this->data["path"]);
			}
			else if(class_exists("SQLite3") && $this->data["version"]==3) //second choice is SQLite3
			{
				$this->type = "SQLite3";
				$this->db = new SQLite3($this->data["path"]);
			}
			else if(class_exists("SQLiteDatabase") && $this->data["version"]==2) //third choice is SQLite, AKA SQlite2 - some features may be missing and cause problems, but it's better than nothing :/
			{
				$this->type = "SQLiteDatabase";
				$this->db = new SQLiteDatabase($this->data["path"]);
			}
			else //none of the possible extensions are enabled/installed and thus, the application cannot work
			{
				$this->showError();
				exit();
			}
		}
		catch(Exception $e)
		{
			$this->showError();
			exit();
		}
	}
	
	public function showError()
	{
		$classPDO = class_exists("PDO");
		$classSQLite3 = class_exists("SQLite3");
		$classSQLiteDatabase = class_exists("SQLiteDatabase");
		if($classPDO)
			$strPDO = "installed";
		else
			$strPDO = "not installed";
		if($classSQLite3)
			$strSQLite3 = "installed";
		else
			$strSQLite3 = "not installed";
		if($classSQLiteDatabase)
			$strSQLiteDatabase = "installed";
		else
			$strSQLiteDatabase = "not installed";
		echo "<div class='confirm' style='margin:20px;'>";
		echo "<i>Checking supported SQLite PHP extensions...<br/><br/>";
		echo "<b>PDO</b>: ".$strPDO."<br/>";
		echo "<b>SQLite3</b>: ".$strSQLite3."<br/>";
		echo "<b>SQLiteDatabase</b>: ".$strSQLiteDatabase."<br/><br/>...done.</i><br/><br/>";
		if(!$classPDO && !$classSQLite3 && !$classSQLiteDatabase)
			echo "It appears that none of the supported SQLite library extensions are available in your installation of PHP. You may not use phpLiteAdmin until you install at least one of them.";
		else
			echo "It appears that your installation of PHP includes one or more of the supported SQLite library extensions. However, your database(s) are not configured correctly. Most likely, you specified a version of SQLite for your database(s) that is not compatible with your available extensions. Try changing the version value for your database(s).";
		echo "</div><br/>";
	}
	
	public function __destruct() 
	{
		if($this->db)
			$this->close();
	}
	
	//get the exact PHP extension being used for SQLite
	public function getType()
	{
		return $this->type;	
	}
	
	//get the name of the database
	public function getName()
	{
		return $this->data["name"];	
	}
	
	//get the filename of the database
	public function getPath()
	{
		return $this->data["path"];	
	}
	
	//get the version of the database
	public function getVersion()
	{
		return $this->data["version"];	
	}
	
	//get the size of the database
	public function getSize()
	{
		return round(filesize($this->data["path"])*0.0009765625, 1)." Kb";	
	}
	
	//get the last modified time of database
	public function getDate()
	{
		return date("g:ia \o\\n F j, Y", filemtime($this->data["path"]));	
	}
	
	//get number of affected rows from last query
	public function getAffectedRows()
	{
		if($this->type=="PDO")
			return $this->lastResult->rowCount();
		else if($this->type=="SQLite3")
			return $this->db->changes();
		else if($this->type=="SQLiteDatabase")
			return $this->db->changes();
	}
	
	public function close() 
	{
		if($this->type=="PDO")
			$this->db = NULL;
		else if($this->type=="SQLite3")
			$this->db->close();
		else if($this->type=="SQLiteDatabase")
			$this->db = NULL;
	}
	
	public function beginTransaction()  
	{
		$this->query("BEGIN");
	}
	
	public function commitTransaction() 
	{
		$this->query("COMMIT");
	}
	
	public function rollbackTransaction() 
	{
		$this->query("ROLLBACK");
	}
	
	//generic query wrapper
	public function query($query, $ignoreAlterCase=false)
	{
		if(strtolower(substr(ltrim($query),0,5))=='alter' && $ignoreAlterCase==false) //this query is an ALTER query - call the necessary function
		{
			$queryparts = preg_split("/[\s]+/", $query, 4, PREG_SPLIT_NO_EMPTY);
			$tablename = $queryparts[2];
			$alterdefs = $queryparts[3];
			//echo $query;
			$result = $this->alterTable($tablename, $alterdefs);
		}
		else //this query is normal - proceed as normal
			$result = $this->db->query($query);
		$this->lastResult = $result;
		return $result;
	}
	
	//wrapper for an INSERT and returns the ID of the inserted row
	public function insert($query)   
	{
		$result = $this->query($query);
		if($this->type=="PDO")
			return $this->db->lastInsertId();
		else if($this->type=="SQLite3")
			return $this->db->lastInsertRowID();
		else if($this->type=="SQLiteDatabase")
			return $this->db->lastInsertRowid();
	}
	
	//returns an array for SELECT
	public function select($query, $mode="both") 
	{
		$result = $this->query($query);
		if($this->type=="PDO")
		{
			if($mode=="assoc")
				$mode = PDO::FETCH_ASSOC;
			else if($mode=="num")
				$mode = PDO::FETCH_NUM;
			else
				$mode = PDO::FETCH_BOTH;
			return $result->fetch($mode);
		}
		else if($this->type=="SQLite3")
		{
			if($mode=="assoc")
				$mode = SQLITE3_ASSOC;
			else if($mode=="num")
				$mode = SQLITE3_NUM;
			else
				$mode = SQLITE3_BOTH;
			return $result->fetchArray($mode);
		}
		else if($this->type=="SQLiteDatabase")
		{
			if($mode=="assoc")
				$mode = SQLITE_ASSOC;
			else if($mode=="num")
				$mode = SQLITE_NUM;
			else
				$mode = SQLITE_BOTH;
			return $result->fetch($mode);
		}
	}
	
	//returns an array of arrays after doing a SELECT
	public function selectArray($query, $mode="both")
	{
		$result = $this->query($query);
		if($this->type=="PDO")
		{
			if($mode=="assoc")
				$mode = PDO::FETCH_ASSOC;
			else if($mode=="num")
				$mode = PDO::FETCH_NUM;
			else
				$mode = PDO::FETCH_BOTH;
			return $result->fetchAll($mode);
		}
		else if($this->type=="SQLite3")
		{
			if($mode=="assoc")
				$mode = SQLITE3_ASSOC;
			else if($mode=="num")
				$mode = SQLITE3_NUM;
			else
				$mode = SQLITE3_BOTH;
			$arr = array();
			$i = 0;
			while($res = $result->fetchArray($mode))
			{ 
				$arr[$i] = $res;
				$i++;
			} 
			return $arr;	
		}
		else if($this->type=="SQLiteDatabase")
		{
			if($mode=="assoc")
				$mode = SQLITE_ASSOC;
			else if($mode=="num")
				$mode = SQLITE_NUM;
			else
				$mode = SQLITE_BOTH;
			return $result->fetchAll($mode);
		}
	}
	
	//function that is called for an alter table statement in a query
	//code borrowed with permission from http://code.jenseng.com/db/
	public function alterTable($table, $alterdefs)
	{
		if($alterdefs != '')
		{
			$tempQuery = "SELECT sql,name,type FROM sqlite_master WHERE tbl_name = '".$table."' ORDER BY type DESC";
			$result = $this->query($tempQuery);
			$resultArr = $this->selectArray($tempQuery);
			
			if(sizeof($resultArr)>0)
			{
				$row = $this->select($tempQuery); //table sql
				$tmpname = 't'.time();
				$origsql = trim(preg_replace("/[\s]+/", " ", str_replace(",", ", ",preg_replace("/[\(]/", "( ", $row['sql'], 1))));
				$createtemptableSQL = 'CREATE TEMPORARY '.substr(trim(preg_replace("'".$table."'", $tmpname, $origsql, 1)), 6);
				$createindexsql = array();
				$i = 0;
				$defs = preg_split("/[,]+/",$alterdefs, -1, PREG_SPLIT_NO_EMPTY);
				$prevword = $table;
				$oldcols = preg_split("/[,]+/", substr(trim($createtemptableSQL), strpos(trim($createtemptableSQL), '(')+1), -1, PREG_SPLIT_NO_EMPTY);
				$newcols = array();
				for($i=0; $i<sizeof($oldcols); $i++)
				{
					$colparts = preg_split("/[\s]+/", $oldcols[$i], -1, PREG_SPLIT_NO_EMPTY);
					$oldcols[$i] = $colparts[0];
					$newcols[$colparts[0]] = $colparts[0];
				}
				$newcolumns = '';
				$oldcolumns = '';
				reset($newcols);
				while(list($key, $val) = each($newcols))
				{
					$newcolumns .= ($newcolumns?', ':'').$val;
					$oldcolumns .= ($oldcolumns?', ':'').$key;
				}
				$copytotempsql = 'INSERT INTO '.$tmpname.'('.$newcolumns.') SELECT '.$oldcolumns.' FROM '.$table;
				$dropoldsql = 'DROP TABLE '.$table;
				$createtesttableSQL = $createtemptableSQL;
				foreach($defs as $def)
				{
					$defparts = preg_split("/[\s]+/", $def,-1, PREG_SPLIT_NO_EMPTY);
					$action = strtolower($defparts[0]);
					switch($action)
					{
						case 'add':
							if(sizeof($defparts) <= 2)
								return false;
							$createtesttableSQL = substr($createtesttableSQL, 0, strlen($createtesttableSQL)-1).',';
							for($i=1;$i<sizeof($defparts);$i++)
								$createtesttableSQL.=' '.$defparts[$i];
							$createtesttableSQL.=')';
							break;
						case 'change':
							if(sizeof($defparts) <= 3)
							{
								return false;
							}
							if($severpos = strpos($createtesttableSQL,' '.$defparts[1].' '))
							{
								if($newcols[$defparts[1]] != $defparts[1])
									return false;
								$newcols[$defparts[1]] = $defparts[2];
								$nextcommapos = strpos($createtesttableSQL,',',$severpos);
								$insertval = '';
								for($i=2;$i<sizeof($defparts);$i++)
									$insertval.=' '.$defparts[$i];
								if($nextcommapos)
									$createtesttableSQL = substr($createtesttableSQL,0,$severpos).$insertval.substr($createtesttableSQL,$nextcommapos);
								else
									$createtesttableSQL = substr($createtesttableSQL,0,$severpos-(strpos($createtesttableSQL,',')?0:1)).$insertval.')';
							}
							else
								return false;
							break;
						case 'drop':
							if(sizeof($defparts) < 2)
								return false;
							if($severpos = strpos($createtesttableSQL,' '.$defparts[1].' '))
							{
								$nextcommapos = strpos($createtesttableSQL,',',$severpos);
								if($nextcommapos)
									$createtesttableSQL = substr($createtesttableSQL,0,$severpos).substr($createtesttableSQL,$nextcommapos + 1);
								else
									$createtesttableSQL = substr($createtesttableSQL,0,$severpos-(strpos($createtesttableSQL,',')?0:1) - 1).')';
								unset($newcols[$defparts[1]]);
							}
							else
								return false;
							break;
						default:
							return false;
					}
					$prevword = $defparts[sizeof($defparts)-1];
				}
				//this block of code generates a test table simply to verify that the columns specifed are valid in an sql statement
				//this ensures that no reserved words are used as columns, for example
				$tempResult = $this->query($createtesttableSQL);
				if(!$tempResult)
					return false;
				$droptempsql = 'DROP TABLE '.$tmpname;
				$tempResult = $this->query($droptempsql);
				//end block
          
				$createnewtableSQL = 'CREATE '.substr(trim(preg_replace("'".$tmpname."'", $table, $createtesttableSQL, 1)), 17);
				$newcolumns = '';
				$oldcolumns = '';
				reset($newcols);
				while(list($key,$val) = each($newcols))
				{
					$newcolumns .= ($newcolumns?', ':'').$val;
					$oldcolumns .= ($oldcolumns?', ':'').$key;
				}
				$copytonewsql = 'INSERT INTO '.$table.'('.$newcolumns.') SELECT '.$oldcolumns.' FROM '.$tmpname;
          
				$this->query($createtemptableSQL); //create temp table
				$this->query($copytotempsql); //copy to table
				$this->query($dropoldsql); //drop old table
          
				$this->query($createnewtableSQL); //recreate original table
				$this->query($copytonewsql); //copy back to original table
				$this->query($droptempsql); //drop temp table
			}
			else
			{
				return false;
			}
			return true;
		}
	}
	
	//get number of rows in table
	public function numRows($table)
	{
		$result = $this->select("SELECT Count(*) FROM ".$table);
		return $result[0];
	}
	
	//correctly escape a string to be injected into an SQL query
	public function quote($value)
	{
		if($this->type=="PDO")
		{
			return $this->db->quote($value);	
		}
		else if($this->type=="SQLite3")
		{
			return $this->db->escapeString($value);
		}
		else
		{
			return "'".$value."'";
		}
	}
	
	//correctly format a string value from a table before showing it 
	public function formatString($value)
	{
		return htmlspecialchars(stripslashes($value));	
	}
}

// here begins the HTML.
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
<title><?php echo PROJECT ?></title>
<!-- JavaScript Support -->
<script type="text/javascript">
//finds and checks all checkboxes for all rows on the Browse tab for a table
function checkAll(field)
{
	var i=0;
	while(document.getElementById('check_'+i)!=undefined)
	{
		document.getElementById('check_'+i).checked = true;
		i++;	
	}
}
//finds and unchecks all checkboxes for all rows on the Browse tab for a table
function uncheckAll(field)
{
	var i=0;
	while(document.getElementById('check_'+i)!=undefined)
	{
		document.getElementById('check_'+i).checked = false;
		i++;	
	}
}
</script>
<!-- CSS stylesheet for look and feel of application - fully customizable -->
<style type="text/css">
body
{
	margin:0px;
	padding:0px;
	font-family:Arial, Helvetica, sans-serif;
	font-size:14px;
	color:black;
	background-color:#e0ebf6;
}
ul
{
	list-style:none;
	padding-left:15px;
	margin-left:0px;	
}
li
{
	padding-left:0px;
	margin-left:0px;	
}
a
{
	color:#03F;
	text-decoration:none;
	cursor:pointer;
}
a:hover
{
	color:#06F;
}
h1
{
	margin:0px;
	padding:5px;
	font-size:24px;
	background-color:#f3cece;
	text-align:center;
	margin-bottom:10px;
	text-shadow: 1px 1px 1px #e0ebf6;
	color:#03F;
}
h2
{
	margin:0px;
	padding:0px;
	font-size:14px;
	margin-bottom:20px;
}
input, select, textarea
{
	font-family:Arial, Helvetica, sans-serif;
	background-color:#eaeaea;
	color:#03F;
	border-color:#03F;
	border-style:solid;
	border-width:1px;
	margin:5px;
}
fieldset
{
	padding:15px;
	border-color:#03F;
	border-width:1px;
	border-style:solid;
}
#container
{
	padding:15px;
}
#leftNav
{
	float:left;
	width:250px;
	padding:0px;
	border-color:#03F;
	border-width:1px;
	border-style:solid;
	background-color:#FFF;
	padding-bottom:15px;
}
#content
{
	overflow:hidden;
	padding-left:15px;
}
#contentInner
{
	overflow:hidden;
}
#loginBox
{
	width:380px;
	margin-left:auto;
	margin-right:auto;
	margin-top:50px;
	border-color:#03F;
	border-width:1px;
	border-style:solid;
	background-color:#FFF;
}
#main
{
	border-color:#03F;
	border-width:1px;
	border-style:solid;
	padding:15px;
	overflow:auto;
	background-color:#FFF;
}
.td1
{
	border-bottom-color:#03F;
	border-bottom-width:1px;
	border-bottom-style:none;
	background-color:#f9e3e3;
	text-align:right;
	font-size:12px;
}
.td2
{
	border-bottom-color:#03F;
	border-bottom-width:1px;
	border-bottom-style:none;
	background-color:#f3cece;
	text-align:right;
	font-size:12px;
}
.tdheader
{
	border-color:#03F;
	border-width:1px;
	border-style:solid;
	font-weight:bold;
	font-size:12px;
	padding-left:5px;
	padding-right:5px;
	background-color:#e0ebf6;
}
.confirm
{
	border-color:#03F;
	border-width:1px;
	border-style:dashed;
	padding:15px;
	background-color:#e0ebf6;
}
.tab
{
	display:block;
	width:80px;
	padding:5px;
	border-color:#03F;
	border-width:1px;
	border-style:solid;
	margin-right:15px;
	float:left;
	border-bottom-style:none;
	position:relative;
	top:1px;
	padding-bottom:4px;
	background-color:#eaeaea;
}
.tab_pressed
{
	display:block;
	width:80px;
	padding:5px;
	border-color:#03F;
	border-width:1px;
	border-style:solid;
	margin-right:15px;
	float:left;
	border-bottom-style:none;
	position:relative;
	top:1px;
	background-color:#FFF;
}
</style>
</head>
<body>
<?php
$auth = new Authorization(); //create authorization object
if(isset($_POST['logout'])) //user has attempted to log out
	$auth->revoke();
else if(isset($_POST['login'])) //user has attempted to log in
{
	if($_POST['password']==$password) //make sure passwords match before granting authorization
		$auth->grant();
}
if(!$auth->isAuthorized()) //user is not authorized - display the login screen
{
	echo "<div id='loginBox'>";
	echo "<h1>".PROJECT." <span style='font-size:14px; color:#000;'>v".VERSION."</span></h1>";
	echo "<div style='padding:15px;'>";
	echo "<form action='".PAGE."' method='post'>";
	echo "Password: <input type='password' name='password'/>";
	echo "<input type='submit' value='Log In' name='login'/>";
	echo "</form>";
	echo "</div>";
	echo "</div>";
	echo "<br/>";
	echo "<div style='text-align:center;'>";
	$endTimeTot = microtime(true);
	$timeTot = round(($endTimeTot - $startTimeTot), 4);
	echo "<span style='font-size:11px;'>Powered by <a href='http://code.google.com/p/phpliteadmin/' target='_blank' style='font-size:11px;'>".PROJECT."</a> | Page generated in ".$timeTot." seconds.</span>";
	echo "</div>";
}
else //user is authorized - display the main application
{
	//set the current database to the first in the array (default)
	if(sizeof($databases)>0)
		$currentDB = $databases[0];
	else //the database array is empty - show error and halt execution
		die("Error: you have not specified any databases to manage.");
		
	if(isset($_POST['database_switch'])) //user is switching database with drop-down menu
	{
		$_SESSION["currentDB"] = $_POST['database_switch'];
		$currentDB = $databases[$_SESSION['currentDB']];
	}
	if(isset($_SESSION['currentDB']))
		$currentDB = $databases[$_SESSION['currentDB']];
	
	//create the objects
	$db = new Database($currentDB); //create the Database object
		
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
				$query = "CREATE TABLE ".$name."(";
				for($i=0; $i<$num; $i++)
				{
					if($_POST[$i.'_field']!="")
					{
						$query .= $_POST[$i.'_field']." ";
						$query .= $_POST[$i.'_type']." ";
						if(isset($_POST[$i.'_primarykey']))
							$query .= "PRIMARY KEY ";
						if(isset($_POST[$i.'_notnull']))
							$query .= "NOT NULL ";
						if($_POST[$i.'_defaultvalue']!="")
						{
							if($_POST[$i.'_type']=="INTEGER")
								$query .= "default ".$_POST[$i.'_defaultvalue']."  ";
							else
								$query .= "default '".$_POST[$i.'_defaultvalue']."' ";
						}
						$query = substr($query, 0, sizeof($query)-2);
						$query .= ", ";
					}
				}
				$query = substr($query, 0, sizeof($query)-3);
				$query .= ")";
				$result = $db->query($query);
				if(!$result)
					$error = true;
				$completed = "Table '".$_POST['tablename']."' has been created.<br/><span style='font-size:11px;'>".$query."</span>";
				break;
			/////////////////////////////////////////////// empty table
			case "table_empty":
				$query = "DELETE FROM ".$_POST['tablename'];
				$result = $db->query($query);
				if(!$result)
					$error = true;
				$query = "VACUUM";
				$result = $db->query($query);
				if(!$result)
					$error = true;
				$completed = "Table '".$_POST['tablename']."' has been emptied.<br/><span style='font-size:11px;'>".$query."</span>";
				break;
			/////////////////////////////////////////////// drop table
			case "table_drop":
				$query = "DROP TABLE ".$_POST['tablename'];
				$db->query($query);
				$completed = "Table '".$_POST['tablename']."' has been dropped.";
				break;
			/////////////////////////////////////////////// rename table
			case "table_rename":
				$query = "ALTER TABLE ".$_POST['oldname']." RENAME TO ".$_POST['newname'];
				if($db->getVersion()==3)
					$result = $db->query($query, true);
				else
					$result = $db->query($query, false);
				if(!$result)
					$error = true;
				$completed = "Table '".$_POST['oldname']."' has been renamed to '".$_POST['newname']."'.<br/><span style='font-size:11px;'>".$query."</span>";
				break;
			//row actions
			/////////////////////////////////////////////// create row
			case "row_create":
				$query = "INSERT INTO ".$_GET['table']." (";
				$i = 0;
				foreach($_POST as $vblname => $value)
				{
					$query .= $vblname.",";
				}
				$query = substr($query, 0, sizeof($query)-2);
				$query .= ") VALUES (";
				$i = 0;
				foreach($_POST as $vblname => $value)
				{
					if($value=="")
						$query .= "NULL,";
					else
						$query .= $db->quote($value).",";
				}
				$query = substr($query, 0, sizeof($query)-2);
				$query .= ")";
				$result = $db->query($query);
				if(!$result)
					$error = true;
				$completed = "1 row(s) inserted.<br/><span style='font-size:11px;'>".$query."</span>";
				break;
			/////////////////////////////////////////////// delete row
			case "row_delete":
				$pks = explode(":", $_GET['pk']);
				$str = $pks[0];
				$query = "DELETE FROM ".$_GET['table']." WHERE ROWID = ".$pks[0];
				for($i=1; $i<sizeof($pks); $i++)
				{
					$str .= ", ".$pks[$i];
					$query .= " OR ROWID = ".$pks[$i];
				}
				$result = $db->query($query);
				if(!$result)
					$error = true;
				$completed = sizeof($pks)." row(s) deleted.<br/><span style='font-size:11px;'>".$query."</span>";
				break;
			/////////////////////////////////////////////// edit row
			case "row_edit":
				$query = "UPDATE ".$_GET['table']." SET ";
				foreach($_POST as $vblname => $value)
				{
					$query .= $vblname."=".$db->quote($value).", ";
				}
				$query = substr($query, 0, sizeof($query)-3);
				$query .= " WHERE ROWID = ".$_GET['pk'];
				$result = $db->query($query);
				if(!$result)
					$error = true;
				$completed = "1 row(s) affected.<br/><span style='font-size:11px;'>".$query."</span>";
				break;
			//column actions
			/////////////////////////////////////////////// create column
			case "column_create":
				$num = intval($_POST['rows']);
				for($i=0; $i<$num; $i++)
				{
					if($_POST[$i.'_field']!="")
					{
						$query = "ALTER TABLE ".$_GET['table']." ADD ".$_POST[$i.'_field']." ";
						$query .= $_POST[$i.'_type']." ";
						if(isset($_POST[$i.'_primarykey']))
							$query .= "PRIMARY KEY ";
						if(isset($_POST[$i.'_notnull']))
							$query .= "NOT NULL ";
						if($_POST[$i.'_defaultvalue']!="")
						{
							if($_POST[$i.'_type']=="INTEGER")
								$query .= "DEFAULT ".$_POST[$i.'_defaultvalue']."  ";
							else
								$query .= "DEFAULT '".$_POST[$i.'_defaultvalue']."' ";
						}
						if($db->getVersion()==3)
							$result = $db->query($query, true);
						else
							$result = $db->query($query, false);
						if(!$result)
							$error = true;
					}
				}
				$completed = "Table '".$_GET['table']."' has been altered successfully.";
				break;
			/////////////////////////////////////////////// delete column
			case "column_delete":
				$pks = explode(":", $_GET['pk']);
				$str = $pks[0];
				$query = "ALTER TABLE ".$_GET['table']." DROP ".$pks[0];
				for($i=1; $i<sizeof($pks); $i++)
				{
					$str .= ", ".$pks[$i];
					$query .= ", DROP ".$pks[$i];
				}
				$result = $db->query($query);
				if(!$result)
					$error = true;
				$completed = "Table '".$_GET['table']."' has been altered successfully.";
				break;
			/////////////////////////////////////////////// edit column
			case "column_edit":
				$col = $_POST['columnname'];
				$query = "ALTER TABLE ".$_GET['table']." CHANGE ".$_POST['field_old']." ".$_POST['field']." ".$_POST['type'];
				$result = $db->query($query);
				if(!$result)
					$error = true;
				$completed = "Table '".$_GET['table']."' has been altered successfully.";
				break;
		}
	}
	
	echo "<div id='container'>";
	echo "<div id='leftNav'>";
	echo "<h1>".PROJECT." <span style='font-size:14px; color:#000;'>v".VERSION."</span></h1>";
	echo "<fieldset style='margin:15px;'><legend><b>Change Database</b></legend>";
	echo "<form action='".PAGE."' method='post'>";
	echo "<select name='database_switch'>";
	for($i=0; $i<sizeof($databases); $i++)
	{
		if($i==$_SESSION['currentDB'])
			echo "<option value='".$i."' selected='selected'>".$databases[$i]['name']."</option>";
		else
			echo "<option value='".$i."'>".$databases[$i]['name']."</option>";
	}
	echo "</select> ";
	echo "<input type='submit' value='Go'>";
	echo "</form>";
	echo "</fieldset>";
	echo "<fieldset style='margin:15px;'><legend><a href='".PAGE."'>".$currentDB['name']."</a></legend>";
	//Display list of tables
	$query = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name";
	$result = $db->selectArray($query);
	$j=0;
	for($i=0; $i<sizeof($result); $i++)
	{
		if(substr($result[$i]['name'], 0, 7)!="sqlite_" && $result[$i]['name']!="")
		{
			echo "<a href='".PAGE."?action=row_view&table=".$result[$i]['name']."'>".$result[$i]['name']."</a><br/>";
			$j++;
		}
	}
	if($j==0) 
		echo "No tables in database.";
	echo "</fieldset>";
	echo "<div style='text-align:center;'>";
	echo "<form action='".PAGE."' method='post'/>";
	echo "<input type='submit' value='Log Out' name='logout'/>";
	echo "</form>";
	echo "</div>";
	echo "</div>";
	echo "<div id='content'>";
	echo "<div id='contentInner'>";
	echo "<h2>Database: ".$currentDB["name"]." (".$currentDB["path"].")</h2>";
	
	//user has performed some action so show the resulting message
	if(isset($_GET['confirm']))
	{
		echo "<div id='main'>";
		echo "<div class='confirm'>";
		if($error) //an error occured during the action, so show an error message
			echo "An error occured. This may be a bug that needs to be reported at <a href='http://code.google.com/p/phpliteadmin/issues/list' target='_blank'>code.google.com/p/phpliteadmin/issues/list</a>";
		else //action was performed successfully - show success message
			echo $completed;
		echo "</div>";
		if($_GET['action']=="row_delete" || $_GET['action']=="row_create" || $_GET['action']=="row_edit")
			echo "<br/><br/><a href='".PAGE."?table=".$_GET['table']."&action=row_view'>Return</a>";
		else
			echo "<br/><br/><a href='".PAGE."'>Return</a>";
		echo "</div>";
	}
	
	//show the various tab views for a table
	if(!isset($_GET['confirm']) && isset($_GET['table']) && isset($_GET['action']) && ($_GET['action']=="row_view" || $_GET['action']=="row_create" || $_GET['action']=="column_view" || $_GET['action']=="table_rename"))
	{
		echo "<a href='".PAGE."?table=".$_GET['table']."&action=row_view' ";
		if($_GET['action']=="row_view")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">Browse</a>";
		echo "<a href='".PAGE."?table=".$_GET['table']."&action=column_view' ";
		if($_GET['action']=="column_view")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">Structure</a>";
		echo "<a href='".PAGE."?table=".$_GET['table']."&action=row_create' ";
		if($_GET['action']=="row_create")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">Insert</a>";
		echo "<a href='".PAGE."?table=".$_GET['table']."&action=table_rename' ";
		if($_GET['action']=="table_rename")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">Rename</a>";
		echo "<a href='".PAGE."?action=table_empty&table=".$_GET['table']."' ";
		echo "class='tab' style='color:red;'";
		echo ">Empty</a>";
		echo "<a href='".PAGE."?action=table_drop&table=".$_GET['table']."' ";
		echo "class='tab' style='color:red;'";
		echo ">Drop</a>";
		echo "<div style='clear:both;'></div>";
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
				echo "<h2>Creating new table: '".$_POST['tablename']."'</h2>";
				if($_POST['tablefields']=="" || intval($_POST['tablefields'])<=0)
					echo "You must specify the number of table fields.";
				else if($_POST['tablename']=="")
					echo "You must specify a table name.";
				else
				{
					$num = intval($_POST['tablefields']);
					$name = $_POST['tablename'];
					echo "<form action='".PAGE."?action=table_create&confirm=1' method='post'>";
					echo "<input type='hidden' name='tablename' value='".$name."'/>";
					echo "<input type='hidden' name='rows' value='".$num."'/>";
					echo "<table border='0' cellpadding='2' cellspacing='1'>";
					echo "<tr>";
					$headings = array("Field", "Type", "Primary Key", "Autoincrement", "Not NULL", "Default Value");
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
						echo "<select name='".$i."_type'>";
						echo "<option value='INTEGER' selected='selected'>INTEGER</option>";
						echo "<option value='REAL'>REAL</option>";
						echo "<option value='TEXT'>TEXT</option>";
						echo "<option value='BLOB'>BLOB</option>";
						echo "<option value='NULL'>NULL</option>";
						echo "</select>";
						echo "</td>";
						echo $tdWithClass;
						echo "<input type='checkbox' name='".$i."_primarykey'/> Yes";
						echo "</td>";
						echo $tdWithClass;
						echo "<input type='checkbox' name='".$i."_autoincrement'/> Yes";
						echo "</td>";
						echo $tdWithClass;
						echo "<input type='checkbox' name='".$i."_notnull'/> Yes";
						echo "</td>";
						echo $tdWithClass;
						echo "<input type='text' name='".$i."_defaultvalue' style='width:100px;'/>";
						echo "</td>";
						echo "</tr>";
					}
					echo "</table>";
					echo "<input type='submit' value='Create'/> ";
					echo "<a href='".PAGE."'>Cancel</a>";
					echo "</form>";
				}
				break;
			/////////////////////////////////////////////// empty table
			case "table_empty":
				echo "<form action='".PAGE."?action=table_empty&confirm=1' method='post'>";
				echo "<input type='hidden' name='tablename' value='".$_GET['table']."'/>";
				echo "<div class='confirm'>";
				echo "Are you sure you want to empty the table '".$_GET['table']."'?<br/><br/>";
				echo "<input type='submit' value='Confirm'/> ";
				echo "<a href='".PAGE."'>Cancel</a>";
				echo "</div>";
				break;
			/////////////////////////////////////////////// drop table
			case "table_drop":
				echo "<form action='".PAGE."?action=table_drop&confirm=1' method='post'>";
				echo "<input type='hidden' name='tablename' value='".$_GET['table']."'/>";
				echo "<div class='confirm'>";
				echo "Are you sure you want to drop the table '".$_GET['table']."'?<br/><br/>";
				echo "<input type='submit' value='Confirm'/> ";
				echo "<a href='".PAGE."'>Cancel</a>";
				echo "</div>";
				break;
			/////////////////////////////////////////////// rename table
			case "table_rename":
				echo "<form action='".PAGE."?action=table_rename&confirm=1' method='post'>";
				echo "<input type='hidden' name='oldname' value='".$_GET['table']."'/>";
				echo "Rename table '".$_GET['table']."' to <input type='text' name='newname' style='width:200px;'/> <input type='submit' value='Rename' name='rename'/>";
				echo "</form>";
				break;
			//row actions
			/////////////////////////////////////////////// view row
			case "row_view":
				if(isset($_POST['startRow']))
					$_SESSION['startRow'] = $_POST['startRow'];
		
				if(isset($_POST['numRows']))
					$_SESSION['numRows'] = $_POST['numRows'];
			
				if(!isset($_SESSION['startRow']))
					$_SESSION['startRow'] = 0;
			
				if(!isset($_SESSION['numRows']))
					$_SESSION['numRows'] = 30;
		
				echo "<form action='".PAGE."?action=row_view&table=".$_GET['table']."' method='post'>";
				echo "<input type='submit' value='Show : ' name='show'/> ";
				echo "<input type='text' name='numRows' style='width:50px;' value='".$_SESSION['numRows']."'/> ";
				echo "row(s) starting from record # ";
				echo "<input type='text' name='startRow' style='width:90px;' value='".intval($_SESSION['startRow']+$_SESSION['numRows'])."'/>";
				echo "</form>";
				if(!isset($_GET['sort']))
					$_GET['sort'] = NULL;
				if(!isset($_GET['order']))
					$_GET['order'] = NULL;
					
				$table = $_GET['table'];
				$numRows = $_SESSION['numRows'];
				$startRow = $_SESSION['startRow'];
				$sort = $_GET['sort'];
				$order = $_GET['order'];
				$_SESSION['numRows'] = $numRows;
				$_SESSION['startRow'] = $startRow;
				$query = "SELECT *, ROWID FROM ".$table;
				$queryDisp = "SELECT * FROM ".$table;
				$queryAdd = "";
				if($sort!=NULL)
					$queryAdd .= " ORDER BY ".$sort;
				if($order!=NULL)
					$queryAdd .= " ".$order;
				$queryAdd .= " LIMIT ".$startRow.", ".$numRows;
				$query .= $queryAdd;
				$queryDisp .= $queryAdd;
				$startTime = microtime(true);
				$arr = $db->selectArray($query);
				$endTime = microtime(true);
				$time = round(($endTime - $startTime), 4);
				$total = $db->numRows($table);
		
				if(sizeof($arr)>0)
				{
					echo "<br/><div class='confirm'>";
					echo "<b>Showing rows ".$startRow." - ".($startRow + sizeof($arr)-1)." (".$total." total, Query took ".$time." sec)</b><br/>";
					echo "<span style='font-size:11px;'>".$queryDisp."</span>";
					echo "</div><br/>";
				}
				else
				{
					echo "<br/><br/>This table is empty.";
					return;
				}
		
				echo "<form action='".PAGE."?action=row_delete&table=".$table."' method='post' name='checkForm'>";
				echo "<table border='0' cellpadding='2' cellspacing='1'>";
				$query = "PRAGMA table_info('".$table."')";
				$result = $db->selectArray($query);
				$rowidColumn = sizeof($result);
		
				echo "<tr>";
				echo "<td colspan='3'>";
				echo "</td>";
		
				for($i=0; $i<sizeof($result); $i++)
				{
					echo "<td class='tdheader'>";
					echo "<a href='".PAGE."?action=row_view&table=".$table."&sort=".$result[$i][1];
					$orderTag = ($sort==$result[$i][1] && $order=="ASC") ? "DESC" : "ASC";
					echo "&order=".$orderTag;
					echo "'>".$result[$i][1]."</a>";
					if($sort==$result[$i][1])
						echo (($order=="ASC") ? " <b>&uarr;</b>" : " <b>&darr;</b>");
					echo "</td>";
				}
				echo "</tr>";
		
				for($i=0; $i<sizeof($arr); $i++)
				{
					// -g-> $pk will always be the last column in each row of the array because we are doing a "SELECT *, ROWID FROM ..."
					$pk = $arr[$i][$rowidColumn];
					$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
					echo "<tr>";
					echo $tdWithClass;
					echo "<input type='checkbox' name='check[]' value='".$pk."' id='check_".$i."'/>";
					echo "</td>";
					echo $tdWithClass;
					// -g-> Here, we need to put the ROWID in as the link for both the edit and delete.
					echo "<a href='".PAGE."?table=".$table."&action=row_edit&pk=".$pk."'>edit</a>";
					echo "</td>";
					echo $tdWithClass;
					echo "<a href='".PAGE."?table=".$table."&action=row_delete&pk=".$pk."' style='color:red;'>delete</a>";
					echo "</td>";
					for($j=0; $j<sizeof($result); $j++)
					{
						echo $tdWithClass;
						// -g-> although the inputs do not interpret HTML on the way "in", when we print the contents of the database the interpretation cannot be avoided.
						echo $db->formatString($arr[$i][$j]);
						echo "</td>";
					}
					echo "</tr>";
				}
				echo "</table>";
				echo "<a onclick='checkAll()'>Check All</a> / <a onclick='uncheckAll()'>Uncheck All</a> <i>With selected:</i> ";
				echo "<select name='massType'>";
				//echo "<option value='edit'>Edit</option>";
				echo "<option value='delete'>Delete</option>";
				echo "</select> ";
				echo "<input type='submit' value='Go' name='massGo'/>";
				echo "</form>";
				break;
			/////////////////////////////////////////////// create row
			case "row_create":
				$query = "PRAGMA table_info('".$_GET['table']."')";
				$result = $db->selectArray($query);
		
				echo "<form action='".PAGE."?table=".$_GET['table']."&action=row_create&confirm=1' method='post'>";
				echo "<table border='0' cellpadding='2' cellspacing='1'>";
				echo "<tr>";
				echo "<td class='tdheader'>Field</td>";
				echo "<td class='tdheader'>Type</td>";
				echo "<td class='tdheader'>Value</td>";
				echo "</tr>";
		
				for($i=0; $i<sizeof($result); $i++)
				{
					$field = $result[$i][1];
					$type = $result[$i][2];
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
					if($type=="INTEGER" || $type=="REAL" || $type=="NULL")
						echo "<input type='text' name='".$field."'/>";
					else
						echo "<textarea name='".$field."' wrap='hard' rows='1' cols='60'></textarea>";
					echo "</td>";
					echo "</tr>";
				}
				echo "</table>";
				echo "<input type='submit' value='Insert'/>";
				echo "</form>";
				break;
			/////////////////////////////////////////////// delete row
			case "row_delete":
				if(isset($_POST['check']))
					$pks = $_POST['check'];
				else if(isset($_GET['pk']))
					$pks = array($_GET['pk']);
				$str = $pks[0];
				$pkVal = $pks[0];
				for($i=1; $i<sizeof($pks); $i++)
				{
					$str .= ", ".$pks[$i];
					$pkVal .= ":".$pks[$i];
				}
					
				echo "<form action='".PAGE."?table=".$_GET['table']."&action=row_delete&confirm=1&pk=".$pkVal."' method='post'>";
				echo "<div class='confirm'>";
				echo "Are you sure you want to delete row(s) ".$str." from table '".$_GET['table']."'?<br/><br/>";
				echo "<input type='submit' value='Confirm'/> ";
				echo "<a href='".PAGE."?table=".$_GET['table']."&action=row_view'>Cancel</a>";
				echo "</div>";
				break;
			/////////////////////////////////////////////// edit row
			case "row_edit":
				echo "<form action='".PAGE."?table=".$_GET['table']."&action=row_edit&confirm=1&pk=".$_GET['pk']."' method='post'>";
				$query = "SELECT * FROM ".$_GET['table']." WHERE ROWID = ".$_GET['pk'];
				$result1 = $db->select($query);
				$query = "PRAGMA table_info('".$_GET['table']."')";
				$result = $db->selectArray($query);
		
				echo "<table border='0' cellpadding='2' cellspacing='1'>";
				echo "<tr>";
				echo "<td class='tdheader'>Field</td>";
				echo "<td class='tdheader'>Type</td>";
				echo "<td class='tdheader'>Value</td>";
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
					if($type=="INTEGER" || $type=="REAL" || $type=="NULL")
						echo "<input type='text' name='".$field."' value='".$db->formatString($value)."'/>";
					else
						echo "<textarea name='".$field."' wrap='hard' rows='1' cols='60'>".$db->formatString($value)."</textarea>";
					echo "</td>";
					echo "</tr>";
				}

				echo "</table>";
				echo "<br/><br/>";
				echo "<input type='submit' value='Save Changes'/> ";
				echo "<a href='".PAGE."?table=".$_GET['table']."&action=row_view'>Cancel</a>";
				echo "</form>";
				break;
			//column actions
			/////////////////////////////////////////////// view column
			case "column_view":
				$query = "PRAGMA table_info('".$_GET['table']."')";
				$result = $db->selectArray($query);
		
				echo "<form action='".PAGE."?table=".$_GET['table']."&action=column_delete' method='post' name='checkForm'>";
				echo "<table border='0' cellpadding='2' cellspacing='1'>";
				echo "<tr>";
				echo "<td colspan='3'>";
				echo "</td>";
				echo "<td class='tdheader'>Column #</td>";
				echo "<td class='tdheader'>Field</td>";
				echo "<td class='tdheader'>Type</td>";
				echo "<td class='tdheader'>Not Null</td>";
				echo "<td class='tdheader'>Default Value</td>";
				echo "<td class='tdheader'>Primary Key</td>";
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
						$notnullVal = "yes";
					else
						$notnullVal = "no";
					if(intval($primarykeyVal)!=0)
						$primarykeyVal = "yes";
					else
						$primarykeyVal = "no";
			
					$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
					echo "<tr>";
					echo $tdWithClass;
					echo "<input type='checkbox' name='check[]' value='".$fieldVal."' id='check_".$i."'/>";
					echo "</td>";
					echo $tdWithClass;
					echo "<a href='".PAGE."?table=".$_GET['table']."&action=column_edit&pk=".$colVal."'>edit</a>";
					echo "</td>";
					echo $tdWithClass;
					echo "<a href='".PAGE."?table=".$_GET['table']."&action=column_delete&pk=".$fieldVal."' style='color:red;'>delete</a>";
					echo "</td>";
					echo $tdWithClass;
					echo $colVal;
					echo "</td>";
					echo $tdWithClass;
					echo $fieldVal;
					echo "</td>";
					echo $tdWithClass;
					echo $typeVal;
					echo "</td>";
					echo $tdWithClass;
					echo $notnullVal;
					echo "</td>";
					echo $tdWithClass;
					echo $defaultVal;
					echo "</td>";
					echo $tdWithClass;
					echo $primarykeyVal;
					echo "</td>";
					echo "</tr>";
				}
		
				echo "</table>";
		
				echo "<a onclick='checkAll()'>Check All</a> / <a onclick='uncheckAll()'>Uncheck All</a> <i>With selected:</i> ";
				echo "<select name='massType'>";
				//echo "<option value='edit'>Edit</option>";
				echo "<option value='delete'>Delete</option>";
				echo "</select> ";
				echo "<input type='hidden' name='structureDel' value='true'/>";
				echo "<input type='submit' value='Go' name='massGo'/>";
				echo "</form>";
		
				echo "<br/>";
				echo "<form action='".PAGE."?table=".$_GET['table']."&action=column_create' method='post'>";
				echo "<input type='hidden' name='tablename' value='".$_GET['table']."'/>";
				echo "Add <input type='text' name='tablefields' style='width:30px;' value='1'/> field(s) at end of table <input type='submit' value='Go' name='addfields'/>";
				echo "</form>";
				break;
			/////////////////////////////////////////////// create column
			case "column_create":
				echo "<h2>Adding new field(s) to table '".$_POST['tablename']."'</h2>";
				if($_POST['tablefields']=="" || intval($_POST['tablefields'])<=0)
					echo "You must specify the number of table fields.";
				else if($_POST['tablename']=="")
					echo "You must specify a table name.";
				else
				{
					$num = intval($_POST['tablefields']);
					$name = $_POST['tablename'];
					echo "<form action='".PAGE."?table=".$_POST['tablename']."&action=column_create&confirm=1' method='post'>";
					echo "<input type='hidden' name='tablename' value='".$name."'/>";
					echo "<input type='hidden' name='rows' value='".$num."'/>";
					echo "<table border='0' cellpadding='2' cellspacing='1'>";
					echo "<tr>";
					$headings = array("Field", "Type", "Primary Key", "Autoincrement", "Not NULL", "Default Value");
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
						echo "<select name='".$i."_type'>";
						echo "<option value='INTEGER' selected='selected'>INTEGER</option>";
						echo "<option value='REAL'>REAL</option>";
						echo "<option value='TEXT'>TEXT</option>";
						echo "<option value='BLOB'>BLOB</option>";
						echo "<option value='NULL'>NULL</option>";
						echo "</select>";
						echo "</td>";
						echo $tdWithClass;
						echo "<input type='checkbox' name='".$i."_primarykey'/> Yes";
						echo "</td>";
						echo $tdWithClass;
						echo "<input type='checkbox' name='".$i."_autoincrement'/> Yes";
						echo "</td>";
						echo $tdWithClass;
						echo "<input type='checkbox' name='".$i."_notnull'/> Yes";
						echo "</td>";
						echo $tdWithClass;
						echo "<input type='text' name='".$i."_defaultvalue' style='width:100px;'/>";
						echo "</td>";
						echo "</tr>";
					}
					echo "</table>";
					echo "<input type='submit' value='Add Field(s)'/> ";
					echo "<a href='".PAGE."'>Cancel</a>";
					echo "</form>";
				}
				break;
			/////////////////////////////////////////////// delete column
			case "column_delete":
				if(isset($_POST['check']))
					$pks = $_POST['check'];
				else if(isset($_GET['pk']))
					$pks = array($_GET['pk']);
				$str = $pks[0];
				$pkVal = $pks[0];
				for($i=1; $i<sizeof($pks); $i++)
				{
					$str .= ", ".$pks[$i];
					$pkVal .= ":".$pks[$i];
				}
					
				echo "<form action='".PAGE."?table=".$_GET['table']."&action=column_delete&confirm=1&pk=".$pkVal."' method='post'>";
				echo "<div class='confirm'>";
				echo "Are you sure you want to delete column(s) ".$str." from table '".$_GET['table']."'?<br/><br/>";
				echo "<input type='submit' value='Confirm'/> ";
				echo "<a href='".PAGE."?table=".$_GET['table']."&action=column_view'>Cancel</a>";
				echo "</div>";
				break;
			/////////////////////////////////////////////// edit column
			case "column_edit":
				$col = $_POST['columnname'];
				$query = "ALTER TABLE ".$_GET['table']." CHANGE ".$_POST['field_old']." ".$_POST['field']." ".$_POST['type'];
				$result = $db->query($query);
				if(!$result)
					$error = true;
				break;
		}
		echo "</div>";
	}
	$view = "structure";
	if(!isset($_GET['table']) && !isset($_GET['confirm']))
	{
		if(isset($_GET['view']))
			$view = $_GET['view'];
		else
			$view = "structure";
				
		echo "<a href='".PAGE."?view=structure' ";
		if($view=="structure")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">Structure</a>";
		echo "<a href='".PAGE."?view=sql' ";
		if($view=="sql")
			echo "class='tab_pressed'";
		else
			echo "class='tab'";
		echo ">SQL</a>";
		echo "<div style='clear:both;'></div>";
		echo "<div id='main'>";
		
		if($view=="structure")
		{
			echo "<b>Database name</b>: ".$db->getName()."<br/>";
			echo "<b>Path to database</b>: ".$db->getPath()."<br/>";
			echo "<b>Size of database</b>: ".$db->getSize()."<br/>";
			echo "<b>Database last modified</b>: ".$db->getDate()."<br/>";
			echo "<b>SQLite version of database</b>: ".$db->getVersion()."<br/>";
			echo "<b>PHP extension used</b>: ".$db->getType()."<br/><br/>";
			
			$query = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name";
			$result = $db->selectArray($query);
		
			$j = 0;
			for($i=0; $i<sizeof($result); $i++)
				if(substr($result[$i]['name'], 0, 7)!="sqlite_" && $result[$i]['name']!="")
					$j++;
		
			if($j==0)
				echo "No tables in database.<br/><br/>";
			else
			{
				echo "<table border='0' cellpadding='2' cellspacing='1'>";
				echo "<tr>";
				echo "<td class='tdheader'>Table</td>";
				echo "<td class='tdheader' colspan='5'>Action</td>";
				echo "<td class='tdheader'>Records</td>";
				echo "</tr>";
			
				for($i=0; $i<sizeof($result); $i++)
				{
					if(substr($result[$i]['name'], 0, 7)!="sqlite_" && $result[$i]['name']!="")
					{
						$records = $db->numRows($result[$i]['name']);
					
						$tdWithClass = "<td class='td" . ($i%2 ? "1" : "2") . "'>";
						echo "<tr>";
						if($i%2)
							echo "<td class='td1' style='text-align:left;'>";
						else
							echo "<td class='td2' style='text-align:left;'>";
						echo "<a href='".PAGE."?table=".$result[$i]['name']."&action=row_view'>".$result[$i]['name']."</a><br/>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='".PAGE."?table=".$result[$i]['name']."&action=table_drop' style='color:red;'>Drop</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='".PAGE."?table=".$result[$i]['name']."&action=table_empty' style='color:red;'>Empty</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='".PAGE."?table=".$result[$i]['name']."&action=column_view'>Structure</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='".PAGE."?table=".$result[$i]['name']."&action=row_view'>Browse</a>";
						echo "</td>";
						echo $tdWithClass;
						echo "<a href='".PAGE."?table=".$result[$i]['name']."&action=row_create'>Insert</a>";
						echo "</td>";
						echo $tdWithClass;
						echo $records;
						echo "</td>";
						echo "</tr>";
					}
				}
				echo "</table>";
				echo "<br/>";
			}
			echo "<fieldset>";
			echo "<legend><b>Create new table on database '".$db->getName()."'</b></legend>";
			echo "<form action='".PAGE."' method='post'>";
			echo "Name: <input type='text' name='tablename' style='width:200px;'/> ";
			echo "Number of Fields: <input type='text' name='tablefields' style='width:90px;'/> ";
			echo "<input type='submit' name='createtable' value='Go'/>";
			echo "</form>";
			echo "</fieldset>";
		}
		else if($view=="sql")
		{
			$isSelect = false;
			if(isset($_POST['query']) && $_POST['query']!="")
			{
				$delimiter = $_POST['delimiter'];
				$queryStr = stripslashes($_POST['queryval']);
				$query = explode($_POST['delimiter'], $queryStr); //explode the query string into individual queries based on the delimiter
			
				for($i=0; $i<sizeof($query); $i++) //iterate through the queries exploded by the delimiter
				{
					if(str_replace(" ", "", str_replace("\n", "", str_replace("\r", "", $query[$i])))!="") //make sure this query is not an empty string
					{
						$startTime = microtime(true);
						if(strpos(strtolower($query[$i]), "select ")!==false)
						{
							$isSelect = true;
							$result = $db->selectArray($query[$i], "assoc");
						}
						else
						{
							$isSelect = false;
							$result = $db->query($query[$i]);
						}
						$endTime = microtime(true);
						$time = round(($endTime - $startTime), 4);
			
						echo "<div class='confirm'>";
						echo "<b>";
						if($isSelect || $result)
						{
							if($isSelect)
							{
								$affected = sizeof($result);
								echo "Showing ".$affected." row(s). ";
							}
							else
							{
								$affected = $db->getAffectedRows();
								echo $affected." row(s) affected. ";
							}
							echo "(Query took ".$time." sec)</b><br/>";
						}
						else
						{
							echo "There is a problem with the syntax of your query ";
							echo "(Query was not executed)</b><br/>";
						}
						echo "<span style='font-size:11px;'>".$query[$i]."</span>";
						echo "</div><br/>";
						if($isSelect)
						{
							if(sizeof($result)==0)
								return;
			
							$headers = array_keys($result[0]);

							echo "<table border='0' cellpadding='2' cellspacing='1'>";
							echo "<tr>";
							for($i=0; $i<sizeof($headers); $i++)
							{
								echo "<td class='tdheader'>";
								echo $headers[$i];
								echo "</td>";
							}
							echo "</tr>";
							for($i=0; $i<sizeof($result); $i++)
							{
								$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
								echo "<tr>";
								for($j=0; $j<sizeof($headers); $j++)
								{
									echo $tdWithClass;
									echo $result[$i][$headers[$j]];
									echo "</td>";
								}
								echo "</tr>";
							}
							echo "</table><br/><br/>";	
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
			echo "<legend><b>Run SQL query/queries on database '".$db->getName()."'</b></legend>";
			echo "<form action='".PAGE."?view=sql' method='post'>";
			echo "<textarea style='width:100%; height:300px;' name='queryval'>".$queryStr."</textarea>";
			echo "Delimiter <input type='text' name='delimiter' value='".$delimiter."' style='width:50px;'/> ";
			echo "<input type='submit' name='query' value='Go'/>";
			echo "</form>";	
		}
				
		echo "</div>";
	}
	
	echo "</div>";
	echo "<br/>";
	$endTimeTot = microtime(true);
	$timeTot = round(($endTimeTot - $startTimeTot), 4);
	echo "<span style='font-size:11px;'>Powered by <a href='http://code.google.com/p/phpliteadmin/' target='_blank' style='font-size:11px;'>".PROJECT."</a> | Page generated in ".$timeTot." seconds.</span>";
	echo "</div>";
	echo "</div>";
	$db->close(); //close the database
}
echo "</body>";
echo "</html>";
