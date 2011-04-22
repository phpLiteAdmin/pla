<?php
/*
 * Project: phpLiteAdmin (http://code.google.com/p/phpliteadmin/)
 * Version: 1.2
 * Summary: PHP-based admin tool to view and edit SQLite databases
 * Last updated: 2/4/11
 * Contributors:
 *    Dane Iracleous (daneiracleous@gmail.com)
 *    George Flanagin & Digital Gaslight, Inc (george@digitalgaslight.com)
 */

//an array of databases that you want to manage by path name relative to this file
$databases = array
(
	"database1.sqlite",
	"database2.sqlite",
	"database3.sqlite"
);

//password to gain access (please change this to something more secure than 'admin')
$password = "admin";




//End of user-editable fields and beginning of user-unfriendly source code

ini_set("display_errors", 1);
error_reporting(E_STRICT | E_ALL);
session_start();

//build the basename of this file
$nameArr = explode("?", $_SERVER['PHP_SELF']);
$thisName = $nameArr[0];
$nameArr = explode("/", $thisName);
$thisName = $nameArr[sizeof($nameArr)-1];

//constants
define("PROJECT", "phpLiteAdmin");
define("VERSION", "1.2");
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
// Generic database class to manage interaction with database
//
class Database 
{
	protected $db; //reference to the DB object
	protected $type; //the extension for PHP that handles SQLite
	protected $name; //the filename of the database
	protected $lastResult;
	
	public function __construct($filename) 
	{
		$this->name = $filename;
		
		if(class_exists("PDO")) //first choice is PDO
		{
			$this->type = "PDO";
			$this->db = new PDO("sqlite:".$filename) or die("Error connecting to database");
		}
		else if(class_exists("SQLite3")) //second choice is SQLite3
		{
			$this->type = "SQLite3";
			$this->db = new SQLite3($filename) or die("Error connecting to database");
		}
		else if(class_exists("SQLite")) //third choice is SQLite, AKA SQlite2 - some features may be missing and cause problems, but it's better than nothing :/
		{
			$this->type = "SQLite";
			$this->db = new SQLite($filename) or die("Error connecting to database");	
		}
		else //none of the possible extensions are enabled/installed and thus, the application cannot work
		{
			die("Your installation of PHP does not include a valid SQLite3 or PDO extension required by the application.");	
		}
	}
	
	public function __destruct() 
	{
		$this->close();
	}
	
	//get the exact PHP extension being used for SQLite
	public function getType()
	{
		return $this->type;	
	}
	
	//get the filename of the database
	public function getName()
	{
		return $this->name;	
	}
	
	//get number of affected rows from last query
	public function getAffectedRows()
	{
		if($this->type=="PDO")
			return $this->lastResult->rowCount();
		else if($this->type=="SQLite3")
			return $this->db->changes();
		else if($this->type=="SQLite")
			return sqlite_changes($this->db);
	}
	
	public function close() 
	{
		if($this->type=="PDO")
			$this->db = NULL;
		else if($this->type=="SQLite3")
			$this->db->close();
		else if($this->type=="SQLite")
			sqlite_close($this->db);
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
	public function query($query)
	{
		if($this->type=="SQLite")
			$result = sqlite_query($this->db, $query);
		else
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
		else if($this->type=="SQLite")
			return sqlite_last_insert_rowid($this->db);
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
		else if($this->type=="SQLite")
			return sqlite_fetch_array($result);
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
		else if($this->type=="SQLite")
			return sqlite_fetch_all($result);
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
			return sqlite_escape_string($value);
		}
	}
	
	//correctly format a string value from a table before showing it 
	public function formatString($value)
	{
		return htmlspecialchars(stripslashes($value));	
	}
}

//
// View class
// Various functions to visually represent the database
//
class View
{
	protected $db;
	
	public function __construct($db) 
	{
		$this->db = $db;
	}
	
