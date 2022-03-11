<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';


use \ExternalModules\ExternalModules;
use \Exception;

final class pdfInjectorTest extends BaseTest {

    public function __construct() {        
        parent::__construct();        
    }

    /**
     *  Test: No module output when no userid is set (logout)
     *  
     *  @since 1.3.7
     */
    function testNo_module_output_for_no_user_set(){
        
        //fwrite(STDERR, print_r("PROJECT_ID: " . PROJECT_ID));
        //  Call redcap_every_page_top()
        $actual = $this->redcap_every_page_top();
        $this->assertSame($actual, null);
    }

    
}