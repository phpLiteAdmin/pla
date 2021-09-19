<?php

namespace phpLiteAdmin;
use phpLiteAdmin\phpLiteAdminOptionsInterface;

class phpLiteAdmin extends phpLiteAdminOptionsInterface {

    public function __construct()
    {
        parent::__construct();
    }

    public function web() {

        $password = $this->getPassword();
        $directory = $this->getDirectory();
        $subdirectories = $this->getSubdirectories();
        $databases = $this->getDatabases();
        $theme = $this->getTheme();
        $language = $this->getLanguage();
        $rowsNum = $this->getRowsnum();
        $charsNum = $this->getCharsNum();
        $maxSavedQueries = $this->getMaxSavedQueries();
        $custom_functions = $this->custom_functions();
        $cookie_name = $this->getCookie_name();
        $debug = $this->getDebug();
        $allowed_extensions = $this->getAllowed_extensions();
        $hexblobs = $this->Hexblobs();

        include_once("./index.php");
        
    }


}