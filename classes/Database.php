<?php
// Database class
// Generic database abstraction class to manage interaction with database without worrying about SQLite vs. PHP versions
//
class Database
{
	protected mixed $db; //reference to the DB object
	protected string $type;
	protected mixed $lastResult;
	protected string $alterError;
	protected string $debugOutput ='';

	public function __construct(protected $data)
	{
		global $lang, $params;
		try
		{
			if(!file_exists($this->data["path"]) && !is_writable(dirname((string) $this->data["path"]))) //make sure the containing directory is writable if the database does not exist
			{
				echo "<div class='confirm' style='margin:20px;'>";
				printf($lang['db_not_writeable'], htmlencode($this->data["path"]), htmlencode(dirname((string) $this->data["path"])));
				echo $params->getForm();
				echo "<input type='submit' value='Log Out' name='".$lang['logout']."' class='btn'/>";
				echo "</form>";
				echo "</div><br/>";
				exit();
			}

			$ver = $this->getVersion();

			switch(true)
			{
				case ((!isset($data['type']) || $data['type']!=2) && (FORCETYPE=="PDO" || (FORCETYPE==false && class_exists("PDO") && in_array("sqlite", PDO::getAvailableDrivers()) && ($ver==-1 || $ver==3)))):
					$this->db = new PDO("sqlite:".$this->data['path']);
					if($this->db!=NULL)
					{
						$this->type = "PDO";
						break;
					}
				case ((!isset($data['type']) || $data['type']!=2) && (FORCETYPE=="SQLite3" || (FORCETYPE==false && class_exists("SQLite3") && ($ver==-1 || $ver==3)))):
					$this->db = new SQLite3($this->data['path']);
					if($this->db!=NULL)
					{
						$this->type = "SQLite3";
						break;
					}
				case (FORCETYPE=="SQLiteDatabase" || (FORCETYPE==false && class_exists("SQLiteDatabase") && ($ver==-1 || $ver==2))):
					$this->db = new SQLiteDatabase($this->data['path']);
					if($this->db!=NULL)
					{
						$this->type = "SQLiteDatabase";
						break;
					}
				default:
					$this->showError();
					exit();
			}
			$this->query("PRAGMA foreign_keys = ON");
		}
		catch(Exception)
		{
			$this->showError();
			exit();
		}
	}

	public function registerUserFunction($ids): void
	{
		// in case a single function id was passed
		if (is_string($ids))
			$ids = [$ids];

		if ($this->type == 'PDO') {
			foreach ($ids as $id) {
				$this->db->sqliteCreateFunction($id, $id, -1);
			}
		} else { // type is Sqlite3 or SQLiteDatabase
			foreach ($ids as $id) {
				$this->db->createFunction($id, $id, -1);
			}
		}
	}

	public function getError($complete_msg = false)
	{
		global $lang, $debug;
		$error = "unknown";

		if($this->alterError!='')
		{
			$error = $this->alterError;
			$this->alterError = "";
		}
		else if($this->type=="PDO")
		{
			$e = $this->db->errorInfo();
			$error = $e[2];
		}
		else if($this->type=="SQLite3")
		{
			$error = $this->db->lastErrorMsg();
		}
		else
		{
			$error = sqlite_error_string($this->db->lastError());
		}

		if($complete_msg)
		{
			$error = $lang['err'].": ".htmlencode($error);
			// do not suggest to report a bug when constraints fail
			if(!str_contains($error, 'constraint failed'))
				$error.="<br/>".$lang['bug_report'].' '.PROJECT_BUGTRACKER_LINK;
		}

		if($debug)
			$error .= $this->getDebugOutput();

		return $error;
	}

	function getDebugOutput()
	{
		return ($this->debugOutput != "" ? "<hr /><strong>DEBUG:</strong><br />".$this->debugOutput : $this->debugOutput);
	}

	public function showError(): void
	{
		global $lang;
		$classPDO = class_exists("PDO");
		$classSQLite3 = class_exists("SQLite3");
		$classSQLiteDatabase = class_exists("SQLiteDatabase");
		if($classPDO)	// PDO is there, check if the SQLite driver for PDO is missing
			$PDOSqliteDriver = (in_array("sqlite", PDO::getAvailableDrivers() ));
		else
			$PDOSqliteDriver = false;
		echo "<div class='confirm' style='margin:20px;'>";
		printf($lang['db_setup'], $this->getPath());
		echo ".<br/><br/><i>".$lang['chk_ext']."...<br/><br/>";
		echo "<b>PDO</b>: ".($classPDO ? $lang['installed'] : $lang['not_installed'])."<br/>";
		echo "<b>PDO SQLite Driver</b>: ".($PDOSqliteDriver ? $lang['installed'] : $lang['not_installed'])."<br/>";
		echo "<b>SQLite3</b>: ".($classSQLite3 ? $lang['installed'] : $lang['not_installed'])."<br/>";
		echo "<b>SQLiteDatabase</b>: ".($classSQLiteDatabase ? $lang['installed'] : $lang['not_installed'])."<br/>";
		echo "<br/>...".$lang['done'].".</i><br/><br/>";
		if(!$classPDO && !$classSQLite3 && !$classSQLiteDatabase)
			printf($lang['sqlite_ext_support'], PROJECT);
		else
		{
			if(!$PDOSqliteDriver && !$classSQLite3 && $this->getVersion()==3)
				printf($lang['sqlite_v_error'], 3, PROJECT, 2);
			else if(!$classSQLiteDatabase && $this->getVersion()==2)
				printf($lang['sqlite_v_error'], 2, PROJECT, 3);
			else
			{
				if(!file_exists($this->getPath()))
				{
					if(touch($this->getPath()))
					{
						echo $lang['report_issue'].' '.PROJECT_BUGTRACKER_LINK.'.';
					}
					else
					{
						echo "<strong>".$lang['filesystem_permission_denied']."</strong>";
					}
				}
			}
		}
		echo "<p>See ".PROJECT_INSTALL_LINK." for help.</p>";

		$this->print_db_list();

		echo "</div>";
	}

