<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';

final class handlePostTest extends \ExternalModules\ModuleBaseTest{
 
    public $pid;

    public function __construct(){
        $this->pid = $this->createProject('Test Handle Post','phpunit testing');
    }

    function test(){

        $actual = $this->getProjectId();
        $expected = null;

        $this->assertSame($actual, $expected);

        
    }
    
}