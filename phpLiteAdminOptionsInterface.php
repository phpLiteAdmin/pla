<?php

namespace phpLiteAdmin;

abstract class phpLiteAdminOptionsInterface {

    private $password;
    private $directory;
    private $subdirectories;
    private $databases;
    private $theme;
    private $language;
    private $rowsNum;
    private $charsNum;
    private $maxSavedQueries;
    private $custom_functions;
    private $cookie_name;
    private $debug;
    private $allowed_extensions;
    private $hexblobs;

    public function __construct()
    {
        $this->password = 'admin';

        $this->directory = '.';

        $this->subdirectories = false;
        $this->databases = array(
            array(
                'path'=> 'database1.sqlite',
                'name'=> 'Database 1'
            ),
            array(
                'path'=> 'database2.sqlite',
                'name'=> 'Database 2'
            ),
        );

        $this->theme = 'phpliteadmin.css';

        $this->language = 'en';

        $this->rowsNum = 30;

        $this->charsNum = 300;

        $this->maxSavedQueries = 10;

        $this->custom_functions = array(
            'md5', 'sha1', 'strtotime',
            // add the names of your custom functions to this array
            /* 'leet_text', */
        );

        $this->cookie_name = 'pla3412';

        $this->debug = false;

        $this->allowed_extensions = array('db','db3','sqlite','sqlite3');

        $this->hexblobs = false;

    }

    public function __get($name)
    {
        $property = str_replace('get', '', $name);
        $prefix = lcfirst($property);
        if ( property_exists($this, $prefix) ) {
            return $this->{$prefix};
        }
        else {
            $trace = debug_backtrace();
            trigger_error(
                'Propiedad indefinida mediante __get(): ' . $name .
                ' en ' . $trace[0]['file'] .
                ' en la línea ' . $trace[0]['line'],
                E_USER_NOTICE);
            return null;
        }
    }

    public function __set($name, $value)
    {
        $property = str_replace('set', '', $name);
        $prefix = lcfirst($property);
        if ( property_exists($this, $prefix) ) {
            $this->{$prefix} = $value;
            return true;
        }
        else {
            $trace = debug_backtrace();
            trigger_error(
                'Propiedad indefinida mediante __get(): ' . $name .
                ' en ' . $trace[0]['file'] .
                ' en la línea ' . $trace[0]['line'],
                E_USER_NOTICE);
            return null;
        }
    }
    
}