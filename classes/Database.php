<?php
// Database class
// Generic database abstraction class to manage interaction with database without worrying about SQLite vs. PHP versions
//
class Database
{
	protected $db; //reference to the DB object
	protected $type; //the extension for PHP that handles SQLite
	protected $data;
	protected $lastResult;
	protected $alterError;

	public function __construct($data)
	{
		global $lang;
		$this->data = $data;
		try
		{
			if(!file_exists($this->data["path"]) && !is_writable(dirname($this->data["path"]))) //make sure the containing directory is writable if the database does not exist
			{
				echo "<div class='confirm' style='margin:20px;'>";
				printf($lang['db_not_writeable'], htmlencode($this->data["path"]), htmlencode(dirname($this->data["path"])));
				echo "<form action='".PAGE."' method='post'>";
				echo "<input type='submit' value='Log Out' name='".$lang['logout']."' class='btn'/>";
				echo "</form>";
				echo "</div><br/>";
				exit();
			}

			$ver = $this->getVersion();

			switch(true)
			{
				case (FORCETYPE=="PDO" || ((FORCETYPE==false || $ver!=-1) && class_exists("PDO") && ($ver==-1 || $ver==3))):
					$this->db = new PDO("sqlite:".$this->data['path']);
					if($this->db!=NULL)
					{
						$this->type = "PDO";
						break;
					}
				case (FORCETYPE=="SQLite3" || ((FORCETYPE==false || $ver!=-1) && class_exists("SQLite3") && ($ver==-1 || $ver==3))):
					$this->db = new SQLite3($this->data['path']);
					if($this->db!=NULL)
					{
						$this->type = "SQLite3";
						break;
					}
				case (FORCETYPE=="SQLiteDatabase" || ((FORCETYPE==false || $ver!=-1) && class_exists("SQLiteDatabase") && ($ver==-1 || $ver==2))):
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
		}
		catch(Exception $e)
		{
			$this->showError();
			exit();
		}
	}

	public function registerUserFunction($ids)
	{
		// in case a single function id was passed
		if (is_string($ids))
			$ids = array($ids);

		if ($this->type == 'PDO') {
			foreach ($ids as $id) {
				$this->db->sqliteCreateFunction($id, $id, 1);
			}
		} else { // type is Sqlite3 or SQLiteDatabase
			foreach ($ids as $id) {
				$this->db->createFunction($id, $id, 1);
			}
		}
	}
	
	public function getError()
	{
		if($this->alterError!='')
		{
			$error = $this->alterError;
			$this->alterError = "";
			return $error;
		}
		else if($this->type=="PDO")
		{
			$e = $this->db->errorInfo();
			return $e[2];
		}
		else if($this->type=="SQLite3")
		{
			return $this->db->lastErrorMsg();
		}
		else
		{
			return sqlite_error_string($this->db->lastError());
		}
	}
	
	public function showError()
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
			if(!$classPDO && !$classSQLite3 && $this->getVersion()==3)
				printf($lang['sqlite_v_error'], 3, PROJECT, 2);
			else if(!$classSQLiteDatabase && $this->getVersion()==2)
				printf($lang['sqlite_v_error'], 2, PROJECT, 3);
			else
				echo $lang['report_issue'].' '.PROJECT_BUGTRACKER_LINK.'.';
		}
		echo "<br />See <a href='https://code.google.com/p/phpliteadmin/wiki/Installation'>https://code.google.com/p/phpliteadmin/wiki/Installation</a> for help.";
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
			$content = strtolower(file_get_contents($this->data['path'], NULL, NULL, 0, 40)); //get the first 40 characters of the database file
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