	// print the list of databases
	public function print_db_list(): void
	{
		global $databases, $lang, $params, $currentDB;
		echo "<fieldset style='margin:15px;' class='databaseList'><legend><b>".$lang['db_ch']."</b></legend>";
		if(sizeof($databases)<10) //if there aren't a lot of databases, just show them as a list of links instead of drop down menu
		{
			$i=0;
			foreach($databases as $database)
			{
				$i++;
				$name = $database['name'];
				if(mb_strlen($name)>25)
					$name = "...".mb_substr($name, mb_strlen($name)-22, 22);
				echo '[' . ($database['readable'] ? 'r':' ' ) . ($database['writable'] && $database['writable_dir'] ? 'w':' ' ) . ']&nbsp;';

				echo $params->getLink(['database'=>$database['path'], 'table'=>null], htmlencode($name), ($database == $currentDB? 'active_db': '') );
				echo "&nbsp;&nbsp;";
				echo $params->getLink(['download'=>$database['path'], 'table'=>null, 'token'=>$_SESSION[COOKIENAME.'token']], '[&darr;]', '', $lang['backup']);

				if($i<sizeof($databases))
					echo "<br/>";
			}
		}
		else //there are a lot of databases - show a drop down menu
		{
			echo $params->getForm(['table'=>null], 'get');
			echo "<select name='database' onchange='this.form.submit()'>";
			foreach($databases as $database)
			{
				$perms_string = htmlencode('[' . ($database['readable'] ? 'r':' ' ) . ($database['writable'] && $database['writable_dir'] ? 'w':' ' ) . '] ');
				if($database == $currentDB)
					echo "<option value='".htmlencode($database['path'])."' selected='selected'>".$perms_string.htmlencode($database['name'])."</option>";
				else
					echo "<option value='".htmlencode($database['path'])."'>".$perms_string.htmlencode($database['name'])."</option>";
			}
			echo "</select>";
			echo "<noscript><input type='submit' value='".$lang['go']."' class='btn'></noscript>";
			echo "</form>";
		}
		echo "</fieldset>";
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

	// get the version of the SQLite library
	public function getSQLiteVersion()
	{
		$queryVersion = $this->select("SELECT sqlite_version() AS sqlite_version");
		return $queryVersion['sqlite_version'];
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

	//is the db-file writable?
	public function isWritable()
	{
		return $this->data["writable"];
	}

	//is the db-folder writable?
	public function isDirWritable()
	{
		return $this->data["writable_dir"];
	}

	//get the version of the database
	public function getVersion()
	{
		if(file_exists($this->data['path'])) //make sure file exists before getting its contents
		{
			$content = strtolower(file_get_contents((string)$this->data['path'], false, NULL, 0, 40)); //get the first 40 characters of the database file
			$p = strpos($content, "** this file contains an sqlite 2"); //this text is at the beginning of every SQLite2 database
			if($p!==false) //the text is found - this is version 2
				return 2;
			else
				return 3;
		}
		else //return -1 to indicate that it does not exist and needs to be created
		{
			return -1;
		}
	}

	//get the size of the database (in KiB)
	public function getSize()
	{
		return round(filesize($this->data["path"])*0.0009765625, 1);
	}

	//get the last modified time of database
	public function getDate()
	{
		global $lang;
		return date($lang['date_format'], filemtime($this->data['path']));
	}

	//get number of affected rows from last query
	public function getAffectedRows()
	{
		if($this->type=="PDO")
			if(!is_object($this->lastResult))
				// in case it was an alter table statement, there is no lastResult object
				return 0;
			else
				return $this->lastResult->rowCount();
		else if($this->type=="SQLite3")
			return $this->db->changes();
		else if($this->type=="SQLiteDatabase")
			return $this->db->changes();
	}

	public function getTypeOfTable($table)
	{
		$result = $this->select("SELECT `type` FROM `sqlite_master` WHERE `name`=" . $this->quote($table), 'assoc');
		return $result['type'];
	}

	public function getTableInfo($table)
	{
		return $this->selectArray("PRAGMA table_info(".$this->quote_id($table).")");
	}

	// returns the list of tables (opt. incl. views) as
	// array( Tablename => tableType ) with tableType being 'view' or 'table'
	public function getTables($alsoViews=true, $alsoInternal=false, $orderBy='name', $orderDirection='ASC')
	{
		$query = "SELECT name, type FROM sqlite_master "
			. "WHERE (type='table'".($alsoViews?" OR type='view'":"").") "
			. "AND name!='' ".($alsoInternal? "":" AND name NOT LIKE 'sqlite_%' ")
			. "ORDER BY ".$this->quote_id($orderBy)." ".$orderDirection;
		$result = $this->selectArray($query);
		$list = [];
		for($i=0; $i<sizeof($result); $i++)
		{
			$list[$result[$i]['name']] = $result[$i]['type'];
		}
		return $list;
	}

	// returns an array of all tables and their columns as
	// array( tablename => array(columName) )
	public function getTableDefinitions()
	{
		$tables = $this->getTables(true, true);
		$result = [];
		foreach ($tables as $tableName => $tableType)
		{
			$tableInfo = $this->getTableInfo($tableName);
			$columns = [];
			foreach($tableInfo as $column)
				$columns[] = $column['name'];
			$result[$tableName] = $columns;
		}
		return $result;
	}

	public function close(): void
	{
		if($this->type=="PDO")
			$this->db = NULL;
		else if($this->type=="SQLite3")
			$this->db->close();
		else if($this->type=="SQLiteDatabase")
			$this->db = NULL;
	}

	public function beginTransaction(): void
	{
		$this->query("BEGIN");
	}

	public function commitTransaction(): void
	{
		$this->query("COMMIT");
	}

	public function rollbackTransaction(): void
	{
		$this->query("ROLLBACK");
	}

	//generic query wrapper
	//returns false on error and the query result on success
	public function query($query, $ignoreAlterCase=false)
	{
		global $debug;
		if(strtolower(substr(ltrim((string) $query),0,5))=='alter' && $ignoreAlterCase==false) //this query is an ALTER query - call the necessary function
		{
			preg_match("/^\s*ALTER\s+TABLE\s+(".$this->sqlite_surroundings_preg("+",false,",' \"\[`").")\s+(.*)$/i",(string) $query,$matches);
			if(!isset($matches[1]) || !isset($matches[2]))
			{
				if($debug) echo "<span title='".htmlencode($query)."' onclick='this.innerHTML=\"".htmlencode(str_replace('"','\"',(string) $query))."\"' style='cursor:pointer'>SQL?</span><br />";
				return false;
			}
			$tablename = $this->sqliteUnquote($matches[1]);
			$alterdefs = $matches[2];
			if($debug) echo "ALTER TABLE QUERY=(".htmlencode($query)."), tablename=($tablename), alterdefs=($alterdefs)<br />";
			$result = $this->alterTable($tablename, $alterdefs);
		}
		else //this query is normal - proceed as normal
		{
			$result = $this->db->query($query);
			if($debug) echo "<span title='".htmlencode($query)."' onclick='this.innerHTML=\"".htmlencode(str_replace('"','\"',(string) $query))."\"' style='cursor:pointer'>SQL?</span><br />";
		}
		if($result===false)
			return false;
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
		if(!$result) //make sure the result is valid
			return NULL;
		if($this->type=="PDO")
		{
			if($mode=="assoc")
				$mode = PDO::FETCH_ASSOC;
			else if($mode=="num")
				$mode = PDO::FETCH_NUM;
			else
				$mode = PDO::FETCH_BOTH;
			$ret = $result->fetch($mode);
			$result->closeCursor();
			return $ret;
		}
		else if($this->type=="SQLite3")
		{
			if($mode=="assoc")
				$mode = SQLITE3_ASSOC;
			else if($mode=="num")
				$mode = SQLITE3_NUM;
			else
				$mode = SQLITE3_BOTH;
			$ret = $result->fetchArray($mode);
			$result->finalize();
			return $ret;
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
		//make sure the result is valid
		if($result=== false || $result===NULL)
			return NULL;		// error
		if(!is_object($result)) // no rows returned
			return [];
		if($this->type=="PDO")
		{
			if($mode=="assoc")
				$mode = PDO::FETCH_ASSOC;
			else if($mode=="num")
				$mode = PDO::FETCH_NUM;
			else
				$mode = PDO::FETCH_BOTH;
			$ret = $result->fetchAll($mode);
			$result->closeCursor();
			return $ret;
		}
		else if($this->type=="SQLite3")
		{
			if($mode=="assoc")
				$mode = SQLITE3_ASSOC;
			else if($mode=="num")
				$mode = SQLITE3_NUM;
			else
				$mode = SQLITE3_BOTH;
			$arr = [];
			$i = 0;
			while($res = $result->fetchArray($mode))
			{
				$arr[$i] = $res;
				$i++;
			}
			$result->finalize();
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

	//returns an array of the next row in $result
	public function fetch($result, $mode="both")
	{
		//make sure the result is valid
		if($result=== false || $result===NULL)
			return NULL;		// error
		if(!is_object($result)) // no rows returned
			return [];
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
			if($result->numColumns() === 0) {
				return [];
			}
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

	public function getColumnName($result, $colNum)
	{
		//make sure the result is valid
		if($result=== false || $result===NULL || !is_object($result))
			return "";		// error or no rows returned
		if($this->type=="PDO")
		{
			$meta = $result->getColumnMeta($colNum);
			return $meta['name'];
		}
		else if($this->type=="SQLite3")
		{
			return $result->columnName($colNum);
		}
		else if($this->type=="SQLiteDatabase")
		{
			return $result->fieldName($colNum);
		}
	}


	// SQlite supports multiple ways of surrounding names in quotes:
	// single-quotes, double-quotes, backticks, square brackets.
	// As sqlite does not keep this strict, we also need to be flexible here.
	// This function generates a regex that matches any of the possibilities.
	private function sqlite_surroundings_preg($name,bool $preg_quote=true,string $notAllowedCharsIfNone="'\"",$notAllowedName=false)
	{
		if($name=="*" || $name=="+")
		{
			if($notAllowedName!==false && $preg_quote)
				$notAllowedName = preg_quote((string) $notAllowedName,"/");
			// use possesive quantifiers to save memory
			// (There is a bug in PCRE starting in 8.13 and fixed in PCRE 8.36
			// why we can't use posesive quantifiers - See issue #310).
			if(version_compare(strstr(constant('PCRE_VERSION'), ' ', true), '8.36', '>=') ||
				version_compare(strstr(constant('PCRE_VERSION'), ' ', true), '8.12', '<='))
				$posessive='+';
			else
				$posessive='';

			$nameSingle   = ($notAllowedName!==false?"(?!".$notAllowedName."')":"")."(?:[^']$name+|'')$name".$posessive;
			$nameDouble   = ($notAllowedName!==false?"(?!".$notAllowedName."\")":"")."(?:[^\"]$name+|\"\")$name".$posessive;
			$nameBacktick = ($notAllowedName!==false?"(?!".$notAllowedName."`)":"")."(?:[^`]$name+|``)$name".$posessive;
			$nameSquare   = ($notAllowedName!==false?"(?!".$notAllowedName."\])":"")."(?:[^\]]$name+|\]\])$name".$posessive;
			$nameNo = ($notAllowedName!==false?"(?!".$notAllowedName."\s)":"")."[^".$notAllowedCharsIfNone."]$name";
		}
		else
		{
			if($preg_quote) $name = preg_quote((string) $name,"/");

			$nameSingle = str_replace("'","''",(string) $name);
			$nameDouble = str_replace('"','""',(string) $name);
			$nameBacktick = str_replace('`','``',(string) $name);
			$nameSquare = str_replace(']',']]',(string) $name);
			$nameNo = $name;
		}

		$preg =	"(?:'".$nameSingle."'|".   // single-quote surrounded or not in quotes (correct SQL for values/new names)
				$nameNo."|".               // not surrounded (correct SQL if not containing reserved words, spaces or some special chars)
				"\"".$nameDouble."\"|".    // double-quote surrounded (correct SQL for identifiers)
				"`".$nameBacktick."`|".    // backtick surrounded (MySQL-Style)
				"\[".$nameSquare."\])";    // square-bracket surrounded (MS Access/SQL server-Style)
		return $preg;
	}

	private function sqliteUnquote($quotedName)
	{
		$firstChar = $quotedName[0];
		$withoutFirstAndLastChar = substr((string) $quotedName,1,-1);
		$name = match ($firstChar) {
      "'", '"', '`' => str_replace($firstChar.$firstChar,$firstChar,$withoutFirstAndLastChar),
      '[' => str_replace("]]","]",$withoutFirstAndLastChar),
      default => $quotedName,
  };
		return $name;
	}

	// Returns the last PREG error as a string, '' if no error occured
	private function getPregError()
	{
		$error = preg_last_error();
		return match ($error) {
      PREG_NO_ERROR => 'No error',
      PREG_INTERNAL_ERROR => 'There is an internal error!',
      PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit was exhausted!',
      PREG_RECURSION_LIMIT_ERROR => 'Recursion limit was exhausted!',
      PREG_BAD_UTF8_ERROR => 'Bad UTF8 error!',
      5 => 'Bad UTF8 offset error!',
      default => 'Unknown Error',
  };
	}

	// function that is called for an alter table statement in a query
	// code borrowed with permission from http://code.jenseng.com/db/
	// this has been completely debugged / rewritten by Christopher Kramer
	public function alterTable($table, $alterdefs)
	{
		global $debug, $lang;
		$this->alterError="";
		$errormsg = sprintf($lang['alter_failed'],htmlencode($table)).' - ';
		if($debug) $this->debugOutput .= "ALTER TABLE: table=($table), alterdefs=($alterdefs), PCRE version=(".PCRE_VERSION.")<hr /><br />";
		if($alterdefs != '')
		{
			$recreateQueries = [];
			$resultArr = $this->selectArray("SELECT sql,name,type FROM sqlite_master WHERE tbl_name = ".$this->quote($table));
			if(sizeof($resultArr)<1)
			{
				$this->alterError = $errormsg . sprintf($lang['tbl_inexistent'], htmlencode($table));
				if($debug) $this->debugOutput .= "ERROR: unknown table<hr /><br />";
				return false;
			}
			for($i=0; $i<sizeof($resultArr); $i++)
			{
				$row = $resultArr[$i];
				if($row['type'] != 'table' && $row['type'] != 'view')
				{
					if($row['sql']!='')
					{
						// store the CREATE statements of triggers and indexes to recreate them later
						$recreateQueries[] = $row;
						if($debug) $this->debugOutput .= "recreate=(".$row['sql'].";)<br />";
					}
				}
				elseif($row['type']=='view')  // workaround to rename views
				{
					$origsql = $row['sql'];
					$preg_remove_create_view = "/^\s*+CREATE\s++VIEW\s++".$this->sqlite_surroundings_preg($table)."\s*+(AS\s++SELECT\s++.*+)$/is";
					$origsql_no_create = preg_replace($preg_remove_create_view, '$1', (string) $origsql, 1);
					if($debug) $this->debugOutput .= "origsql=($origsql)<br />preg_remove_create_table=($preg_remove_create_view)<br />";
					preg_match("/RENAME\s++TO\s++(?:\"((?:[^\"]|\"\")+)\"|'((?:[^']|'')+)')/is", (string) $alterdefs, $matches);
					if(isset($matches[1]) && $matches[1]!='')
						$newname = $matches[1];
					elseif(isset($matches[2]) && $matches[2]!='')
						$newname = $matches[2];
					else
					{
						$this->alterError = $errormsg . ' could not detect new view name. It needs to be in single or double quotes.';
						if($debug) $this->debugOutput .= "ERROR: could not detect new view name<hr />";
						return false;
					}
					$dropoldSQL = 'DROP VIEW '.$this->quote_id($table);
					$createnewSQL = 'CREATE VIEW '.$this->quote_id($newname).' '.$origsql_no_create;
					$alter_transaction = 'BEGIN; ' . $dropoldSQL .'; '. $createnewSQL . '; ' . 'COMMIT;';
					if($debug) $this->debugOutput .= $alter_transaction;
					return $this->multiQuery($alter_transaction);
				}
				else
				{
					// ALTER the table
					$tmpname = 't'.time();
					$origsql = $row['sql'];
					$preg_remove_create_table = "/^\s*+CREATE\s++TABLE\s++".$this->sqlite_surroundings_preg($table)."\s*+(\(.*+)$/is";
					$origsql_no_create = preg_replace($preg_remove_create_table, '$1', (string) $origsql, 1);
					if($debug) $this->debugOutput .= "origsql=($origsql)<br />preg_remove_create_table=($preg_remove_create_table)<br />";
					if($origsql_no_create == $origsql)
					{
						$this->alterError = $errormsg . $lang['alter_tbl_name_not_replacable'];
						if($debug) $this->debugOutput .= "ERROR: could not get rid of CREATE TABLE<hr />";
						return false;
					}
					$createtemptableSQL = "CREATE TABLE ".$this->quote($tmpname)." ".$origsql_no_create;
					if($debug) $this->debugOutput .= "createtemptableSQL=($createtemptableSQL)<br />";
					$createindexsql = [];
					$preg_alter_part = "/(?:DROP(?! PRIMARY KEY)(?: COLUMN)?|ADD(?! PRIMARY KEY)(?: COLUMN)?|CHANGE(?: COLUMN)?|RENAME TO|ADD PRIMARY KEY|DROP PRIMARY KEY)" // the ALTER command
						."(?:"
							."\s+\(".$this->sqlite_surroundings_preg("+",false,"\"'\[`)")."+\)"	// stuff in brackets (in case of ADD PRIMARY KEY)
						."|"																	// or
							."\s+".$this->sqlite_surroundings_preg("+",false,",'\"\[`")			// column names and stuff like this
						.")*/i";
					if($debug)
						$this->debugOutput .= "preg_alter_part=(".$preg_alter_part.")<br />";
					preg_match_all($preg_alter_part,(string) $alterdefs,$matches);
					$defs = $matches[0];

					$result_oldcols = $this->getTableInfo($table);
					$newcols = [];
					$coltypes = [];
					$primarykey = [];
					foreach($result_oldcols as $column_info)
					{
						$newcols[$column_info['name']] = $column_info['name'];
						$coltypes[$column_info['name']] = $column_info['type'];
						if($column_info['pk'])
							$primarykey[] = $column_info['name'];
					}
					$newcolumns = '';
					$oldcolumns = '';
					reset($newcols);
					foreach($newcols as $key => $val)
					{
						$newcolumns .= ($newcolumns?', ':'').$this->quote_id($val);
						$oldcolumns .= ($oldcolumns?', ':'').$this->quote_id($key);
					}
					$copytotempsql = 'INSERT INTO '.$this->quote_id($tmpname).'('.$newcolumns.') SELECT '.$oldcolumns.' FROM '.$this->quote_id($table);
					$dropoldsql = 'DROP TABLE '.$this->quote_id($table);
					$createtesttableSQL = $createtemptableSQL;
					if(count($defs)<1)
					{
						$this->alterError = $errormsg . $lang['alter_no_def'];
						if($debug) $this->debugOutput .= "ERROR: defs&lt;1<hr /><br />";
						return false;
					}
					foreach($defs as $def)
					{
						if($debug) $this->debugOutput .= "<hr />def=$def<br />";
						$preg_parse_def =
							"/^(DROP(?! PRIMARY KEY)(?: COLUMN)?|ADD(?! PRIMARY KEY)(?: COLUMN)?|CHANGE(?: COLUMN)?|RENAME TO|ADD PRIMARY KEY|DROP PRIMARY KEY)" // $matches[1]: command
							."(?:"												// this is either
								."(?:\s+\((.+)\)\s*$)"							// anything in brackets (for ADD PRIMARY KEY)
																				// then $matches[2] is what there is in brackets
							."|"												// OR:
								."\s+(".$this->sqlite_surroundings_preg("+",false," \"'\[`").")"//  $matches[3]: (first) column name, possibly including quotes
																				// (may be quoted in any type of quotes)
																				// in case of RENAME TO, it is the new a table name
								."("											// $matches[4]: anything after the column name
									."(?:\s+(".$this->sqlite_surroundings_preg("+",false," \"'\[`")."))?"	// $matches[5] (optional): a second column name possibly including quotes
																				//		(may be quoted in any type of quotes)
									."\s*"
									."((?:[A-Z]+\s*)+(?:\(\s*[+-]?\s*[0-9]+(?:\s*,\s*[+-]?\s*[0-9]+)?\s*\))?)?\s*"	// $matches[6] (optional): a type name
									.".*".
								")"
								."?\s*$"
							.")?\s*$/i"; // in case of DROP PRIMARY KEY, there is nothing after the command
						if($debug) $this->debugOutput .= "preg_parse_def=$preg_parse_def<br />";
						$parse_def = preg_match($preg_parse_def, $def,$matches);
						if($parse_def===false)
						{
							$this->alterError = $errormsg . $lang['alter_parse_failed'];
							if($debug) $this->debugOutput .= "ERROR: !parse_def<hr /><br />";
							return false;
						}
						if(!isset($matches[1]))
						{
							$this->alterError = $errormsg . $lang['alter_action_not_recognized'];
							if($debug) $this->debugOutput .= "ERROR: !isset(matches[1])<hr /><br />";
							return false;
						}
						$action = str_replace(' column','',strtolower($matches[1]));
						if($action == 'add primary key' && isset($matches[2]) && $matches[2]!='')
							$column = $matches[2];
						elseif($action == 'drop primary key')
							$column = '';	// DROP PRIMARY KEY has no column definition
						elseif(isset($matches[3]) && $matches[3]!='')
							$column = $this->sqliteUnquote($matches[3]);
						else
							$column = '';

						$column_escaped = str_replace("'","''",(string) $column);

						if($debug) $this->debugOutput .= "action=($action), column=($column), column_escaped=($column_escaped)<br />";

						/* we build a regex that devides the CREATE TABLE statement parts:
						  Part example                            Group  Explanation
						  1. CREATE TABLE t... (                  $1
						  2. 'col1' ..., 'col2' ..., 'colN' ...,  $3     (with col1-colN being columns that are not changed and listed before the col to change)
						  3. 'colX' ...,                                 (with colX being the column to change/drop)
						  4. 'colX+1' ..., ..., 'colK')           $5     (with colX+1-colK being columns after the column to change/drop)
						*/
						$preg_create_table = "\s*+(CREATE\s++TABLE\s++".preg_quote((string) $this->quote($tmpname),"/")."\s*+\()";   // This is group $1 (keep unchanged)
						$preg_column_definiton = "\s*+".$this->sqlite_surroundings_preg("+",true," '\"\[`,",$column)."(?:\s*+".$this->sqlite_surroundings_preg("*",false,"'\",`\[ ").")++";		// catches a complete column definition, even if it is
														// 'column' TEXT NOT NULL DEFAULT 'we have a comma, here and a double ''quote!'
														// this definition does NOT match columns with the column name $column
						if($debug) $this->debugOutput .= "preg_column_definition=(".$preg_column_definiton.")<br />";
						$preg_columns_before =  // columns before the one changed/dropped (keep)
							"(?:".
								"(".			// group $2. Keep this one unchanged!
									"(?:".
										"$preg_column_definiton,\s*+".		// column definition + comma
									")*".								// there might be any number of such columns here
									$preg_column_definiton.				// last column definition
								")".			// end of group $2
								",\s*+"			// the last comma of the last column before the column to change. Do not keep it!
							.")?";    // there might be no columns before
						if($debug) $this->debugOutput .= "preg_columns_before=(".$preg_columns_before.")<br />";
						$preg_columns_after = "(,\s*(.+))?"; // the columns after the column to drop. This is group $3 (drop) or $4(change) (keep!)
												// we could remove the comma using $6 instead of $5, but then we might have no comma at all.
												// Keeping it leaves a problem if we drop the first column, so we fix that case in another regex.
						$table_new = $table;

						switch($action)
						{
							case 'add':
								if($column=='')
								{
									$this->alterError = $errormsg . ' (add) - '. $lang['alter_no_add_col'];
									return false;
								}
								$new_col_definition = "'$column_escaped' ".($matches[4] ?? '');
								$preg_pattern_add = "/^".$preg_create_table.   // the CREATE TABLE statement ($1)
									"((?:(?!,\s*(?:PRIMARY\s+KEY\s*\(|CONSTRAINT\s|UNIQUE\s*\(|CHECK\s*\(|FOREIGN\s+KEY\s*\()).)*)". // column definitions ($2)
									"(.*)\\)\s*$/si"; // table-constraints like PRIMARY KEY(a,b) ($3) and the closing bracket
								// append the column definiton in the CREATE TABLE statement
								$newSQL = preg_replace($preg_pattern_add, '$1$2, '.strtr($new_col_definition, ['\\' => '\\\\', '$' => '\$']).' $3', $createtesttableSQL).')';
								$preg_error = $this->getPregError();
								if($debug)
								{
									$this->debugOutput .= $createtesttableSQL."<hr /><br />";
									$this->debugOutput .= $newSQL."<hr /><br />";
									$this->debugOutput .= $preg_pattern_add."<hr /><br />";
								}
								if($newSQL==$createtesttableSQL) // pattern did not match, so column adding did not succed
									{
									$this->alterError = $errormsg . ' (add) - '.$lang['alter_pattern_mismatch'].'. PREG ERROR: '.$preg_error;
									return false;
									}
								$createtesttableSQL = $newSQL;
								break;
							case 'change':
								if(!isset($matches[5]))
								{
									$this->alterError = $errormsg . ' (change) - '.$lang['alter_col_not_recognized'];
									return false;
								}
								$new_col_name = $matches[5];
								if(!isset($matches[6]))
									$new_col_type = '';
								else
									$new_col_type = $matches[6];
								$new_col_definition = "$new_col_name $new_col_type";
								$preg_column_to_change = "\s*".$this->sqlite_surroundings_preg($column)."(?:\s+".preg_quote((string) $coltypes[$column]).")?(\s+(?:".$this->sqlite_surroundings_preg("*",false,",'\"`\[").")+)?";
												// replace this part (we want to change this column)
												// group $3 contains the column constraints (keep!). the name & data type is replaced.
								$preg_pattern_change = "/^".$preg_create_table.$preg_columns_before.$preg_column_to_change.$preg_columns_after."\s*\\)\s*$/s";

								// replace the column definiton in the CREATE TABLE statement
								$newSQL = preg_replace($preg_pattern_change, '$1$2,'.strtr($new_col_definition, ['\\' => '\\\\', '$' => '\$']).'$3$4)', $createtesttableSQL);
								$preg_error = $this->getPregError();
								// remove comma at the beginning if the first column is changed
								// probably somebody is able to put this into the first regex (using lookahead probably).
								$newSQL = preg_replace("/^\s*(CREATE\s+TABLE\s+".preg_quote((string) $this->quote($tmpname),"/")."\s+\(),\s*/",'$1',$newSQL);
								if($debug)
								{
									$this->debugOutput .= "new_col_name=(".$new_col_name."), new_col_type=(".$new_col_type."), preg_column_to_change=(".$preg_column_to_change.")<hr /><br />";
									$this->debugOutput .= $createtesttableSQL."<hr /><br />";
									$this->debugOutput .= $newSQL."<hr /><br />";

									$this->debugOutput .= $preg_pattern_change."<hr /><br />";
								}
								if($newSQL==$createtesttableSQL || $newSQL=="") // pattern did not match, so column removal did not succed
								{
									$this->alterError = $errormsg . ' (change) - '.$lang['alter_pattern_mismatch'].'. PREG ERROR: '.$preg_error;
									return false;
								}
								$createtesttableSQL = $newSQL;
								$newcols[$column] = $this->sqliteUnquote($new_col_name);
								break;
							case 'drop':
								$preg_column_to_drop = "\s*".$this->sqlite_surroundings_preg($column)."\s+(?:".$this->sqlite_surroundings_preg("*",false,",'\"\[`").")+";      // delete this part (we want to drop this column)
								$preg_pattern_drop = "/^".$preg_create_table.$preg_columns_before.$preg_column_to_drop.$preg_columns_after."\s*\\)\s*$/s";

								// remove the column out of the CREATE TABLE statement
								$newSQL = preg_replace($preg_pattern_drop, '$1$2$3)', $createtesttableSQL);
								$preg_error = $this->getPregError();
								// remove comma at the beginning if the first column is removed
								// probably somebody is able to put this into the first regex (using lookahead probably).
								$newSQL = preg_replace("/^\s*(CREATE\s+TABLE\s+".preg_quote((string) $this->quote($tmpname),"/")."\s+\(),\s*/",'$1',$newSQL);
								if($debug)
								{
									$this->debugOutput .= $createtesttableSQL."<hr /><br />";
									$this->debugOutput .= $newSQL."<hr /><br />";
									$this->debugOutput .= $preg_pattern_drop."<hr /><br />";
								}
								if($newSQL==$createtesttableSQL || $newSQL=="") // pattern did not match, so column removal did not succed
								{
									$this->alterError = $errormsg . ' (drop) - '.$lang['alter_pattern_mismatch'].'. PREG ERROR: '.$preg_error;
									return false;
								}
								$createtesttableSQL = $newSQL;
								unset($newcols[$column]);
								break;
							case 'rename to':
								// don't change column definition at all
								$newSQL = $createtesttableSQL;
								// only change the name of the table
								$table_new = $column;
								break;
							case 'add primary key':
								// we want to add a primary key for the column(s) stored in $column
								$newSQL = preg_replace("/\)\s*$/", ", PRIMARY KEY (".$column.") )", $createtesttableSQL);
								$createtesttableSQL = $newSQL;
								break;
							case 'drop primary key':
								// we want to drop the primary key
								if($debug) $this->debugOutput .= "DROP";
								if(sizeof($primarykey)==1)
								{
									// if not compound primary key, might be a column constraint -> try removal
									$column = $primarykey[0];
									if($debug) $this->debugOutput .= "<br>Trying to drop column constraint for column $column <br>";
									/*
									TODO: This does not work yet:
									CREATE TABLE 't12' ('t1' INTEGER CONSTRAINT "bla" NOT NULL CONSTRAINT 'pk' PRIMARY KEY ); ALTER TABLE "t12" DROP PRIMARY KEY
									This does:                                  !   !
									CREATE TABLE 't12' ('t1' INTEGER CONSTRAINT  bla  NOT NULL CONSTRAINT 'pk' PRIMARY KEY ); ALTER TABLE "t12" DROP PRIMARY KEY
									*/
									$preg_column_to_change = "(\s*".$this->sqlite_surroundings_preg($column).")". // column ($3)
										"(?:".		// opt. type and column constraints
											"(\s+(?:".$this->sqlite_surroundings_preg("(?:[^PC,'\"`\[]|P(?!RIMARY\s+KEY)|".
											"C(?!ONSTRAINT\s+".$this->sqlite_surroundings_preg("+",false," ,'\"\[`")."\s+PRIMARY\s+KEY))",false,",'\"`\[").")*)". // column constraints before PRIMARY KEY ($3)
												// primary key constraint (remove this!):
												"(?:CONSTRAINT\s+".$this->sqlite_surroundings_preg("+",false," ,'\"\[`")."\s+)?".
												"PRIMARY\s+KEY".
												"(?:\s+(?:ASC|DESC))?".
												"(?:\s+ON\s+CONFLICT\s+(?:ROLLBACK|ABORT|FAIL|IGNORE|REPLACE))?".
												"(?:\s+AUTOINCREMENT)?".
											"((?:".$this->sqlite_surroundings_preg("*",false,",'\"`\[").")*)". // column constraints after PRIMARY KEY ($4)
										")";
													// replace this part (we want to change this column)
													// group $3 (column) $4  (constraints before) and $5 (constraints after) contain the part to keep
									$preg_pattern_change = "/^".$preg_create_table.$preg_columns_before.$preg_column_to_change.$preg_columns_after."\s*\\)\s*$/si";

									// replace the column definiton in the CREATE TABLE statement
									$newSQL = preg_replace($preg_pattern_change, '$1$2,$3$4$5$6)', $createtesttableSQL);
									// remove comma at the beginning if the first column is changed
									// probably somebody is able to put this into the first regex (using lookahead probably).
									$newSQL = preg_replace("/^\s*(CREATE\s+TABLE\s+".preg_quote((string) $this->quote($tmpname),"/")."\s+\(),\s*/",'$1',$newSQL);
									if($debug)
									{
										$this->debugOutput .= "preg_column_to_change=(".$preg_column_to_change.")<hr /><br />";
										$this->debugOutput .= $createtesttableSQL."<hr /><br />";
										$this->debugOutput .= $newSQL."<hr /><br />";

										$this->debugOutput .= $preg_pattern_change."<hr /><br />";
									}
									if($newSQL!=$createtesttableSQL && $newSQL!="") // pattern did match, so PRIMARY KEY constraint removed :)
									{
										$createtesttableSQL = $newSQL;
										if($debug) $this->debugOutput .= "<br>SUCCEEDED<br>";
									}
									else
									{
										if($debug) $this->debugOutput .= "NO LUCK";
										// TODO: try removing table constraint
										return false;
									}
									$createtesttableSQL = $newSQL;
								} else
									// TODO: Try removing table constraint
									return false;

								break;
							default:
								if($debug) $this->debugOutput .= 'ERROR: unknown alter operation!<hr /><br />';
								$this->alterError = $errormsg . $lang['alter_unknown_operation'];
								return false;
						}
					}
					$droptempsql = 'DROP TABLE '.$this->quote_id($tmpname);

					$createnewtableSQL = "CREATE TABLE ".$this->quote($table_new)." ".preg_replace("/^\s*CREATE\s+TABLE\s+'?".str_replace("'","''",preg_quote($tmpname,"/"))."'?\s+(.*)$/is", '$1', $createtesttableSQL, 1);

					$newcolumns = '';
					$oldcolumns = '';
					reset($newcols);
					foreach($newcols as $key => $val)
					{
						$newcolumns .= ($newcolumns?', ':'').$this->quote_id($val);
						$oldcolumns .= ($oldcolumns?', ':'').$this->quote_id($key);
					}
					$copytonewsql = 'INSERT INTO '.$this->quote_id($table_new).'('.$newcolumns.') SELECT '.$oldcolumns.' FROM '.$this->quote_id($tmpname);
				}
			}
			$alter_transaction  = 'BEGIN; ';
			$alter_transaction .= $createtemptableSQL.'; ';  //create temp table
			$alter_transaction .= $copytotempsql.'; ';       //copy to table
			$alter_transaction .= $dropoldsql.'; ';          //drop old table
			$alter_transaction .= $createnewtableSQL.'; ';   //recreate original table
			$alter_transaction .= $copytonewsql.'; ';        //copy back to original table
			$alter_transaction .= $droptempsql.'; ';         //drop temp table

			$preg_index="/^\s*(CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:".$this->sqlite_surroundings_preg("+",false," '\"\[`")."\s*)*ON\s+)(".$this->sqlite_surroundings_preg($table).")(\s*\((?:".$this->sqlite_surroundings_preg("+",false," '\"\[`")."\s*)*\)\s*)\s*$/i";
			foreach($recreateQueries as $recreate_query)
			{
				if($recreate_query['type']=='index')
				{
					// this is an index. We need to make sure the index is not on a column that we drop. If it is, we drop the index as well.
					$indexInfos = $this->selectArray('PRAGMA index_info('.$this->quote_id($recreate_query['name']).')');
					foreach($indexInfos as $indexInfo)
					{
						if(!isset($newcols[$indexInfo['name']]))
						{
							if($debug) $this->debugOutput .= 'Not recreating the following index: <hr /><br />'.htmlencode($recreate_query['sql']).'<hr /><br />';
							// Index on a column that was dropped. Skip recreation.
							continue 2;
						}
					}
				}
				// TODO: In case we renamed a column on which there is an index, we need to recreate the index with the column name adjusted.

				// recreate triggers / indexes
				if($table == $table_new)
				{
					// we had no RENAME TO, so we can recreate indexes/triggers just like the original ones
					$alter_transaction .= $recreate_query['sql'].';';
				} else
				{
					// we had a RENAME TO, so we need to exchange the table-name in the CREATE-SQL of triggers & indexes
					switch ($recreate_query['type'])
					{
						case 'index':
							$recreate_queryIndex = preg_replace($preg_index, '$1'.$this->quote_id(strtr($table_new, ['\\' => '\\\\', '$' => '\$'])).'$3 ', (string) $recreate_query['sql']);
							if($recreate_queryIndex!=$recreate_query['sql'] && $recreate_queryIndex != NULL)
								$alter_transaction .= $recreate_queryIndex.';';
							else
							{
								// the CREATE INDEX regex did not match. this normally should not happen
								if($debug) $this->debugOutput .= 'ERROR: CREATE INDEX regex did not match!?<hr /><br />';
								// just try to recreate the index originally (will fail most likely)
								$alter_transaction .= $recreate_query['sql'].';';
							}
							break;

						case 'trigger':
							// TODO: IMPLEMENT
							$alter_transaction .= $recreate_query['sql'].';';
							break;
						default:
							if($debug) $this->debugOutput .= 'ERROR: Unknown type '.htmlencode($recreate_query['type']).'<hr /><br />';
							$alter_transaction .= $recreate_query['sql'].';';
					}
				}
			}
			$alter_transaction .= 'COMMIT;';
			if($debug) $this->debugOutput .= $alter_transaction;
			return $this->multiQuery($alter_transaction);
		}
	}

	//multiple query execution
	//returns true on success, false otherwise. Use getError() to fetch the error.
	public function multiQuery($query)
	{
		if($this->type=="PDO")
			$success = $this->db->exec($query);
		else if($this->type=="SQLite3")
			$success = $this->db->exec($query);
		else
			$success = $this->db->queryExec($query, $error);
		return $success;
	}


	// checks whether a table has a primary key
	public function hasPrimaryKey($table)
	{
		$table_info = $this->getTableInfo($table);
		foreach($table_info as $row_id => $row_data)
		{
			if($row_data['pk'])
			{
				return true;
			}

		}
		return false;
	}

	// Returns an array of columns by which rows can be uniquely adressed.
	// For tables with a rowid column, this is always array('rowid')
	// for tables without rowid, this is an array of the primary key columns.
	public function getPrimaryKey($table)
	{
		$primary_key = [];
		// check if this table has a rowid
		$getRowID = $this->select('SELECT ROWID FROM '.$this->quote_id($table).' LIMIT 0,1');
		if(isset($getRowID[0]))
			// it has, so we prefer addressing rows by rowid
			return ['rowid'];
		else
		{
			// the table is without rowid, so use the primary key
			$table_info = $this->getTableInfo($table);
			if(is_array($table_info))
			{
				foreach($table_info as $row_id => $row_data)
				{
					if($row_data['pk'])
						$primary_key[] = $row_data['name'];
				}
			}
		}
		return $primary_key;
	}

	// selects a row by a given key $pk, which is an array of values
	// for the columns by which a row can be adressed (rowid or primary key)
	public function wherePK($table, $pk)
	{
		$where = "";
		$primary_key = $this->getPrimaryKey($table);
		foreach($primary_key as $pk_index => $column)
		{
			if($where!="")
				$where .= " AND ";
			$where .= $this->quote_id($column) . ' = ';
			if(is_int($pk[$pk_index]) || is_float($pk[$pk_index]))
				$where .= $pk[$pk_index];
			else
				$where .= $this->quote($pk[$pk_index]);
		}
		return $where;
	}

	//get number of rows in table
	public function numRows($table, $dontTakeLong = false)
	{
		// as Count(*) can be slow on huge tables without PK,
		// if $dontTakeLong is set and the size is > 2MB only count() if there is a PK
		if(!$dontTakeLong || $this->getSize() <= 2000 || $this->hasPrimaryKey($table))
		{
			$result = $this->select("SELECT Count(*) FROM ".$this->quote_id($table));
			return $result[0];
		} else
		{
			return '?';
		}
	}

	//correctly escape a string to be injected into an SQL query
	public function quote($value)
	{
		if($this->type=="PDO")
		{
			// PDO quote() escapes and adds quotes
			return $this->db->quote($value);
		}
		else if($this->type=="SQLite3")
		{
			return "'".$this->db->escapeString($value)."'";
		}
		else
		{
			return "'".sqlite_escape_string($value)."'";
		}
	}

	//correctly escape an identifier (column / table / trigger / index name) to be injected into an SQL query
	public function quote_id($value)
	{
		// double-quotes need to be escaped by doubling them
		$value = str_replace('"','""',(string) $value);
		return '"'.$value.'"';
	}


	//import sql
	//returns true on success, error message otherwise
	public function import_sql($query)
	{
		$import = $this->multiQuery($query);
		if(!$import)
			return $this->getError();
		else
			return true;
	}

	public function prepareQuery($query)
	{
		if($this->type=='PDO' || $this->type=='SQLite3')
			return $this->db->prepare($query);
		else
		{
			// here we are in trouble, SQLiteDatabase cannot prepare statements.
			// we need to emulate prepare as best as we can
			# todo: implement this
			return null;
		}
	}

	public function bindValue($handle, $parameter, $value, $type)
	{
		if($this->type=='SQLite3')
		{
			$types = ['bool'=>SQLITE3_INTEGER, 'int'=>SQLITE3_INTEGER, 'float'=>SQLITE3_FLOAT, 'text'=>SQLITE3_TEXT, 'blob'=>SQLITE3_BLOB, 'null'=>SQLITE3_NULL];
			if(!isset($types[$type]))
				$type = 'text';
			// there is no SQLITE_BOOL, so check value and make sure it is 0/1
			if($type=='bool')
			{
				if($value===1 || $value===true)
					$value=1;
				elseif($value===0 || $value===false)
					$value=0;
				else
					return false;
			}
			return $handle->bindValue($parameter, $value, $types[$type]);
		}
		if($this->type=='PDO')
		{
			$types = ['bool'=>PDO::PARAM_BOOL, 'int'=>PDO::PARAM_INT, 'float'=>PDO::PARAM_STR, 'text'=>PDO::PARAM_STR, 'blob'=>PDO::PARAM_LOB, 'null'=>PDO::PARAM_NULL];
			if(!isset($types[$type]))
				$type = 'text';
			// there is no PDO::PARAM_FLOAT, so we check it ourself
			if($type=='float')
			{
				if(is_numeric($value))
					$value = (float) $value;
				else
					return false;
			}
			return $handle->bindValue($parameter, $value, $types[$type]);
		}
		else
			# todo: workaround
			return false;

	}

	public function executePrepared($handle, $fetchResult=false)
	{
		if($this->type=='PDO')
		{
			$ok=$handle->execute();
			if($fetchResult && $ok)
			{
				$res = $handle->fetchAll();
				$handle->closeCursor();
				return $res;
			}
			else
			{
				if($ok)
					$handle->closeCursor();
				return $ok;
			}
		}
		elseif($this->type=='SQLite3')
		{
			$resultset=$handle->execute();
			if($fetchResult && $resultset!==false)
			{
				$res = $resultset->fetchArray();
				$resultset->finalize();
				return $res;
			}
			else
			{
				if($resultset!==false)
					$resultset->finalize();
				if($resultset===false)
					return false;
				else
					return true;
			}
		}
		else
		{
			#todo.
			return false;
		}
	}

	//import csv
	//returns true on success, error message otherwise
	public function import_csv($filename, $table, $field_terminate, $field_enclosed, $field_escaped, $null, $fields_in_first_row)
	{
		@set_time_limit(-1);
		$csv_handle = fopen($filename,'r');
		$csv_insert = "BEGIN;\n";
		$csv_number_of_rows = 0;
		// PHP requires enclosure defined, but has no problem if it was not used
		if($field_enclosed=="") $field_enclosed='"';
		// PHP requires escaper defined
		if($field_escaped=="") $field_escaped='\\';
		// support tab delimiters
		if($field_terminate=='\t') $field_terminate = "\t";
		while($csv_handle!==false && !feof($csv_handle))
		{
			$csv_data = fgetcsv($csv_handle, 0, $field_terminate, $field_enclosed, (string) $field_escaped);
			if(is_array($csv_data) && ($csv_data[0] != NULL || count($csv_data)>1))
			{
				$csv_number_of_rows++;
				if($csv_number_of_rows==1)
				{
					if($this->getTypeOfTable($table)!="table")
					{
						// First,Create a new table
						$csv_insert .="CREATE TABLE ".$this->quote($table)." (";
						$number_of_cols = count($csv_data);
						foreach($csv_data as $csv_col => $csv_cell)
						{
							if($fields_in_first_row)
								$csv_insert .= $this->quote($csv_cell);
							else
								$csv_insert.= $this->quote("col{$csv_col}");
							if($csv_col < $number_of_cols-1)
								$csv_insert .= ", ";
						}
						$csv_insert .=");";

					} else {
						$number_of_cols = count($this->getTableInfo($table));
					}
					if($fields_in_first_row)
						continue;
				}
				$csv_insert .= "INSERT INTO ".$this->quote_id($table)." VALUES (";
				for($csv_col = 0; $csv_col < $number_of_cols; $csv_col++)
				{
					if(isset($csv_data[$csv_col]))
						$csv_cell = $csv_data[$csv_col];
					else
						$csv_cell = $null;
					if($csv_cell == $null)
						$csv_insert .= "NULL";
					else
						$csv_insert.= $this->quote($csv_cell);
					if($csv_col < $number_of_cols-1)
						$csv_insert .= ",";
				}
				$csv_insert .= ");\n";

				if($csv_number_of_rows % 5000 == 0)
				{
					$csv_insert .= "COMMIT;\nBEGIN;\n";
				}
			}
		}
		if($csv_handle === false)
			return "Error reading CSV file";
		else
		{
			$csv_insert .= "COMMIT;";
			fclose($csv_handle);
			$import = $this->multiQuery($csv_insert);
			if(!$import)
				return $this->getError();
			else
				return true;
		}
	}

	//export csv
	public function export_csv($tables, $field_terminate, $field_enclosed, $field_escaped, $null, $crlf, $fields_in_first_row): void
	{
		@set_time_limit(-1);
		// we use \r\n if the _client_ OS is windows (as the exported file is downloaded to the client), \n otherwise
		$crlf = (isset($_SERVER['HTTP_USER_AGENT']) && str_contains((string) $_SERVER['HTTP_USER_AGENT'], 'Win') ? "\r\n" : "\n");

		$query = "SELECT * FROM sqlite_master WHERE type='table' or type='view' ORDER BY type DESC";
		$result = $this->selectArray($query);
		for($i=0; $i<sizeof($result); $i++)
		{
			$valid = false;
			for($j=0; $j<sizeof($tables); $j++)
			{
				if($result[$i]['tbl_name']==$tables[$j])
					$valid = true;
			}
			if($valid)
			{
				$temp = $this->getTableInfo($result[$i]['tbl_name']);
				$cols = [];
				for($z=0; $z<sizeof($temp); $z++)
					$cols[$z] = $temp[$z][1];
				if($fields_in_first_row)
				{
					for($z=0; $z<sizeof($cols); $z++)
					{
						echo $field_enclosed.$cols[$z].$field_enclosed;
						// do not terminate the last column!
						if($z < sizeof($cols)-1)
							echo $field_terminate;
					}
					echo $crlf;
				}
				$query = "SELECT * FROM ".$this->quote_id($result[$i]['tbl_name']);
				$table_result = $this->query($query);
				$firstRow=true;
				while($row = $this->fetch($table_result, "assoc"))
				{
					if(!$firstRow)
						echo $crlf;
					else
						$firstRow=false;

					for($y=0; $y<sizeof($cols); $y++)
					{
						$cell = $row[$cols[$y]];
						if($crlf)
						{
							$cell = str_replace("\n","", (string) $cell);
							$cell = str_replace("\r","", $cell);
						}
						$cell = str_replace($field_terminate,$field_escaped.$field_terminate,$cell);
						$cell = str_replace($field_enclosed,$field_escaped.$field_enclosed,$cell);
						// do not enclose NULLs
						if($cell == NULL)
							echo $null;
						else
							echo $field_enclosed.$cell.$field_enclosed;
						// do not terminate the last column!
						if($y < sizeof($cols)-1)
							echo $field_terminate;
					}
				}
				if($i<sizeof($result)-1)
					echo $crlf;
			}
		}
	}

	//export sql
	public function export_sql($tables, $drop, $structure, $data, $transaction, $comments, $echo=true)
	{
		global $lang;
		@set_time_limit(-1);
		// we use \r\n if the _client_ OS is windows (as the exported file is downloaded to the client), \n otherwise
		$crlf = (isset($_SERVER['HTTP_USER_AGENT']) && str_contains((string) $_SERVER['HTTP_USER_AGENT'], 'Win') ? "\r\n" : "\n");

		if(!$echo)
			ob_start();

		if($comments)
		{
			echo "----".$crlf;
			echo "-- ".PROJECT." ".$lang['db_dump']." (".PROJECT_URL.")".$crlf;
			echo "-- ".PROJECT." ".$lang['ver'].": ".VERSION.$crlf;
			echo "-- ".$lang['exported'].": ".date($lang['date_format']).$crlf;
			echo "-- ".$lang['db_f'].": ".$this->getPath().$crlf;
			echo "----".$crlf;
		}
		$query = "SELECT * FROM sqlite_master WHERE type='table' OR type='index' OR type='view' OR type='trigger' ORDER BY type='trigger', type='index', type='view', type='table'";
		$result = $this->selectArray($query);

		if($transaction)
			echo "BEGIN TRANSACTION;".$crlf;

		//iterate through each table
		for($i=0; $i<sizeof($result); $i++)
		{
			$valid = false;
			for($j=0; $j<sizeof($tables); $j++)
			{
				if($result[$i]['tbl_name']==$tables[$j])
					$valid = true;
			}
			if($valid)
			{
				if($drop)
				{
					if($comments)
					{
						echo "\r\n----".$crlf;
						echo "-- ".$lang['drop']." ".$result[$i]['type']." ".$lang['for']." ".$result[$i]['name'].$crlf;
						echo "----".$crlf;
					}
					echo "DROP ".strtoupper((string) $result[$i]['type'])." IF EXISTS ".$this->quote_id($result[$i]['name']).";".$crlf;
				}
				if($structure)
				{
					if($comments)
					{
						echo "\r\n----".$crlf;
						if($result[$i]['type']=="table" || $result[$i]['type']=="view")
							echo "-- ".ucfirst((string) $result[$i]['type'])." ".$lang['struct_for']." ".$result[$i]['tbl_name'].$crlf;
						else // index or trigger
							echo "-- ".$lang['struct_for']." ".$result[$i]['type']." ".$result[$i]['name']." ".$lang['on_tbl']." ".$result[$i]['tbl_name'].$crlf;
						echo "----".$crlf;
					}
					echo $result[$i]['sql'].";".$crlf;
				}
				if($data && $result[$i]['type']=="table")
				{
					$query = "SELECT * FROM ".$this->quote_id($result[$i]['tbl_name']);
					$table_result = $this->query($query, "assoc");

					if($comments)
					{
						$numRows = $this->numRows($result[$i]['tbl_name']);
						echo "\r\n----".$crlf;
						echo "-- ".$lang['data_dump']." ".$result[$i]['tbl_name'].", ".sprintf($lang['total_rows'], $numRows).$crlf;
						echo "----".$crlf;
					}
					$temp = $this->getTableInfo($result[$i]['tbl_name']);
					$cols = [];
					$cols_quoted = [];
					for($z=0; $z<sizeof($temp); $z++)
					{
						$cols[$z] = $temp[$z][1];
						$cols_quoted[$z] = $this->quote_id($temp[$z][1]);
					}
					while($row = $this->fetch($table_result))
					{
						$vals = [];
						for($y=0; $y<sizeof($cols); $y++)
						{
							if($row[$cols[$y]] === NULL)
								$vals[$cols[$y]] = 'NULL';
							else
								$vals[$cols[$y]] = $this->quote($row[$cols[$y]]);
						}
						echo "INSERT INTO ".$this->quote_id($result[$i]['tbl_name'])." (".implode(",", $cols_quoted).") VALUES (".implode(",", $vals).");".$crlf;
					}
				}
			}
		}
		if($transaction)
			echo "COMMIT;".$crlf;

		if(!$echo) {
			$o = ob_get_contents();
			ob_end_clean();
			return $o;
		}

	}
}
