<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

use \ExternalModules\ExternalModules;
use \Exception;

final class pdfInjectorTestPostHandler extends BaseTest {

    public function __construct() {        
        parent::__construct();        
    }

    //  Delete all temporary files after each tests
    public function tearDown():void{
        $dirname = __DIR__ . "/tmp";
        array_map('unlink', glob("$dirname/*.*"));
        rmdir($dirname);
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

    /**
     * Test: HandlePost_EMPTY_returns_null
     * 
     *  @since 1.4.0
     */
    function testHandlePost_EMPTY_returns_null() {
        
        //  Call handlePost()
        $actual = $this->callPrivateMethod('handlePost', []);
        $this->assertNull($actual);
    }

}