	//get the size of the database (in KB)
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
	//returns false on error and the query result on success
	public function query($query, $ignoreAlterCase=false)
	{
		global $debug;
		if(strtolower(substr(ltrim($query),0,5))=='alter' && $ignoreAlterCase==false) //this query is an ALTER query - call the necessary function
		{
			preg_match("/^\s*ALTER\s+TABLE\s+\"((?:[^\"]|\"\")+)\"\s+(.*)$/i",$query,$matches);
			if(!isset($matches[1]) || !isset($matches[2]))
			{
				if($debug) echo "<span title='".htmlencode($query)."' onclick='this.innerHTML=\"".htmlencode(str_replace('"','\"',$query))."\"' style='cursor:pointer'>SQL?</span><br />";
				return false;
			}
			$tablename = str_replace('""','"',$matches[1]);
			$alterdefs = $matches[2];
			if($debug) echo "ALTER TABLE QUERY=(".htmlencode($query)."), tablename=($tablename), alterdefs=($alterdefs)<hr>";
			$result = $this->alterTable($tablename, $alterdefs);
		}
		else //this query is normal - proceed as normal
		{
			$result = $this->db->query($query);
			if($debug) echo "<span title='".htmlencode($query)."' onclick='this.innerHTML=\"".htmlencode(str_replace('"','\"',$query))."\"' style='cursor:pointer'>SQL?</span><br />";
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
		//make sure the result is valid
		if($result=== false || $result===NULL) 
			return NULL;		// error
		if(!is_object($result)) // no rows returned
			return array();
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

	
	// SQlite supports multiple ways of surrounding names in quotes:
	// single-quotes, double-quotes, backticks, square brackets.
	// As sqlite does not keep this strict, we also need to be flexible here.
	// This function generates a regex that matches any of the possibilities.
	private function sqlite_surroundings_preg($name,$preg_quote=true,$notAllowedCharsIfNone="'\"",$notAllowedName=false)
	{
		if($name=="*" || $name=="+")
		{
			if($notAllowedName!==false && $preg_quote)
				$notAllowedName = preg_quote($notAllowedName,"/");
			// use possesive quantifiers to save memory
			$nameSingle   = ($notAllowedName!==false?"(?!".$notAllowedName."')":"")."(?:[^']$name+|'')$name+";
			$nameDouble   = ($notAllowedName!==false?"(?!".$notAllowedName."\")":"")."(?:[^\"]$name+|\"\")$name+";
			$nameBacktick = ($notAllowedName!==false?"(?!".$notAllowedName."`)":"")."(?:[^`]$name+|``)$name+";
			$nameSquare   = ($notAllowedName!==false?"(?!".$notAllowedName."\])":"")."(?:[^\]]$name+|\]\])$name+";
			$nameNo = ($notAllowedName!==false?"(?!".$notAllowedName."\s)":"")."[^".$notAllowedCharsIfNone."]$name";
		}
		else
		{
			if($preg_quote) $name = preg_quote($name,"/");
			
			$nameSingle = str_replace("'","''",$name);
			$nameDouble = str_replace('"','""',$name);
			$nameBacktick = str_replace('`','``',$name);
			$nameSquare = str_replace(']',']]',$name);
			$nameNo = $name;
		}
		
		$preg =	"(?:'".$nameSingle."'|".   // single-quote surrounded or not in quotes (correct SQL for values/new names)
				$nameNo."|".               // not surrounded (correct SQL if not containing reserved words, spaces or some special chars)
				"\"".$nameDouble."\"|".    // double-quote surrounded (correct SQL for identifiers)
				"`".$nameBacktick."`|".    // backtick surrounded (MySQL-Style)
				"\[".$nameSquare."\])";    // square-bracket surrounded (MS Access/SQL server-Style)
		return $preg;
	}
	
	// Returns the last PREG error as a string, '' if no error occured
	private function getPregError()
	{
		$error = preg_last_error();
		switch ($error)
		{
			case PREG_NO_ERROR: return 'No error';
			case PREG_INTERNAL_ERROR: return 'There is an internal error!';
			case PREG_BACKTRACK_LIMIT_ERROR: return 'Backtrack limit was exhausted!';
			case PREG_RECURSION_LIMIT_ERROR: return 'Recursion limit was exhausted!';
			case PREG_BAD_UTF8_ERROR: return 'Bad UTF8 error!';
			case PREG_BAD_UTF8_ERROR: return 'Bad UTF8 offset error!';
			default: return 'Unknown Error';
		} 
	}
	
	// function that is called for an alter table statement in a query
	// code borrowed with permission from http://code.jenseng.com/db/
	// this has been completely debugged / rewritten by Christopher Kramer
	public function alterTable($table, $alterdefs)
	{
		global $debug, $lang;
		$this->alterError="";
		$errormsg = sprintf($lang['alter_failed'],htmlencode($table)).' - ';
		if($debug) echo "ALTER TABLE: table=($table), alterdefs=($alterdefs)<hr>";
		if($alterdefs != '')
		{
			$recreateQueries = array();
			$resultArr = $this->selectArray("SELECT sql,name,type FROM sqlite_master WHERE tbl_name = ".$this->quote($table));
			if(sizeof($resultArr)<1)
			{
				$this->alterError = $errormsg . sprintf($lang['tbl_inexistent'], htmlencode($table));
				if($debug) echo "ERROR: unknown table<hr>";
				return false;
			}
			for($i=0; $i<sizeof($resultArr); $i++)
			{
				$row = $resultArr[$i];
				if($row['type'] != 'table')
				{
					if($row['sql']!='')
					{
						// store the CREATE statements of triggers and indexes to recreate them later
						$recreateQueries[] = $row;
						if($debug) echo "recreate=(".$row['sql'].";)<hr />";
					}
				}
				else
				{
					// ALTER the table
					$tmpname = 't'.time();
					$origsql = $row['sql'];
					$preg_remove_create_table = "/^\s*+CREATE\s++TABLE\s++".$this->sqlite_surroundings_preg($table)."\s*+(\(.*+)$/is";
					$origsql_no_create = preg_replace($preg_remove_create_table, '$1', $origsql, 1);
					if($debug) echo "origsql=($origsql)<br />preg_remove_create_table=($preg_remove_create_table)<hr>";
					if($origsql_no_create == $origsql)
					{
						$this->alterError = $errormsg . $lang['alter_tbl_name_not_replacable'];
						if($debug) echo "ERROR: could not get rid of CREATE TABLE<hr />";
						return false;
					}
					$createtemptableSQL = "CREATE TEMPORARY TABLE ".$this->quote($tmpname)." ".$origsql_no_create;
					if($debug) echo "createtemptableSQL=($createtemptableSQL)<hr>";
					$createindexsql = array();
					$preg_alter_part = "/(?:DROP(?! PRIMARY KEY)|ADD(?! PRIMARY KEY)|CHANGE|RENAME TO|ADD PRIMARY KEY|DROP PRIMARY KEY)" // the ALTER command
						."(?:"
							."\s+\(".$this->sqlite_surroundings_preg("+",false,"\"'\[`)")."+\)"	// stuff in brackets (in case of ADD PRIMARY KEY)
						."|"																	// or
							."\s+".$this->sqlite_surroundings_preg("+",false,",'\"\[`")			// column names and stuff like this
						.")*/i";
					if($debug)
						echo "preg_alter_part=(".$preg_alter_part.")<hr />";
					preg_match_all($preg_alter_part,$alterdefs,$matches);
					$defs = $matches[0];
					
					$get_oldcols_query = "PRAGMA table_info(".$this->quote_id($table).")";
					$result_oldcols = $this->selectArray($get_oldcols_query);
					$newcols = array();
					$coltypes = array();
					$primarykey = array();
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
					while(list($key, $val) = each($newcols))
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
						if($debug) echo "ERROR: defs&lt;1<hr />";
						return false;
					}
					foreach($defs as $def)
					{
						if($debug) echo "def=$def<hr />";
						$parse_def = preg_match(
							"/^(DROP(?! PRIMARY KEY)|ADD(?! PRIMARY KEY)|CHANGE|RENAME TO|ADD PRIMARY KEY|DROP PRIMARY KEY)" // $matches[1]: command
							."(?:"												// this is either
								."(?:\s+\((.+)\)\s*$)"							// anything in brackets (for ADD PRIMARY KEY)
																				// then $matches[2] is what there is in brackets
							."|"												// OR: 
								."(?:\s+\"((?:[^\"]|\"\")+)\"|'((?:[^']|'')+)')"// (first) column name, either in single or double quotes
																				// in case of RENAME TO, it is the new a table name
																				// $matches[3] will be the column/table name without the quotes if double quoted
																				// $matches[4] will be the column/table name without the quotes if single quoted
								."("											// $matches[5]: anything after the column name
									."(?:\s+'((?:[^']|'')+)')?"					// $matches[6] (optional): a second column name surrounded with single quotes
																				//		(the match does not contain the quotes) 
									."\s+"
									."((?:[A-Z]+\s*)+(?:\(\s*[+-]?\s*[0-9]+(?:\s*,\s*[+-]?\s*[0-9]+)?\s*\))?)\s*"	// $matches[7]: a type name
									.".*".
								")"
								."?\s*$"
							.")?/i", // in case of DROP PRIMARY KEY, there is nothing after the command
							$def,$matches);
						if($parse_def===false)
						{
							$this->alterError = $errormsg . $lang['alter_parse_failed'];
							if($debug) echo "ERROR: !parse_def<hr />";
							return false;
						}
						if(!isset($matches[1]))
						{
							$this->alterError = $errormsg . $lang['alter_action_not_recognized'];
							if($debug) echo "ERROR: !isset(matches[1])<hr />";
							return false;
						}
						$action = strtolower($matches[1]);
						if($action == 'add' || $action == 'rename to')	
							$column = str_replace("''","'",$matches[4]);		// enclosed in ''
						elseif($action == 'add primary key')
							$column = $matches[2];	
						elseif($action == 'drop primary key')
							$column = '';	// DROP PRIMARY KEY has no column definition
						else
							$column = str_replace('""','"',$matches[3]);		// enclosed in ""

						$column_escaped = str_replace("'","''",$column);

						if($debug) echo "action=($action), column=($column), column_escaped=($column_escaped)<hr />";

						/* we build a regex that devides the CREATE TABLE statement parts:
						  Part example                            Group  Explanation
						  1. CREATE TABLE t... (                  $1
						  2. 'col1' ..., 'col2' ..., 'colN' ...,  $3     (with col1-colN being columns that are not changed and listed before the col to change)
						  3. 'colX' ...,                                 (with colX being the column to change/drop)
						  4. 'colX+1' ..., ..., 'colK')           $5     (with colX+1-colK being columns after the column to change/drop)
						*/
						$preg_create_table = "\s*+(CREATE\s++TEMPORARY\s++TABLE\s++".preg_quote($this->quote($tmpname),"/")."\s*+\()";   // This is group $1 (keep unchanged)
						$preg_column_definiton = "\s*+".$this->sqlite_surroundings_preg("+",true," '\"\[`,",$column)."(?:\s*+".$this->sqlite_surroundings_preg("*",false,"'\",`\[ ").")++";		// catches a complete column definition, even if it is
														// 'column' TEXT NOT NULL DEFAULT 'we have a comma, here and a double ''quote!'
														// this definition does NOT match columns with the column name $column
						if($debug) echo "preg_column_definition=(".$preg_column_definiton.")<hr />";
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
						if($debug) echo "preg_columns_before=(".$preg_columns_before.")<hr />";
						$preg_columns_after = "(,\s*(.+))?"; // the columns after the column to drop. This is group $3 (drop) or $4(change) (keep!)
												// we could remove the comma using $6 instead of $5, but then we might have no comma at all.
												// Keeping it leaves a problem if we drop the first column, so we fix that case in another regex.
						$table_new = $table;
	
						switch($action)
						{
							case 'add':
								if(!isset($matches[5]))
								{
									$this->alterError = $errormsg . ' (add) - '. $lang['alter_no_add_col'];
									return false;
								}
								$new_col_definition = "'$column_escaped' ".$matches[5];
								$preg_pattern_add = "/^".$preg_create_table.   // the CREATE TABLE statement ($1)
									"((?:(?!,\s*(?:PRIMARY\s+KEY\s*\(|CONSTRAINT\s|UNIQUE\s*\(|CHECK\s*\(|FOREIGN\s+KEY\s*\()).)*)". // column definitions ($2)
									"(.*)\\)\s*$/si"; // table-constraints like PRIMARY KEY(a,b) ($3) and the closing bracket
								// append the column definiton in the CREATE TABLE statement
								$newSQL = preg_replace($preg_pattern_add, '$1$2, '.strtr($new_col_definition, array('\\' => '\\\\', '$' => '\$')).' $3', $createtesttableSQL).')';
								$preg_error = $this->getPregError();
								if($debug)
								{
									echo $createtesttableSQL."<hr>";
									echo $newSQL."<hr>";
									echo $preg_pattern_add."<hr>";
								}
								if($newSQL==$createtesttableSQL) // pattern did not match, so column adding did not succed
									{
									$this->alterError = $errormsg . ' (add) - '.$lang['alter_pattern_mismatch'].'. PREG ERROR: '.$preg_error;
									return false;
									}
								$createtesttableSQL = $newSQL;
								break;
							case 'change':
								if(!isset($matches[6]) || !isset($matches[7]))
								{
									$this->alterError = $errormsg . ' (change) - '.$lang['alter_col_not_recognized'];
									return false;
								}
								$new_col_name = $matches[6];
								$new_col_type = $matches[7];
								$new_col_definition = "'$new_col_name' $new_col_type";
								$preg_column_to_change = "\s*".$this->sqlite_surroundings_preg($column)."(?:\s+".preg_quote($coltypes[$column]).")?(\s+(?:".$this->sqlite_surroundings_preg("*",false,",'\"`\[").")+)?";
												// replace this part (we want to change this column)
												// group $3 contains the column constraints (keep!). the name & data type is replaced.
								$preg_pattern_change = "/^".$preg_create_table.$preg_columns_before.$preg_column_to_change.$preg_columns_after."\s*\\)\s*$/s";

								// replace the column definiton in the CREATE TABLE statement
								$newSQL = preg_replace($preg_pattern_change, '$1$2,'.strtr($new_col_definition, array('\\' => '\\\\', '$' => '\$')).'$3$4)', $createtesttableSQL);
								$preg_error = $this->getPregError();
								// remove comma at the beginning if the first column is changed
								// probably somebody is able to put this into the first regex (using lookahead probably).
								$newSQL = preg_replace("/^\s*(CREATE\s+TEMPORARY\s+TABLE\s+".preg_quote($this->quote($tmpname),"/")."\s+\(),\s*/",'$1',$newSQL);
								if($debug)
								{
									echo "preg_column_to_change=(".$preg_column_to_change.")<hr />";
									echo $createtesttableSQL."<hr />";
									echo $newSQL."<hr />";

									echo $preg_pattern_change."<hr />";
								}
								if($newSQL==$createtesttableSQL || $newSQL=="") // pattern did not match, so column removal did not succed
								{
									$this->alterError = $errormsg . ' (change) - '.$lang['alter_pattern_mismatch'].'. PREG ERROR: '.$preg_error;
									return false;
								}
								$createtesttableSQL = $newSQL;
								$newcols[$column] = str_replace("''","'",$new_col_name);
								break;
							case 'drop':
								$preg_column_to_drop = "\s*".$this->sqlite_surroundings_preg($column)."\s+(?:".$this->sqlite_surroundings_preg("*",false,",'\"\[`").")+";      // delete this part (we want to drop this column)
								$preg_pattern_drop = "/^".$preg_create_table.$preg_columns_before.$preg_column_to_drop.$preg_columns_after."\s*\\)\s*$/s";

								// remove the column out of the CREATE TABLE statement
								$newSQL = preg_replace($preg_pattern_drop, '$1$2$3)', $createtesttableSQL);
								$preg_error = $this->getPregError();
								// remove comma at the beginning if the first column is removed
								// probably somebody is able to put this into the first regex (using lookahead probably).
								$newSQL = preg_replace("/^\s*(CREATE\s+TEMPORARY\s+TABLE\s+".preg_quote($this->quote($tmpname),"/")."\s+\(),\s*/",'$1',$newSQL);
								if($debug)
								{
									echo $createtesttableSQL."<hr>";
									echo $newSQL."<hr>";
									echo $preg_pattern_drop."<hr>";
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
								if($debug) echo "DROP";
								if(sizeof($primarykey)==1)
								{
									// if not compound primary key, might be a column constraint -> try removal
									$column = $primarykey[0];
									if($debug) echo "<br>Trying to drop column constraint for column $column <br>";
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
									$newSQL = preg_replace("/^\s*(CREATE\s+TEMPORARY\s+TABLE\s+".preg_quote($this->quote($tmpname),"/")."\s+\(),\s*/",'$1',$newSQL);
									if($debug)
									{
										echo "preg_column_to_change=(".$preg_column_to_change.")<hr />";
										echo $createtesttableSQL."<hr />";
										echo $newSQL."<hr />";
	
										echo $preg_pattern_change."<hr />";
									}
									if($newSQL!=$createtesttableSQL && $newSQL!="") // pattern did match, so PRIMARY KEY constraint removed :)
									{
										$createtesttableSQL = $newSQL;
										if($debug) echo "<br>SUCCEEDED<br>";
									}
									else
									{
										if($debug) echo "NO LUCK";
										// TODO: try removing table constraint
										return false;
									}
									$createtesttableSQL = $newSQL;
								} else
									// TODO: Try removing table constraint
									return false;
								
								break;
							default:
								if($debug) echo 'ERROR: unknown alter operation!<hr />';
								$this->alterError = $errormsg . $lang['alter_unknown_operation'];
								return false;
						}
					}
					$droptempsql = 'DROP TABLE '.$this->quote_id($tmpname);

					$createnewtableSQL = "CREATE TABLE ".$this->quote($table_new)." ".preg_replace("/^\s*CREATE\s+TEMPORARY\s+TABLE\s+'?".str_replace("'","''",preg_quote($tmpname,"/"))."'?\s+(.*)$/is", '$1', $createtesttableSQL, 1);

					$newcolumns = '';
					$oldcolumns = '';
					reset($newcols);
					while(list($key,$val) = each($newcols))
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
							if($debug) echo 'Not recreating the following index: <hr />'.htmlencode($recreate_query['sql']).'<hr />'; 
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
							$recreate_queryIndex = preg_replace($preg_index, '$1'.$this->quote_id(strtr($table_new, array('\\' => '\\\\', '$' => '\$'))).'$3 ', $recreate_query['sql']);
							if($recreate_queryIndex!=$recreate_query['sql'] && $recreate_queryIndex != NULL)
								$alter_transaction .= $recreate_queryIndex.';';
							else
							{
								// the CREATE INDEX regex did not match. this normally should not happen
								if($debug) echo  'ERROR: CREATE INDEX regex did not match!?<hr />';
								// just try to recreate the index originally (will fail most likely)
								$alter_transaction .= $recreate_query['sql'].';';
							}
							break;
							
						case 'trigger':
							// TODO: IMPLEMENT
							$alter_transaction .= $recreate_query['sql'].';';
							break;
						default:
							if($debug) echo 'ERROR: Unknown type '.htmlencode($recreate_query['type']).'<hr />';
							$alter_transaction .= $recreate_query['sql'].';';
					}
				}
			}
			$alter_transaction .= 'COMMIT;';
			if($debug) echo $alter_transaction;
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
		$query = "PRAGMA table_info(".$this->quote_id($table).")";
		$table_info = $this->selectArray($query);
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
		$primary_key = array();
		// check if this table has a rowid
		$getRowID = $this->select('SELECT ROWID FROM '.$this->quote_id($table).' LIMIT 0,1');
		if(isset($getRowID[0]))
			// it has, so we prefer addressing rows by rowid			
			return array('rowid');
		else
		{
			// the table is without rowid, so use the primary key
			$query = "PRAGMA table_info(".$this->quote_id($table).")";
			$table_info = $this->selectArray($query);
			foreach($table_info as $row_id => $row_data)
			{
				if($row_data['pk'])
					$primary_key[] = $row_data['name'];
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
		$value = str_replace('"','""',$value);
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
	
	//import csv
	//returns true on success, error message otherwise
	public function import_csv($filename, $table, $field_terminate, $field_enclosed, $field_escaped, $null, $fields_in_first_row)
	{
		// CSV import implemented by Christopher Kramer - http://www.christosoft.de
		$csv_handle = fopen($filename,'r');
		$csv_insert = "BEGIN;\n";
		$csv_number_of_rows = 0;
		// PHP requires enclosure defined, but has no problem if it was not used
		if($field_enclosed=="") $field_enclosed='"';
		// PHP requires escaper defined
		if($field_escaped=="") $field_escaped='\\';
		while(!feof($csv_handle))
		{
			$csv_data = fgetcsv($csv_handle, 0, $field_terminate, $field_enclosed, $field_escaped); 
			if($csv_data[0] != NULL || count($csv_data)>1)
			{
				$csv_number_of_rows++;
				if($fields_in_first_row && $csv_number_of_rows==1)
				{
					$fields_in_first_row = false;
					continue; 
				}
				$csv_col_number = count($csv_data);
				$csv_insert .= "INSERT INTO ".$this->quote_id($table)." VALUES (";
				foreach($csv_data as $csv_col => $csv_cell)
				{
					if($csv_cell == $null) $csv_insert .= "NULL";
					else
					{
						$csv_insert.= $this->quote($csv_cell);
					}
					if($csv_col == $csv_col_number-2 && $csv_data[$csv_col+1]=='')
					{
						// the CSV row ends with the separator (like old phpliteadmin exported)
						break;
					} 
					if($csv_col < $csv_col_number-1) $csv_insert .= ",";
				}
				$csv_insert .= ");\n";
				
				if($csv_number_of_rows > 5000)
				{
					$csv_insert .= "COMMIT;\nBEGIN;\n";
					$csv_number_of_rows = 0;
				}
			}
		}
		$csv_insert .= "COMMIT;";
		fclose($csv_handle);
		$import = $this->multiQuery($csv_insert);
		if(!$import)
			return $this->getError();
		else
			return true;
	}
	
	//export csv
	public function export_csv($tables, $field_terminate, $field_enclosed, $field_escaped, $null, $crlf, $fields_in_first_row)
	{
		$field_enclosed = $field_enclosed;
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
				$query = "PRAGMA table_info(".$this->quote_id($result[$i]['tbl_name']).")";
				$temp = $this->selectArray($query);
				$cols = array();
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
					echo "\r\n";	
				}
				$query = "SELECT * FROM ".$this->quote_id($result[$i]['tbl_name']);
				$arr = $this->selectArray($query, "assoc");
				for($z=0; $z<sizeof($arr); $z++)
				{
					for($y=0; $y<sizeof($cols); $y++)
					{
						$cell = $arr[$z][$cols[$y]];
						if($crlf)
						{
							$cell = str_replace("\n","", $cell);
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
					if($z<sizeof($arr)-1)
						echo "\r\n";	
				}
				if($i<sizeof($result)-1)
					echo "\r\n";
			}
		}
	}
	
	//export sql
	public function export_sql($tables, $drop, $structure, $data, $transaction, $comments)
	{
		global $lang;
		if($comments)
		{
			echo "----\r\n";
			echo "-- ".PROJECT." ".$lang['db_dump']." (".PROJECT_URL.")\r\n";
			echo "-- ".PROJECT." ".$lang['ver'].": ".VERSION."\r\n";
			echo "-- ".$lang['exported'].": ".date($lang['date_format'])."\r\n";
			echo "-- ".$lang['db_f'].": ".$this->getPath()."\r\n";
			echo "----\r\n";
		}
		$query = "SELECT * FROM sqlite_master WHERE type='table' OR type='index' OR type='view' OR type='trigger' ORDER BY type='trigger', type='index', type='view', type='table'";
		$result = $this->selectArray($query);

		if($transaction)
			echo "BEGIN TRANSACTION;\r\n";

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
						echo "\r\n----\r\n";
						echo "-- ".$lang['drop']." ".$result[$i]['type']." ".$lang['for']." ".$result[$i]['name']."\r\n";
						echo "----\r\n";
					}
					echo "DROP ".strtoupper($result[$i]['type'])." ".$this->quote_id($result[$i]['name']).";\r\n";
				}
				if($structure)
				{
					if($comments)
					{
						echo "\r\n----\r\n";
						if($result[$i]['type']=="table" || $result[$i]['type']=="view")
							echo "-- ".ucfirst($result[$i]['type'])." ".$lang['struct_for']." ".$result[$i]['tbl_name']."\r\n";
						else // index or trigger
							echo "-- ".$lang['struct_for']." ".$result[$i]['type']." ".$result[$i]['name']." ".$lang['on_tbl']." ".$result[$i]['tbl_name']."\r\n";
						echo "----\r\n";
					}
					echo $result[$i]['sql'].";\r\n";
				}
				if($data && $result[$i]['type']=="table")
				{
					$query = "SELECT * FROM ".$this->quote_id($result[$i]['tbl_name']);
					$arr = $this->selectArray($query, "assoc");

					if($comments)
					{
						echo "\r\n----\r\n";
						echo "-- ".$lang['data_dump']." ".$result[$i]['tbl_name'].", ".sprintf($lang['total_rows'], sizeof($arr))."\r\n";
						echo "----\r\n";
					}
					$query = "PRAGMA table_info(".$this->quote_id($result[$i]['tbl_name']).")";
					$temp = $this->selectArray($query);
					$cols = array();
					$cols_quoted = array();
					$vals = array();
					for($z=0; $z<sizeof($temp); $z++)
					{
						$cols[$z] = $temp[$z][1];
						$cols_quoted[$z] = $this->quote_id($temp[$z][1]);
					}
					for($z=0; $z<sizeof($arr); $z++)
					{
						for($y=0; $y<sizeof($cols); $y++)
						{
							if(!isset($vals[$z]))
								$vals[$z] = array();
							if($arr[$z][$cols[$y]] === NULL)
								$vals[$z][$cols[$y]] = 'NULL';
							else
								$vals[$z][$cols[$y]] = $this->quote($arr[$z][$cols[$y]]);
						}
					}
					for($j=0; $j<sizeof($vals); $j++)
						echo "INSERT INTO ".$this->quote_id($result[$i]['tbl_name'])." (".implode(",", $cols_quoted).") VALUES (".implode(",", $vals[$j]).");\r\n";
				}
			}
		}
		if($transaction)
			echo "COMMIT;\r\n";
	}
}
