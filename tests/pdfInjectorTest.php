<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

use \ExternalModules\ExternalModules;
use \Exception;
use \HttpClient;
use \GuzzleHttp\Psr7;

final class pdfInjectorTest extends BaseTest {

    static string $pid1;
    static string $pid2;
    static array $formNames;

    const DEV_MODE = FALSE;

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

        //  Create MetaData for first test project
        self::createTestMetaData(self::$pid1);


    }

    static function tearDownAfterClass():void{
        if(!DEV_MODE) {
            self::cleanupTestProjects();    
        }
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
        //\Project::setDefaults($pid);

        // Give this new project an arm and an event (default)
        \Project::insertDefaultArmAndEvent($pid);

        return $pid;

    }

    static function createTestMetaData($pid) {

        try {
            $sql = 'INSERT into redcap_metadata (project_id, field_name, form_name, form_menu_description, field_order, element_type, element_label,
            element_enum, element_validation_type, element_validation_checktype, element_preceding_header)
VALUES  (99, "fancy_field_name", "Form 1", "Form Menu 1", "1","1","","","","","");
';
        } catch(\Exception $e) {
            throw $e;
        }        
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
        $this->assertNull($actual);
    }

    /**
     * 
     * 
     *  @since 1.4.0
     */
    function testHandlePost_EMPTY_returns_null() {
        
        //  Call handlePost()
        $actual = $this->callPrivateMethod('handlePost', []);
        $this->assertNull($actual);
    }

    /**
     * 
     * 
     *  @since 1.4.0
     */    
    function testHandlePost_CREATE_throws_for_no_file_set() {
        
        //  Fake Post Variable
        $_POST["mode"] = "CREATE";

        //  Reset $_FILES 
        $_FILES = [];        
        
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
    
        //  Generate random string to fake change
        $random = (string) rand();

        //  Fake Post Variables
        $_POST["mode"] = "CREATE";
        $_POST["title"] = $random;
        $_POST["description"] = "Test Description";

        //  Fake File Upload        
        $this->fakeUpload("pdftk_PDFI_testing_blank.pdf");
        
        //  Call handlePost()
        $actual = $this->callPrivateMethod('handlePost', []);

        //  Check if Injection has been saved into database
        $injections = self::getProjectSetting("pdf-injections");
        $firstInjection = reset($injections);
        $actual = $firstInjection["title"];
        
        $exptected = $random;
        $this->assertSame($actual, $exptected);
        
    }

    function testHandlePost_UPDATE_throws_for_no_file_set() {
        //  Fake Post Variable
        $_POST["mode"] = "UPDATE";

        //  Reset $_FILES 
        $_FILES = [];

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
    function testHandlePost_UPDATE_throws_for_invalid_file() {

        //  Fake Post Variable
        $_POST["mode"] = "UPDATE";

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
    function testHandlePost_UPDATE_throws_for_invalid_pdf() {

        //  Fake Post Variable
        $_POST["mode"] = "UPDATE";

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
    function testHandlePost_UPDATE_succeeds_without_file_change() {

        //  Generate random string to fake change
        $random = (string) rand();

        //  Get already saved Injection (from CREATE tests)
        $oldInjection = reset(self::getProjectSetting("pdf-injections"));

        //  Fake Post Variables
        $_POST["mode"] = "UPDATE";
        $_POST["hasFileChanged"] = false;

        $_POST["document_id"] = $oldInjection["document_id"];
        $_POST["thumbnail_id"] = $oldInjection["thumbnail_id"];
        $_POST["description"] = $oldInjection["description"];

        $_POST["title"] = $random;    //  this is the change
        

        //  Fake File Upload        
        $this->fakeUpload("pdftk_PDFI_testing_blank.pdf");        

        //  Call handlePost
        $this->callPrivateMethod('handlePost', []);

        //  Get new Injection
        $newInjection = reset(self::getProjectSetting("pdf-injections"));
        $actual = $newInjection["title"];
        $expected = $random;

        $this->assertSame($expected, $actual);

    }

    function testHandlePost_UPDATE_succeeds_with_file_change() {

        //  Generate random string to fake change
        $random = (string) rand();

        //  Get already saved Injection (from CREATE tests)
        $oldInjection = reset(self::getProjectSetting("pdf-injections"));

        //  Fake Post Variables
        $_POST["mode"] = "UPDATE";
        $_POST["hasFileChanged"] = true;

        $_POST["document_id"] = $oldInjection["document_id"];
        $_POST["thumbnail_id"] = $oldInjection["thumbnail_id"];        
        $_POST["description"] = $oldInjection["description"];

        $_POST["title"] = $random;  //  this is a change (but not relevant within this test)

        //  Fake File Upload        
        $this->fakeUpload("pdftk_PDFI_testing_blank.pdf");        

        //  Call handlePost
        $this->callPrivateMethod('handlePost', []);

        //  Get new Injection
        $newInjection = reset(self::getProjectSetting("pdf-injections"));
        $new = $newInjection["document_id"];
        $old = $oldInjection["document_id"];

        $this->assertNotSame($new, $old);
    }

    function testHandlePost_UPDATE_returns_null_for_no_change() {
        //  Get already saved Injection (from CREATE tests)
        $oldInjection = reset(self::getProjectSetting("pdf-injections"));

        //  Fake Post Variables
        $_POST["mode"] = "UPDATE";
        $_POST["document_id"] = $oldInjection["document_id"];
        $_POST["thumbnail_id"] = $oldInjection["thumbnail_id"];
        $_POST["hasFileChanged"] = false;

        $_POST["title"] = $oldInjection["title"];    //  this is the change
        $_POST["description"] = $oldInjection["description"];

        //  Fake File Upload        
        $this->fakeUpload("pdftk_PDFI_testing_blank.pdf");           

        //  Call handlePost
        $actual = $this->callPrivateMethod('handlePost', []);

        $this->assertNull($actual);
    }

    function testHandlePost_DELETE_throws_for_no_document_id_set() {
        $_POST["mode"] = "DELETE";
        $_POST["document_id"] = "";

        $this->expectExceptionMessage("The document id not set.");
        $this->callPrivateMethod('handlePost', []);

    }

    function testHandlePost_DELETE_succeeds() {
        
        $oldInjection = reset(self::getProjectSetting("pdf-injections"));
        
        $_POST["mode"] = "DELETE";
        $_POST["document_id"] = $oldInjection["document_id"];        

        $this->callPrivateMethod('handlePost', []);
        $injections = self::getProjectSetting("pdf-injections");

        $this->assertEmpty($injections);
    }

    /**
     * API Testing
     * scanFile()
     */

    function testScanFile_throws_for_no_file_set() {

        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fileScan";

        $this->expectExceptionMessage("Unknown Error");
        $client::request('post', $requestURL);        
        
    }

    function testScanFile_throws_for_invalid_file() {

        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fileScan";

        $this->expectException(Exception);
        $client::request('post', $requestURL, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen('files/invalid_file.txt', 'r')
                ]
               
            ]
        ]);
    }

    function testScanFile_throws_for_invalid_pdf(){
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fileScan";

        $this->expectException(Exception);
        $client::request('post', $requestURL, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen('files/PDFI_testing_blank.pdf', 'r')
                ]
               
            ]
        ]);
    }

    function testScanFile_succeeds(){
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fileScan";

        $response = $client::request('post', $requestURL, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => Psr7\Utils::tryFopen('files/pdftk_PDFI_testing_blank.pdf', 'r')
                ]
               
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        //  To Do: Improve Test by asserting JSON object
    }
    
    /**
     * API Testing
     * scanField()
     */
    function testScanField_fails_for_no_fieldName_set() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";
        
        $this->expectExceptionMessage("Field is invalid");
        $client::request('post', $requestURL, []);

    }

    function testScanField_fails_for_invalid_fieldName_set() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";
        
        $this->expectExceptionMessage("Field is invalid");
        $client::request('post', $requestURL, [
            "fieldName" => "non-existing-field"
        ]);

    }

    function testScanField_succeeds() {
        $client = new HttpClient;
        $requestURL = $this->getUrl("requestHandler.php") . "&action=fieldScan";
        
        $response = $client::request('post', $requestURL, [
            "fieldName" => "record_id"
        ]);

        $this->assertSame(200, $response->getStatusCode());

    }    
    
} 