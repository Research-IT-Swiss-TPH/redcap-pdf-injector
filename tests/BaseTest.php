<?php namespace STPH\pdfInjector;

require_once __DIR__ . '/../../../redcap_connect.php';

abstract class BaseTest extends \ExternalModules\ModuleBaseTest {

    protected function callPrivateMethod($name, array $args) {
        $obj =  $this->module;
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs($obj, $args);
    }

}