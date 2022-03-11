<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

use \ExternalModules\ExternalModules;
use \Exception;

final class PostHandlerTest extends BaseTest {

    static function setUpBeforeClass(): void {
        $_GET['pid'] = self::getTestPID();    
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

        //To Do: Test Thumbnail Content Generation in separate test. Set it to string for so long.
        $_POST["thumbnail_base64"] = "Foo";
    }

    /**
     * Test: HandlePost_EMPTY_returns_null
     * 
     *  @since 1.3.7
     */
    function testUndefined_request_returns_null() {
        
        //  Call handlePost()
        $actual = $this->callPrivateMethod('handlePost', []);
        $this->assertNull($actual);
    }

    /**
     * 
     * 
     *  @since 1.3.7
     */    
    function testCREATE_throws_for_no_file_set() {
        
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
     *  @since 1.3.7
     */    
    function testCREATE_throws_for_invalid_type() {

        //  Fake Post Variable
        $_POST["mode"] = "CREATE";

        //  Fake File Upload        
        $this->fakeUpload("invalid_file.txt");        

        //  Expect Exception
        $this->expectExceptionMessage("The file type is invalid.");

        //  Call handlePost()
        $this->callPrivateMethod('handlePost', []);
    } 

    /**
     * 
     * 
     *  @since 1.3.7
     */    
    function testCREATE_throws_for_invalid_pdf() {

        //  Fake Post Variable
        $_POST["mode"] = "CREATE";

        //  Fake File Upload        
        $this->fakeUpload("pdfi_blank_unreadable.pdf");
        
        //  Expect Exception
        $this->expectExceptionMessage("The PDF file is invalid.");

        //  Call handlePost()
       $this->callPrivateMethod('handlePost', []);
    }

    /**
     * 
     * 
     *  @since 1.3.7
     */    
    function testCREATE_succeeds_for_valid_pdf() {
    
        //  Generate random string to fake change
        $random = (string) rand();

        //  Fake Post Variables
        $_POST["mode"] = "CREATE";
        $_POST["title"] = $random;
        $_POST["description"] = "Test Description";

        //  Fake File Upload        
        $this->fakeUpload("pdfi_blank_readable.pdf");
        
        //  Call handlePost()
        $actual = $this->callPrivateMethod('handlePost', []);

        //  Check if Injection has been saved into database
        $injections = $this->getProjectSetting("pdf-injections");
        $firstInjection = reset($injections);
        $actual = $firstInjection["title"];
        
        $exptected = $random;
        $this->assertSame($actual, $exptected);
        
    }


    function testUPDATE_throws_for_no_file_set() {
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
     *  @since 1.3.7
     */    
    function testUPDATE_throws_for_invalid_file() {

        //  Fake Post Variable
        $_POST["mode"] = "UPDATE";

        //  Fake File Upload        
        $this->fakeUpload("invalid_file.txt");        

        //  Expect Exception
        $this->expectExceptionMessage("The file type is invalid.");

        //  Call handlePost()
        $this->callPrivateMethod('handlePost', []);
    }
    
    /**
     * 
     * 
     *  @since 1.3.7
     */    
    function testUPDATE_throws_for_invalid_pdf() {

        //  Fake Post Variable
        $_POST["mode"] = "UPDATE";

        //  Fake File Upload        
        $this->fakeUpload("pdfi_blank_unreadable.pdf");
        
        //  Expect Exception
        $this->expectExceptionMessage("The PDF file is invalid.");

        //  Call handlePost()
        $this->callPrivateMethod('handlePost', []);
    }

  
    /**
     * 
     * 
     *  @since 1.3.7
     */    
    function testUPDATE_succeeds_without_file_change() {

        //  Generate random string to fake change
        $random = (string) rand();

        //  Get already saved Injection (from CREATE tests)
        $oldInjection = reset($this->getProjectSetting("pdf-injections"));

        //  Fake Post Variables
        $_POST["mode"] = "UPDATE";
        $_POST["hasFileChanged"] = false;

        $_POST["document_id"] = $oldInjection["document_id"];
        $_POST["thumbnail_id"] = $oldInjection["thumbnail_id"];
        $_POST["description"] = $oldInjection["description"];

        $_POST["title"] = $random;    //  this is the change
        

        //  Fake File Upload        
        $this->fakeUpload("pdfi_blank_readable.pdf");        

        //  Call handlePost
        $this->callPrivateMethod('handlePost', []);

        //  Get new Injection
        $newInjection = reset($this->getProjectSetting("pdf-injections"));
        $actual = $newInjection["title"];
        $expected = $random;

        $this->assertSame($expected, $actual);
    }

    /**
     * 
     * 
     *  @since 1.3.7
     */  
    function testUPDATE_succeeds_with_file_change() {

        //  Generate random string to fake change
        $random = (string) rand();

        //  Get already saved Injection (from CREATE tests)
        $oldInjection = reset($this->getProjectSetting("pdf-injections"));

        //  Fake Post Variables
        $_POST["mode"] = "UPDATE";
        $_POST["hasFileChanged"] = true;

        $_POST["document_id"] = $oldInjection["document_id"];
        $_POST["thumbnail_id"] = $oldInjection["thumbnail_id"];        
        $_POST["description"] = $oldInjection["description"];

        $_POST["title"] = $random;  //  this is a change (but not relevant within this test)

        //  Fake File Upload        
        $this->fakeUpload("pdfi_blank_readable.pdf");        

        //  Call handlePost
        $this->callPrivateMethod('handlePost', []);

        //  Get new Injection
        $newInjection = reset($this->getProjectSetting("pdf-injections"));
        $new = $newInjection["document_id"];
        $old = $oldInjection["document_id"];

        $this->assertNotSame($new, $old);
    }

    function testUPDATE_returns_null_for_no_change() {
        //  Get already saved Injection (from CREATE tests)
        $oldInjection = reset($this->getProjectSetting("pdf-injections"));

        //  Fake Post Variables
        $_POST["mode"] = "UPDATE";
        $_POST["document_id"] = $oldInjection["document_id"];
        $_POST["thumbnail_id"] = $oldInjection["thumbnail_id"];
        $_POST["hasFileChanged"] = false;

        $_POST["title"] = $oldInjection["title"];    //  this is the change
        $_POST["description"] = $oldInjection["description"];

        //  Fake File Upload        
        $this->fakeUpload("pdfi_blank_readable.pdf");            

        //  Call handlePost
        $actual = $this->callPrivateMethod('handlePost', []);

        $this->assertNull($actual);
    }

    function testDELETE_throws_for_no_document_id_set() {
        $_POST["mode"] = "DELETE";
        $_POST["document_id"] = "";

        $this->expectExceptionMessage("The document id not set.");
        $this->callPrivateMethod('handlePost', []);

    }

    function testDELETE_succeeds() {
        
        $oldInjection = reset($this->getProjectSetting("pdf-injections"));
        
        $_POST["mode"] = "DELETE";
        $_POST["document_id"] = $oldInjection["document_id"];

        $this->callPrivateMethod('handlePost', []);
        $injections = $this->getProjectSetting("pdf-injections");

        $this->assertEmpty($injections);
    }

}