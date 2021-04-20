<?php

// Set the namespace defined in your config file
namespace STPH\pdfInjector;

require 'vendor/autoload.php';

// Declare your module class, which must extend AbstractExternalModule 
class pdfInjector extends \ExternalModules\AbstractExternalModule {

    private $moduleName = "PDF Injector";
    public $fieldData;

    public $injections;

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

        //  Include Javascript and Styles on module page
        if(PAGE == "ExternalModules/index.php" && $_GET["prefix"] == "pdf_injector") {
            $this->initModule();
            $this->includePageJavascript();
            $this->includePageCSS();
        }
    }

   /**
    * Initializes the module from the module page
    *
    */
    public function initModule() {
        $this->injections = $this->getProjectSetting("pdf-injections");
        $this->handlePost();

    }

    private function handlePost() {


        if($_POST) {
            //  Check if a new injection or update of given one
            //  if update: only change if diff; if new file than delete this and create new
            //  add date updated
            
            //  if new:
            //  New Injection
            if($_FILES)  {

                //  Upload File to REDCap
                $docId = \Files::uploadFile($_FILES['file']);
                
                //  If file successfully Uploaded create new Injection entry in database
                if($docId != 0) {

                    //  Prepare new injection
                    $newInjection = [
                        "title" => $_POST["title"],
                        "fileName" => $_FILES['file']['name'],
                        "description" => $_POST["description"],
                        "doc_id" => $docId,
                        "thumb64" => $_POST["thumbnail"],
                        "created" => date("Y-m-d"),
                        "updated" => NULL,
                        "fields" => $_POST["fields"]
                    ];

                    //  Insert new injectio to injections array
                    $injections = $this->injections;
                    $injections[$docId] = $newInjection;

                    //  Save into module data base
                    $this->setProjectSetting("pdf-injections", $injections);

                } else {
                    print "Error";
                }
            }

        }   



    }

   /**
    * Add new injection to list of injections
    *
    */
    public function setLastInjectionID(Int $id) {        
        $this->setProjectSetting("last-injection-id", $id);
    }

    public function scanFile(){

        if(isset($_FILES['file']['name'])){

            $filename = $_FILES['file']['name'];
            $ext =  strtolower(pathinfo($filename,PATHINFO_EXTENSION));
            $tmp_file = $_FILES['file']['tmp_name'];

            //  Process PDF with FPDM - Helper Class (FPDMH)
            if (!class_exists("FPDMH")) include_once("classes/FPDMH.php");
            $pdf = new FPDMH($tmp_file);
            $fieldData = $pdf->getFieldData();

            if($pdf->hasError) {
            //  Check for errors
                header("HTTP/1.1 400 Bad Request");
                header('Content-Type: application/json; charset=UTF-8');                
                die(json_encode(array('message' => $pdf->errorMessage)));
                
            } else {
            //  Return as json response
                $data = file_get_contents( $tmp_file );
                $response = array('file' => $filename, 'fieldData' => $fieldData, 'pdf64' => base64_encode($data));
                header('Content-Type: application/json; charset=UTF-8');                
                echo json_encode($response);
                exit();
            }

         }
         else {
                header("HTTP/1.1 400 Bad Request");
                die(json_encode(array('message' => "Unknown Error")));
         }
    }
    
    public function checkField($fieldname) {

        $pid = PROJECT_ID;

        $sql = 'SELECT * FROM redcap_metadata WHERE project_id = ? AND field_name = ?';

        $result = $this->query($sql, [$pid, $fieldname]);

        if($result->num_rows == 1) {
            header('Content-Type: application/json; charset=UTF-8');                
            echo json_encode(array("fieldName" => $fieldname));
        } else {
            header("HTTP/1.1 400 Bad Request");
            header('Content-Type: application/json; charset=UTF-8');
            die();

        }
             
    }


    public function base64ToImage($base64_string, $output_file) {
        $file = fopen($output_file, "wb");
    
        $data = explode(',', $base64_string);
    
        fwrite($file, base64_decode($data[1]));
        fclose($file);
    
        return $output_file;
    }

    public function base64FromId($doc_id) {

        $path = EDOC_PATH . \Files::getEdocName( $doc_id, true );
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);

        return 'data:image/' . $type . ';base64,' . base64_encode($data);    
    }

   /**
    * Include Page JavaScript files
    *
    */    
    public function includePageJavascript() {

            $debug = $this->getProjectSetting("javascript-debug") == true;
        
            // Prepare parameter array to be passed to Javascript
            $js_params = array (
                "debug" => $debug,
                "injections" => $this->injections
            );

        ?>
        <script src="<?php print $this->getUrl('js/pdfjs/pdf.js'); ?>"></script>
        <script src="<?php print $this->getUrl('js/Injections.js'); ?>"></script>
        <script>
            STPH_pdfInjector.params = <?= json_encode($js_params) ?>;
            STPH_pdfInjector.requestHandlerUrl = "<?= $this->getUrl("requestHandler.php") ?>";
            STPH_pdfInjector.templateURL = "<?= $this->getUrl("template/field_variable_list.php") ?>";
            $(function() {
                $(document).ready(function(){
                    STPH_pdfInjector.init();
                })
            });
        </script>
        <?php
    }

   /**
    * Include Page Style files
    *
    */
    private function includePageCSS() {
        ?>
        <link rel="stylesheet" href="<?= $this->getUrl('style.css')?>">
        <?php
    }

   /**
    * Add new injection to list of injections
    *
    */
    public function addNewEntryToJSON() {
        if (!class_exists("Injection")) include_once("classes/Injection.php");

        $fields = [
            "field_firstname" => "Firstname",
            "field_lastname" => "Lastname",
            "refuser" => false
        ];

        $injection = new Injection("New Document", "This is another document for testing", $fields);

        dump($injection);


    }


    public function addDummyData() {
        $data1 = [
            "title" => "Health Report",
            "description" => "A report about the general health.",
            "doc_id" => 14,
            "created" => "2021-04-12",
            "fields" => [
                    "field_name" => "name",
                    "field_age" => "age",
                    "field_address" => "address:instance",
                    "field_event_day_date" =>"event_day_14:date",
                    "field_unset" => ""
                ]
            ];
            $data2 = [
                "title" => "Foo Bar",
                "description" => "A report about the general Foo.",
                "doc_id" => 2,
                "created" => "2021-04-13",
                "fields" => [
                        "field_firstname" => "firstname",
                        "field_lastname" => "lastname",
                        "field_bla" => "foobar"
                    ]
                ];
            $data3 = [
                "title" => "Lalalalaa Bar",
                "description" => "Lorem Ipsum dolor es achme lach net.",
                "doc_id" => 7,
                "created" => "2021-04-13",
                "fields" => [
                        "field_firstname" => "otra",
                        "field_lastname" => "cosa",
                        "field_bla" => "portfa"
                    ]
                ];		
        
                $data = [];
                $data[14] = $data1;
                $data[2] = $data2;
                $data[7] = $data3;
        
        
        $this->setProjectSetting("pdf-injections", $data);        
    }

    
}