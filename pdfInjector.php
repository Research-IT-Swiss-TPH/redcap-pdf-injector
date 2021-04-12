<?php

// Set the namespace defined in your config file
namespace STPH\pdfInjector;

require 'vendor/autoload.php';


// Declare your module class, which must extend AbstractExternalModule 
class pdfInjector extends \ExternalModules\AbstractExternalModule {

    private $moduleName = "PDF Injector";
    public $fieldData;

   /**
    * Constructs the class
    *
    */
    public function __construct()
    {        
        parent::__construct();
       // Other code to run when object is instantiated
    }

   /**
    * Hooks PDF Injector module to redcap_every_page_top
    *
    */
    public function redcap_every_page_top($project_id = null) {
        $this->renderModule();
    }

   /**
    * Renders the module
    *
    */
    private function renderModule() {
        
        $this->includeJavascript();                
        $this->includeCSS();
        
        $this->readPDF();
        //dump($this->fieldData);

    }

    private function readPDF() {
        if (!class_exists("FPDMH")) include_once("classes/FPDMH.php");

        $filePath = __DIR__ . '/test.pdf';
        $pdf = new FPDMH($filePath);

        $this->fieldData = $pdf->getFieldData();
    }

    
   /**
    * Include JavaScript files
    *
    */
    private function includeJavascript() {
        ?>
        <script src="<?php print $this->getUrl('js/main.js'); ?>"></script>
        <script> 
            $(function() {
                $(document).ready(function(){
                    STPH_pdfInjector.init();
                })
            });
        </script>
        <?php
    }
    

    
   /**
    * Include Style files
    *
    */
    private function includeCSS() {
        ?>
        <link rel="stylesheet" href="<?= $this->getUrl('style.css')?>">
        <?php
    }
    
}