	//generate the structure view
	public function generateStructure($table)
	{
		$query = "PRAGMA table_info('".$table."')";
		$result = $this->db->selectArray($query);
		
		echo "Note: Removing and editing columns is not currently possible in SQLite<br/><br/>";
		
		echo "<table border='0' cellpadding='2' cellspacing='1'>";
		echo "<tr>";
		echo "<td class='tdheader'>Column #</td>";
		echo "<td class='tdheader'>Field</td>";
		echo "<td class='tdheader'>Type</td>";
		echo "<td class='tdheader'>Not Null</td>";
		echo "<td class='tdheader'>Default Value</td>";
		echo "<td class='tdheader'>Primary Key</td>";
		echo "</tr>";
		
		for($i=0; $i<sizeof($result); $i++)
		{
			echo "<tr>";
			for($j=0; $j<6; $j++)
			{
				$tdWithClass = "<td class='td".($i%2 ? "1" : "2")."'>";
				echo $tdWithClass.$result[$i][$j]."</td>";
			}
			echo "</tr>";
		}
		
		echo "</table>";
		echo "<br/>";
		echo "<form action='".PAGE."' method='post'>";
		echo "<input type='hidden' name='tablename' value='".$table."'/>";
		echo "Add <input type='text' name='tablefields' style='width:30px;' value='1'/> field(s) at end of table <input type='submit' value='Go' name='addfields'/>";
		echo "</form>";
	}
	
	//generate the insert row view
	public function generateInsert($table)
	{
		$query = "PRAGMA table_info('".$table."')";
		$result = $this->db->selectArray($query);
		
		echo "<form action='".PAGE."?table=".$table."&insert=1' method='post'>";
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
	}
	
