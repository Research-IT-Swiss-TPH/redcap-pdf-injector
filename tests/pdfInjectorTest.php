<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';


use \ExternalModules\ExternalModules;
use \Exception;

final class pdfInjectorTest extends BaseTest {

    static string $pid1;
    static string $pid2;

    public function __construct() {        
        parent::__construct();        
    }

    static function setUpBeforeClass():void{

        if(!self::hasTestProjects()) {

            self::setupTestProjects();

        } else {

            $testPIDs = ExternalModules::getTestPIDs();
            self::$pid1 = $testPIDs[0];
            self::$pid2 = $testPIDs[1];    
        }


        //  Fake Project ID (using first test project)
        define(PROJECT_ID, ExternalModules::getTestPIDs()[0]);

    }

    static function tearDownAfterClass():void{
        //self::cleanupTestProjects();    
    }

    public function tearDown():void{
        $dirname = __DIR__ . "/tmp";
        array_map('unlink', glob("$dirname/*.*"));
        rmdir($dirname);
    }

    private static function hasTestProjects(){

        $sql = "SELECT *  FROM redcap_config WHERE field_name = 'external_modules_test_pids'";
        $result  = ExternalModules::query($sql, []);

        if($result->num_rows > 0) {
            return true;
        } else {
            return false;
        }

    }

    static function setupTestProjects() {

        self::$pid1 = self::createTestProject('External Module Unit Test Project 1');
        self::$pid2 = self::createTestProject('External Module Unit Test Project 2');
        
        $value = self::$pid1.",".self::$pid2;

        $sql = "insert into redcap_config values ('external_modules_test_pids', ?)";
        
        $result = ExternalModules::query($sql, [$value]);
    }

    static function createTestProject($title){

        $title = \Project::cleanTitle($title);
        $new_app_name = \Project::getValidProjectName($title);

        $purpose = 0;
        $project_note = 'Unit Testing';

        $ui_id = NULL;
        $auto_inc_set = 1;
        $GLOBALS['__SALT__'] = substr(sha1(rand()), 0, 10);

        ExternalModules::query("insert into redcap_projects (project_name, purpose, app_title, creation_time, created_by, auto_inc_set, project_note,auth_meth,__SALT__) values(?,?,?,?,?,?,?,?,?)",
            [$new_app_name,$purpose,$title,NOW,$ui_id,$auto_inc_set,trim($project_note),'none',$GLOBALS['__SALT__']]);            

        // Get this new project's project_id
        $pid = db_insert_id();

        // Insert project defaults into redcap_projects
        \Project::setDefaults($pid);

        return $pid;

    }

    static function cleanupTestProjects() {
        ExternalModules::query(
            "DELETE FROM `redcap_projects` WHERE `project_id`= ? OR `project_id`=?", 
            [
                self::$pid1,
                self::$pid2
            ]
        );

        ExternalModules::query(
            "DELETE FROM `redcap_config` WHERE  `field_name`='external_modules_test_pids'", []
        );
    }    

    function getFirstTestPID(){
        return ExternalModules::getTestPIDs()[0];
    }


    /*
     * Tests
     */

    
    /**
     *  Test Every Page Top: No module output when no userid is set (logout)
     *  
     *  @since 1.4.0
     */
    function testEveryPageTop_returns_null_for_no_user_set(){
        
        //  Call redcap_every_page_top()
        $actual = $this->redcap_every_page_top();
        $this->assertSame($actual, null);
    }


}