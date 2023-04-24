<?php namespace STPH\pdfInjector;

// For now, the path to "redcap_connect.php" on your system must be hard coded.
require_once __DIR__ . '/../../../redcap_connect.php';


use \ExternalModules\ExternalModules;
use \Exception;

final class InjectionsTest extends BaseTest {

    public function test_get_and_set_Injections() {

        $testData = [
            3 => [
                "foo" => "bar"
            ]
        ];
        $this->module->setProjectSetting('pdf-injections', $testData);
        $this->callPrivateMethod('setInjections', []);

        $actual = $this->module->getInjections()[3]["foo"];
        $expected = "bar";

        $this->assertSame($expected, $actual);
    }

    public function testsave_Injection() {

        $expected = "New Injection";
        if (!class_exists("Injection")) include_once("classes/Injection.php");
        $injection = new Injection;
        $injection->setValues( $expected, "Added for testing", [], 'filename', $this->rnd, $this->rnd+1);

        $this->callPrivateMethod('saveInjection', [$injection]);

        $injections = $this->module->getProjectSetting('pdf-injections');

        $actual = $injections[$this->rnd]["title"];

        $this->assertSame($expected, $actual);
    }

    public function test_delete_Injection() {
        $expected = "Old Injection";
        if (!class_exists("Injection")) include_once("classes/Injection.php");
        $injection = new Injection;
        $injection->setValues( $expected, "Added for testing", [], 'filename', $this->rnd, $this->rnd+1);

        //  We need to set PORJECT_ID, otherwise deleteInjection:deleteFileByDocId will return false
        define('PROJECT_ID', self::getTestPID());

        $this->callPrivateMethod('saveInjection', [$injection]);
        $this->callPrivateMethod('deleteInjection', [$injection]);

        $actual = $this->module->getProjectSetting('pdf-injections');

        $this->assertEmpty($actual);
    }

}
