REQUIREMENTS:

	- a server with PHP >= 5.1.0 installed

	- at least one PHP SQLite library extension installed and enabled: PDO, SQLite3, or SQLiteDatabase
	  See https://code.google.com/p/phpliteadmin/wiki/Installation

INSTALLATION:

  --- NEW as of 1.9.4: You can now configure phpLiteAdmin in an external file. ---
  If you want to do this:
  - rename phpliteadmin.config.sample.php into phpliteadmin.config.php
  - do not change the settings in phpliteadmin.php but in phpliteadmin.config.php
  See https://code.google.com/p/phpliteadmin/wiki/Configuration for details.

	1. Open phpliteadmin.config.php (or phpliteadmin.php) in a text editor.
	
	2. If you want to have a directory scanned for your databases instead of listing them manually, specify the directory as the value of the $directory variable and skip to step 4. 
	
	3. If you want to specify your databases manually, set the value of the $directory variable as false and modify the $databases array to hold the databases you would like to manage.
	
		- The path field is the file path of the database relative to where phpliteadmin.php will be located on the server. For example, if phpliteadmin.php is located at "databases/manager/phpliteadmin.php" and you want to manage "databases/yourdatabase.sqlite", the path value would be "../yourdatabase.sqlite".
		
		- The name field is the human-friendly way of referencing the database within the application. It can be anything you want.

	4. Modify the $password variable to be the password used for gaining access to the phpLiteAdmin tool.
	
	5. If you want to have multiple installations of phpLiteAdmin on the same server, change the $cookie_name variable to be unique for each installation (optional).

	6. Save and upload phpliteadmin.php to your web server.
	
	7. Open a web browser and navigate to the uploaded phpliteadmin.php file. You will be prompted to enter a password. Use the same password you set in step 4.

INSTALLING A THEME:
	
	1. Download the themes package from the project Downloads page.
	
	2. Unzip the file and choose your desired theme.
	
	3. Upload phpliteadmin.css from the theme's directory alongside phpliteadmin.php.
	
	4. Your theme will automatically override the default.
	
GETTING HELP:

	The project's wiki provides information on how to do certain things and is located at http://code.google.com/p/phpliteadmin/w/list. In addition, the project's discussion group is located at http://groups.google.com/group/phpliteadmin.

REPORTING ERRORS AND BUGS:

	If you find any issues while using the tool, please report them at http://code.google.com/p/phpliteadmin/issues/list.