	//generate the edit row view
	public function generateEdit($table, $pks)
	{
		echo "<form action='".PAGE."?table=".$table."&edit=1&confirm=1&pk=".$pks[0]."' method='post'>";
		$str = "";
		for($j=0; $j<sizeof($pks); $j++)
		{
			$str .= $pks[$j].":";
			$query = "SELECT * FROM ".$table." WHERE ROWID = ".$pks[$j];
			$result1 = $this->db->select($query);
		
			$query = "PRAGMA table_info('".$table."')";
			$result = $this->db->selectArray($query);
		
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
					echo "<input type='text' name='".$field."' value='".$this->db->formatString($value)."'/>";
				else
					echo "<textarea name='".$field."' wrap='hard' rows='1' cols='60'>".$this->db->formatString($value)."</textarea>";
				echo "</td>";
				echo "</tr>";
			}

			echo "</table>";
			echo "<br/><br/>";
		}
		echo "<input type='hidden' value='".$str."'/>";
		echo "<input type='submit' value='Save Changes'/> ";
		echo "<a href='".PAGE."?table=".$table."'>Cancel</a>";
		echo "</form>";
	}
	
	//generate the rename table view
	public function generateRename($table)
	{
		echo "<form action='".PAGE."' method='post'>";
		echo "<input type='hidden' name='oldname' value='".$table."'/>";
		echo "Rename table '".$table."' to <input type='text' name='newname' style='width:200px;'/> <input type='submit' value='Rename' name='rename'/>";
		echo "</form>";
	}
	
	public function generateSelect($result)
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
	
	//generate the view rows view
	public function generateView($table, $numRows, $startRow, $sort, $order)
	{
		$_SESSION['numRows'] = $numRows;
		$_SESSION['startRow'] = $startRow;

		// -g-> We need to get the ROWID to be able to find the row again. I put it at the end of the list of fields so that the numbering remained unchanged.
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
		$arr = $this->db->selectArray($query);
		$endTime = microtime(true);
		$time = round(($endTime - $startTime), 4);
		$total = $this->db->numRows($table);
		
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
		
		echo "<form action='".PAGE."?edit=1&table=".$table."' method='post' name='checkForm'>";
		echo "<table border='0' cellpadding='2' cellspacing='1'>";
		$query = "PRAGMA table_info('".$table."')";
		$result = $this->db->selectArray($query);
		$rowidColumn = sizeof($result);
		
		echo "<tr>";
		echo "<td colspan='3'>";
		echo "</td>";
		
		for($i=0; $i<sizeof($result); $i++)
		{
			echo "<td class='tdheader'>";
			echo "<a href='".PAGE."?table=".$table."&sort=".$result[$i][1];
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
			echo "<a href='".PAGE."?table=".$table."&edit=1&pk=".$pk."'>edit</a>";
			echo "</td>";
			echo $tdWithClass;
			echo "<a href='".PAGE."?table=".$table."&delete=1&pk=".$pk."' style='color:red;'>delete</a>";
			echo "</td>";
			for($j=0; $j<sizeof($result); $j++)
			{
				echo $tdWithClass;
				// -g-> although the inputs do not interpret HTML on the way "in", when we print the contents of the database the interpretation cannot be avoided.
				echo $this->db->formatString($arr[$i][$j]);
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
	}
	
	//generate a list of all the tables in the database
	function generateTableList()
	{
		$query = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name";
		$result = $this->db->selectArray($query);
		
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
					$records = $this->db->numRows($result[$i]['name']);
					
					$tdWithClass = "<td class='td" . ($i%2 ? "1" : "2") . "'>";
					echo "<tr>";
					if($i%2)
						echo "<td class='td1' style='text-align:left;'>";
					else
						echo "<td class='td2' style='text-align:left;'>";
					echo "<a href='".PAGE."?table=".$result[$i]['name']."'>".$result[$i]['name']."</a><br/>";
					echo "</td>";
					echo $tdWithClass;
					echo "<a href='".PAGE."?droptable=".$result[$i]['name']."' style='color:red;'>Drop</a>";
					echo "</td>";
					echo $tdWithClass;
					echo "<a href='".PAGE."?emptytable=".$result[$i]['name']."' style='color:red;'>Empty</a>";
					echo "</td>";
					echo $tdWithClass;
					echo "<a href='".PAGE."?table=".$result[$i]['name']."&view=structure'>Structure</a>";
					echo "</td>";
					echo $tdWithClass;
					echo "<a href='".PAGE."?table=".$result[$i]['name']."&view=browse'>Browse</a>";
					echo "</td>";
					echo $tdWithClass;
					echo "<a href='".PAGE."?table=".$result[$i]['name']."&view=insert'>Insert</a>";
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
		echo "<legend><b>Create new table in database '".$this->db->getName()."'</b></legend>";
		echo "<form action='".PAGE."' method='post'>";
		echo "Name: <input type='text' name='tablename' style='width:200px;'/> ";
		echo "Number of Fields: <input type='text' name='tablefields' style='width:90px;'/> ";
		echo "<input type='submit' name='createtable' value='Go'/>";
		echo "</form>";
		echo "</fieldset>";
	}
	
	//generate the SQL query window
	function generateSQL()
	{
		$isSelect = false;
		if(isset($_POST['query']) && $_POST['query']!="")
		{
			$queryStr = stripslashes($_POST['queryval']);
			$query = explode($_POST['delimiter'], $queryStr); //explode the query string into individual queries based on the delimiter
			
			for($i=0; $i<sizeof($query); $i++) //iterate through the queries exploded by the delimiter
			{
				if($query[$i]!="") //make sure this query is not an empty string
				{
					$startTime = microtime(true);
					if(strpos(strtolower($query[$i]), "select ")!==false)
					{
						$isSelect = true;
						$result = $this->db->selectArray($query[$i], "assoc");
					}
					else
					{
						$isSelect = false;
						$result = $this->db->query($query[$i]);
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
							$affected = $this->db->getAffectedRows();
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
						$this->generateSelect($result);
				}
			}
		}
		else
			$queryStr = "";
			
		echo "<fieldset>";
		echo "<legend><b>Run SQL query/queries on database '".$this->db->getName()."'</b></legend>";
		echo "<form action='".PAGE."?view=sql' method='post'>";
		echo "<textarea style='width:100%; height:300px;' name='queryval'>".$queryStr."</textarea>";
		echo "Delimiter <input type='text' name='delimiter' value=';' style='width:50px;'/> ";
		echo "<input type='submit' name='query' value='Go'/>";
		echo "</form>";
	}
}

// here begins the HTML.
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv='Content-Type' content='text/html; charset=UTF-8' />
<title><?php echo PROJECT ?></title>
<script>
function checkAll(field)
{
	var i=0;
	while(document.getElementById('check_'+i)!=undefined)
	{
		document.getElementById('check_'+i).checked = true;
		i++;	
	}
}
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
	if($_POST['password']==$password)
		$auth->grant();
}

if(!$auth->isAuthorized())
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
}
else
{
	if(sizeof($databases)>0)
		$DBFilename = $databases[0];
	else
		errorMsg($errorMessages[6], true);
		
	if(isset($_POST['database_switch']))
	{
		$_SESSION['DBFilename'] = $_POST['database_switch'];
		$DBFilename = $_POST['database_switch'];
	}
	if(isset($_SESSION['DBFilename']))
		$DBFilename = $_SESSION['DBFilename'];
		
	//First, check that the right classes are available
	if(!class_exists("PDO") && !class_exists("SQLite3") && !class_exists("SQLite"))
		errorMsg($errorMessages[0], true);
	/*
	if($DBFilename!="") //these errors only apply when the file is specified - so if it isn't, don't check for the errors
	{
		//Second, check to see that the database file is intact and can be used
		if(!file_exists($DBFilename))
			errorMsg($errorMessages[1], true);

		//Check the permissions of the database file
		$perms = substr(decoct(fileperms($DBFilename)),4);
		if(intval($perms)<44) 
			errorMsg($errorMessages[2], true);	
		else if(intval($perms)>=44 && intval($perms)<66)
			errorMsg($errorMessages[3], false); // non-fatal error.
	}
*/
	//Check to see if user has not changed default password
	/*
	if($password=="admin")
		errorMsg($errorMessages[4], false);
	else if(strlen($password)<4)
		errorMsg($errorMessages[5], false);
	*/
	
	$db = new Database($DBFilename); //create the Database object
	$dbView = new View($db);
	
	//Switch board for various operations a user could have requested
	if(isset($_POST['createtable'])) //ver 1.1 bug fix - check for $_POST variables before $_GET variables 
	{
	  // Not sure what is happening here... gkf
	}
	else if(isset($_POST['rename']))
	{
		$query = "ALTER TABLE ".$_POST['oldname']." RENAME TO ".$_POST['newname'];
		$db->query($query);
	}
	else if(isset($_POST['createtableconfirm'])) //create new table
	{
		$num = intval($_GET['rows']);
		$name = $_GET['tablename'];
		//build the query for creating this new table
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
		$db->query($query);
	}
	else if(isset($_POST['addfieldsconfirm'])) //create new table
	{
		$num = intval($_GET['rows']);
		$name = $_GET['tablename'];
		//build the query for creating this new table
		for($i=0; $i<$num; $i++)
		{
			if($_POST[$i.'_field']!="")
			{
				$query = "ALTER TABLE ".$name." ADD COLUMN ".$_POST[$i.'_field']." ";
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
				$db->query($query);
			}
		}
		$_GET['table'] = $_GET['tablename'];
		$_GET['view'] = "structure";
	}
	else if(isset($_GET['droptable']) && isset($_GET['confirm'])) //drop table
	{
		$query = "DROP TABLE ".$_GET['droptable'];
		$db->query($query);
	}
	else if(isset($_GET['emptytable']) && isset($_GET['confirm'])) //empty table
	{
		$query = "DELETE FROM ".$_GET['emptytable'];
		$db->query($query);
		$query = "VACUUM";
		$db->query($query);
	}
	else if(isset($_GET['insert'])) //insert record into table
	{
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
		$db->query($query);
		$insertQuery = $query;
	}
	echo "<div id='container'>";
	echo "<div id='leftNav'>";
	echo "<h1>".PROJECT." <span style='font-size:14px; color:#000;'>v".VERSION."</span></h1>";
	echo "<fieldset style='margin:15px;'><legend><b>Change Database</b></legend>";
	echo "<form action='".PAGE."' method='post'>";
	echo "<select name='database_switch'>";
	for($i=0; $i<sizeof($databases); $i++)
	{
		if($databases[$i]==$DBFilename)
			echo "<option value='".$databases[$i]."' selected='selected'>".$databases[$i]."</option>";
		else
			echo "<option value='".$databases[$i]."'>".$databases[$i]."</option>";
	}
	echo "</select> ";
	echo "<input type='submit' value='Go'>";
	echo "</form>";
	echo "</fieldset>";
	echo "<fieldset style='margin:15px;'><legend><a href='".PAGE."'>".$DBFilename."</a></legend>";
	//Display list of tables
	$query = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name";
	$result = $db->selectArray($query);
	$j=0;
	for($i=0; $i<sizeof($result); $i++)
	{
		if(substr($result[$i]['name'], 0, 7)!="sqlite_" && $result[$i]['name']!="")
		{
			echo "<a href='".PAGE."?table=".$result[$i]['name']."'>".$result[$i]['name']."</a><br/>";
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
	
	if(isset($_POST['createtable']) || isset($_POST['addfields']))
	{
		echo "<h2>Database: ".$DBFilename."</h2>";
		echo "<div id='main'>";
		if(isset($_POST['addfields']))
			echo "<h2>Adding new field(s) to table '".$_POST['tablename']."'</h2>";
		else
			echo "<h2>Creating new table: '".$_POST['tablename']."'</h2>";
		if($_POST['tablefields']=="" || intval($_POST['tablefields'])<=0)
			echo "You must specify the number of table fields.";
		else if($_POST['tablename']=="")
			echo "You must specify a table name.";
		else
		{
			$num = intval($_POST['tablefields']);
			$name = $_POST['tablename'];
			echo "<form action='".PAGE."?tablename=".$name."&rows=".$num."' method='post'>";
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
			if(isset($_POST['addfields']))
				echo "<input type='submit' name='addfieldsconfirm' value='Add Field(s)'/> ";
			else
				echo "<input type='submit' name='createtableconfirm' value='Create'/> ";
			echo "<a href='".PAGE."?table=".$_POST['tablename']."&view=structure'>Cancel</a>";
			echo "</form>";
		}
		echo "</div>";
	}
	else if(isset($_GET['table']) && !isset($_POST['rename']))
	{
		if(isset($_GET['view']))
			$view = $_GET['view'];
		else
			$view = "browse";
			
		$table = $_GET['table'];
		echo "<h2>Database: ".$DBFilename." | Table: ".$table."</h2>";
		
		if((isset($_GET['delete']) && !isset($_GET['confirm'])) || (isset($_POST['massType']) && $_POST['massType']=="delete"))
		{
			echo "<div id='main'>";
			if(isset($_POST['check']))
				$pks = $_POST['check'];
			else if(isset($_GET['pk']))
				$pks = array($_GET['pk']);
			
			if(isset($pks))
			{
				$str = $pks[0];
				$pass = $pks[0];
				for($i=1; $i<sizeof($pks); $i++)
				{
					$str .= ", ".$pks[$i];
					$pass .= ":".$pks[$i];
				}
				echo "<div class='confirm'>";
				echo "Are you sure you want to delete record(s) ".$str."?<br/><br/>";
				echo "<a href='".PAGE."?table=".$table."&delete=1&pks=".$pass."&confirm=1'>Confirm</a> | ";
				echo "<a href='".PAGE."?table=".$table."'>Cancel</a>";
				echo "</div>";
			}
			else
			{
				echo "<div class='confirm'>";
				echo "You have not selected any rows.";
				echo "</div>";	
			}
			echo "</div>";
		}
		else if((isset($_GET['edit']) && !isset($_GET['confirm'])) || (isset($_POST['massType']) && $_POST['massType']=="edit"))
		{
			echo "<div id='main'>";
			if(isset($_POST['check']))
				$pks = $_POST['check'];
			else
				$pks = array($_GET['pk']);
			$dbView->generateEdit($table, $pks);
			echo "</div>";
		}
		else
		{
			if(isset($_GET['delete']) && isset($_GET['confirm']))
			{
				$pks = explode(":", $_GET['pks']);
				$str = $pks[0];
				$query = "DELETE FROM ".$table." WHERE ROWID = ".$pks[0];
				for($i=1; $i<sizeof($pks); $i++)
				{
					$str .= ", ".$pks[$i];
					$query .= " OR ROWID = ".$pks[$i];
				}
				$result = $db->query($query);
				echo "<div class='confirm'>";
				if($result)
				{
					echo "<b>".sizeof($pks)." row(s) affected.</b><br/>";
					echo "<span style='font-size:11px;'>".$query."</span>";
				}
				else
					echo "An error occured.";
				echo "</div><br/>";
			}
			else if(isset($_GET['edit']) && isset($_GET['confirm']))
			{
				// -g-> And we have to change this request a bit.
				//$pks = explode(":", $_POST['pks']);
				$query = "UPDATE ".$table." SET ";
				foreach($_POST as $vblname => $value)
				{
					$query .= $vblname."=".$db->quote($value).", ";
				}
				$query = substr($query, 0, sizeof($query)-3);
				
				$query .= " WHERE ROWID = ".$_GET['pk'];
				
				$result = $db->query($query);
				echo "<div class='confirm'>";
				if($result)
				{
					echo "<b>1 row(s) affected.</b><br/>";
					echo "<span style='font-size:11px;'>".$query."</span>";
				}
				else
					echo "An error occured.";
				echo "</div><br/>";
			}
			else if(isset($_GET['insert']))
			{	
				echo "<div class='confirm'>";
				echo "<b>1 row(s) inserted.</b><br/>";
				echo "<span style='font-size:11px;'>".$insertQuery."</span>";
				echo "</div><br/>";
			}
			echo "<a href='".PAGE."?table=".$table."&view=browse' ";
			if($view=="browse")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">Browse</a>";
			echo "<a href='".PAGE."?table=".$table."&view=structure' ";
			if($view=="structure")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">Structure</a>";
			echo "<a href='".PAGE."?table=".$table."&view=insert' ";
			if($view=="insert")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">Insert</a>";
			echo "<a href='".PAGE."?table=".$table."&view=rename' ";
			if($view=="rename")
				echo "class='tab_pressed'";
			else
				echo "class='tab'";
			echo ">Rename</a>";
			echo "<a href='".PAGE."?emptytable=".$table."' ";
			echo "class='tab' style='color:red;'";
			echo ">Empty</a>";
			echo "<a href='".PAGE."?droptable=".$table."' ";
			echo "class='tab' style='color:red;'";
			echo ">Drop</a>";
			echo "<div style='clear:both;'></div>";
			echo "<div id='main'>";
			
			if($view=="structure")
				$dbView->generateStructure($table);
			else if($view=="insert")
				$dbView->generateInsert($table);
			else if($view=="rename")
				$dbView->generateRename($table);
			else
			{
				if(isset($_POST['startRow']))
					$_SESSION['startRow'] = $_POST['startRow'];
		
				if(isset($_POST['numRows']))
					$_SESSION['numRows'] = $_POST['numRows'];
			
				if(!isset($_SESSION['startRow']))
					$_SESSION['startRow'] = 0;
			
				if(!isset($_SESSION['numRows']))
					$_SESSION['numRows'] = 30;
		
				echo "<form action='".PAGE."?table=".$table."' method='post'>";
				echo "<input type='submit' value='Show : ' name='show'/> ";
				echo "<input type='text' name='numRows' style='width:50px;' value='".$_SESSION['numRows']."'/> ";
				echo "row(s) starting from record # ";
				echo "<input type='text' name='startRow' style='width:90px;' value='".intval($_SESSION['startRow']+$_SESSION['numRows'])."'/>";
				echo "</form>";
				if(!isset($_GET['sort']))
					$_GET['sort'] = NULL;
				if(!isset($_GET['order']))
					$_GET['order'] = NULL;
				$dbView->generateView($table, $_SESSION['numRows'], $_SESSION['startRow'], $_GET['sort'], $_GET['order']);
			}
			
			echo "</div>";
		}
	}
	else
	{
		echo "<h2>Database: ".$DBFilename."</h2>";
		
		if(isset($_POST['createtableconfirm']))
		{
			echo "<div class='confirm'>";
			echo "Table '".$_GET['tablename']."' has been created.";
			echo "</div><br/>";
		}
		else if(isset($_GET['droptable']) && isset($_GET['confirm']))
		{
			echo "<div class='confirm'>";
			echo "Table '".$_GET['droptable']."' has been dropped.";
			echo "</div><br/>";
		}
		else if(isset($_GET['emptytable']) && isset($_GET['confirm']))
		{
			echo "<div class='confirm'>";
			echo "Table '".$_GET['emptytable']."' has been emptied.";
			echo "</div><br/>";
		}
		
		if(isset($_GET['emptytable']) && !isset($_GET['confirm']))
		{
			echo "<div id='main'>";
			echo "<div class='confirm'>";
			echo "Are you sure you want to empty the table '".$_GET['emptytable']."'?<br/><br/>";
			echo "<a href='".PAGE."?emptytable=".$_GET['emptytable']."&confirm=1'>Confirm</a> | ";
			echo "<a href='".PAGE."'>Cancel</a>";
			echo "</div>";
			echo "</div>";
		}
		else if(isset($_GET['droptable']) && !isset($_GET['confirm']))
		{
			echo "<div id='main'>";
			echo "<div class='confirm'>";
			echo "Are you sure you want to drop the table '".$_GET['droptable']."'?<br/><br/>";
			echo "<a href='".PAGE."?droptable=".$_GET['droptable']."&confirm=1'>Confirm</a> | ";
			echo "<a href='".PAGE."'>Cancel</a>";
			echo "</div>";
			echo "</div>";
		}
		else
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
				$dbView->generateTableList();
			else if($view=="sql")
				$dbView->generateSQL();
				
			echo "</div>";
		}
	}
	echo "</div>";
	echo "<br/>";
	echo "<a href='http://code.google.com/p/phpliteadmin/' target='_blank' style='font-size:11px;'>Get help and updates from the ".PROJECT." project on Google Code</a>";
	echo "</div>";
	echo "</div>";
	$db->close(); //close the database
}
echo "</body>";
echo "</html>";
