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

    /**
     *  Fake upload for different files
     * 
     */
    private function fakeUpload($file) {

        //  Reset $_FILES
        $_FILES = [];

        //  Define file path
        $path = __DIR__ . "/files/" . $file;

        //  Check if test file exists
        if(!file_exists($path)) {
            throw new Exception( "Testing file '" . $file . "' could not be found in 'tests/files' directory. Please ensure that this file exists.");
        }

        //  Create temporary directory if not exists
        if (!file_exists(__DIR__ . "/tmp")) {
            mkdir(__DIR__ . "/tmp/", 0777, true);
        }        

        $tmp_path = __DIR__ . "/tmp/" . $file;
        if(!copy($path, $tmp_path)) {
            throw new Exception( "Temporary file could not be created");
        }

        //  Change permission for temporary files
        chmod( $tmp_path, 0777 ); 

        //  Fake File Upload
        $_FILES = array(
            'file'    =>  array(
                'name'      =>  pathinfo($path)["basename"],
                'tmp_name'  =>  $tmp_path,
                'type'      =>  mime_content_type($path),
                'size'      =>  filesize($path),
                'error'     =>  0
            )
        );

        //  Fake Post Variable
        //  TODO: seems to be wrong
        $_POST["thumbnail_base64"] = base64_encode(file_get_contents($tmp_path));
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

    /**
     * 
     * 
     *  @since 1.4.0
     */
    function testHandlePost_EMPTY_returns_null() {
        
        //  Call handlePost()
        $actual = $this->callPrivateMethod('handlePost', []);
        $this->assertSame($actual, null);
    }

    /**
     * 
     * 
     *  @since 1.4.0
     */    
    function testHandlePost_CREATE_throws_for_no_file_set() {
        
        //  Fake Post Variable
        $_POST["mode"] = "CREATE";
        
        //  Expect Exception
        $this->expectExceptionMessage("The file upload is not set.");

        //  Call handlePost()
        $this->callPrivateMethod('handlePost', []);
    }
    
    /**
     * 
     * 
     *  @since 1.4.0
     */    
    function testHandlePost_CREATE_throws_for_invalid_file() {

        //  Fake Post Variable
        $_POST["mode"] = "CREATE";

        //  Fake File Upload        
        $this->fakeUpload("invalid_file.txt");        

        //  Expect Exception
        $this->expectExceptionMessage("The file is invalid.");

        //  Call handlePost()
        $this->callPrivateMethod('handlePost', []);
    }

    /**
     * 
     * 
     *  @since 1.4.0
     */    
    function testHandlePost_CREATE_throws_for_invalid_pdf() {

        //  Fake Post Variable
        $_POST["mode"] = "CREATE";

        //  Fake File Upload        
        $this->fakeUpload("PDFI_testing_blank.pdf");
        
        //  Expect Exception
        $this->expectExceptionMessage("The PDF is invalid.");

        //  Call handlePost()
       $this->callPrivateMethod('handlePost', []);
    }

    /**
     * 
     * 
     *  @since 1.4.0
     */    
    function testHandlePost_CREATE_succeeds() {

        //  Fake Post Variables
        $_POST["mode"] = "CREATE";
        $_POST["title"] = "Test Title";
        $_POST["description"] = "Test Description";

        //  Fake File Upload        
        $this->fakeUpload("pdftk_PDFI_testing_blank.pdf");
        
        //  Call handlePost()
        $actual = $this->callPrivateMethod('handlePost', []);

        //  Check if Injection has been saved into database
        $injections = self::getProjectSetting("pdf-injections");

        dump($injections);
        
    }    
    
}