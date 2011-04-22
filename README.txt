INSTALLATION:

   1. Open phpliteadmin.php in a text editor.
	
   2. Modify the $databases array to hold the databases you would like to manage.
	
         - The path field is the file path of the database relative to where phpliteadmin.php will be located on the server. For example, if phpliteadmin.php is located at "databases/manager/phpliteadmin.php" and you want to manage "databases/yourdatabase.sqlite", the path value would be "../yourdatabase.sqlite".
		
         - The name field is the human-friendly way of referencing the database within the application. It can be anything you want.

   3. Modify the $password variable to be the password used for gaining access to the phpLiteAdmin tool.

   4. Save and upload phpliteadmin.php to your web server. Make sure you follow the same file path conventions you assumed in step 2 so that the database files are correctly referenced.
	
   5. Open a web browser and navigate to the uploaded phpliteadmin.php file. You will be prompted to enter a password. Use the same password you set in step 3.


REQUIREMENTS:

   - a server with PHP installed
	
	- at least one PHP SQLite library extension installed and enabled: PDO, SQLite3, or SQLiteDatabase


GETTING HELP:

   The project's wiki provides information on how to do certain things and is located at http://code.google.com/p/phpliteadmin/w/list. In addition, the project's discussion group is located at http://groups.google.com/group/phpliteadmin.


REPORTING ERRORS AND BUGS:

   If you find any issues while using the tool, please report them at http://code.google.com/p/phpliteadmin/issues/list.