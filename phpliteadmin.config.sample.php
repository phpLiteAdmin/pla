<?php
//
// This is sample configuration file
//
// You can configure phpliteadmin in one of 2 ways:
// 1. Rename phpliteadmin.config.sample.php to phpliteadmin.config.php and change parameters in there.
//    You can set only your custom settings in phpliteadmin.config.php. All other settings will be set to defaults.
// 2. Change parameters directly in main phpliteadmin.php file
//
// Please see https://bitbucket.org/phpliteadmin/public/wiki/Configuration for more details

/**
 * This function checks for an environment variable with uppercase name of
 * configuration item prefixed by "PHPLITEADMIN_".
 * E.g. PHPLITEADMIN_PASSWORD
 *
 * @param string $name
 * @param string $default
 * @return string
 */
function getEnvironmentValue(string $name, string $default = ''): string
{
    $value = getenv('PHPLITEADMIN_' . strtoupper($name));
    if (false === $value) {
        return $default;
    } else {
        return $value;
    }
}

//password to gain access
$password = getEnvironmentValue('password','admin');

//directory relative to this file to search for databases (if empty, manually list databases in the $databases variable)
$directory = getEnvironmentValue('directory', '.');

//whether or not to scan the subdirectories of the above directory infinitely deep
$subdirectories = (getEnvironmentValue('subdirectories', 'false') === 'true');

//if the above $directory variable is set to false, you must specify the databases manually in an array as the next variable
//if any of the databases do not exist as they are referenced by their path, they will be created automatically
if (empty($directory)) {
    $databases = json_decode(
        getEnvironmentValue('databases', '[{"path":"database1.sqlite","name":"Database 1"},{"path":"database2.sqlite","name":"Database 2"}]'),
        true
    );
} else {
    $databases = [];
}


/* ---- Interface settings ---- */

// Theme! If you want to change theme, save the CSS file in same folder of phpliteadmin or in folder "themes"
$theme = getEnvironmentValue('theme', 'Default') . '/phpliteadmin.css';

// the default language! If you want to change it, save the language file in same folder of phpliteadmin or in folder "languages"
// More about localizations (downloads, how to translate etc.): https://bitbucket.org/phpliteadmin/public/wiki/Localization
$language = getEnvironmentValue('language', 'en');

// set default number of rows. You need to relog after changing the number
$rowsNum = (int)getEnvironmentValue('rowsNum', '30');

// reduce string characters by a number bigger than 10
$charsNum = (int)getEnvironmentValue('charsNum', '300');

// maximum number of SQL queries to save in the history
$maxSavedQueries = (int)getEnvironmentValue('maxSavedQueries', '10');

/* ---- Custom functions ---- */

//a list of custom functions that can be applied to columns in the databases
//make sure to define every function below if it is not a core PHP function
$custom_functions = json_decode(
    getEnvironmentValue('custom_functions', '["md5","sha1","strtotime"]'),
    true
);
// define your custom functions here
/*
function leet_text($value)
{
  return strtr($value, 'eaAsSOl', '344zZ01');
}
*/


/* ---- Advanced options ---- */

//changing the following variable allows multiple phpLiteAdmin installs to work under the same domain.
$cookie_name = getEnvironmentValue('cookie_name', 'pla3412');

//whether or not to put the app in debug mode where errors are outputted
$debug = (getEnvironmentValue('debug', 'false') === 'true');

// the user is allowed to create databases with only these extensions
$allowed_extensions = json_decode(
    getEnvironmentValue('allowed_extensions', '["db", "db3", "sqlite", "sqlite3"]'),
    true
);

// BLOBs are displayed and edited as hex string
$hexblobs = (getEnvironmentValue('hexblobs', 'false') === 'true');;
