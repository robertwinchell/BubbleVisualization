<?php
session_start();

/**
 * include the main config file which contains the database connection details
 */
/*
 * Class: dbPDO
 * core database class to setup the connection to the database
 * 
 * extends:
 * PDO (base PHP daabase abstraction layer)
 */
class PDO_DB extends PDO {
    public $role;
  
    /*
     * Constructor: initialise the database
     * 
     * Parameters:
     * password - password
     * user - user name
     * dbname - database name
     * server - server name (localhost or hosted version)
     */
    public function __construct() {
        
        /*
         * 
         * YOU MUST change th e values below to the correct connection
         * variables for your database eg $pword = '12345';
         * 
         */
       
        
            $pword = '[your db password]';
            $user = '[your db user]';
            $dbname = '[your db name]';
            $hostname = "[you db host name]";
      
        error_reporting(0);
        parent::__construct("mysql:host=$hostname;dbname=$dbname", $user, $pword);
        error_reporting(-1);

        if (mysqli_connect_error()) {
            die('Connect Error (' . mysqli_connect_errno() . ') '
                    . mysqli_connect_error());
        }

    }

  
}
?>