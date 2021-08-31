<?php namespace STPH\pdfInjector;

require_once __DIR__ . '/../../../../redcap_connect.php';

// Using terminal code: https://wiki.bash-hackers.org/scripting/terminalcodes
//  \033 is escape string
echo "\n\n\033[41m\033[37m Cleaning up.. \033[0m\n\n";

//  Delete test projects from redcap_projects
try {
    $sql = 'DELETE FROM redcap_projects WHERE `app_title` LIKE "External Module Unit Test Project%" ';
    db_query($sql);    
    if(db_affected_rows() > 0) {
        echo "Test Projects from `redcap_projects` have been deleted.\n";
    } else {
        echo "No Tests Projects to delete.\n";
    } 

} catch (\Exception $e){
    echo $e::getMessage();
}

//  Delete test pids from redcap_config
try {
    $sql = 'DELETE FROM redcap_config WHERE `field_name` = "external_modules_test_pids"';
    db_query($sql);
    if(db_affected_rows() > 0) {
        echo "External modules test pids from `redcap_config` have been deleted.\n";
    } else {
        echo "No External modules test pids to delete.\n";
    } 
} catch(\Exception $e) {
    echo $e::getMessage();
}

//  Delete project data
