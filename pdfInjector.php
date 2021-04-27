<?php

// Set the namespace defined in your config file
namespace STPH\pdfInjector;

require 'vendor/autoload.php';

use \Exception;
use \Files;
use \Piping;

// Declare your module class, which must extend AbstractExternalModule 
class pdfInjector extends \ExternalModules\AbstractExternalModule {

    private $injections;

    //  Supported Action Tags
    const SUPPORTED_ACTIONTAGS = ['@TODAY'];

   /**
    * Constructs the class
    *
    */
    public function __construct()
    {        
        parent::__construct();
        // Other code to run when object is instantiated

    }

    private function replaceActionTagsInLabel($label, $actiontag) {
        switch ($actiontag) {
            case '@TODAY':
                $str =  date("d.m.Y");
                break;
            
            default:
                $str = "";
                break;
        }

        return str_replace($actiontag, $str, $label);

    }

   /**
    *   -> Hooked to redcap_every_page_top
    *
    */
    function redcap_every_page_top($project_id = null) {

        if(isset($_GET["page"])) {

            //  Params
            $record_id = $_GET["id"];

            //  Get fields
            $form = $_GET["page"];
            $project  = new \Project;
            $form = $project->forms[$form];
            $fields = $form["fields"];

        }

        //  Include Javascript and Styles on module page
        if(PAGE == "ExternalModules/index.php" && $_GET["prefix"] == "pdf_injector") {  
            $this->initModule();            
        }
        //  Include Button
        if (PAGE === "DataEntry/record_home.php" && isset($_GET["id"]) && isset($_GET["pid"])) {
            $this->initModuleTip();
        } 
    }

   /**    
    *   -> Called via RequestHandler.php over AJAX
    *   Scans submitted file and returns field names
    */
    public function scanFile(){        
        if(isset($_FILES['file']['name'])){

            $filename = $_FILES['file']['name'];
            $ext =  strtolower(pathinfo($filename,PATHINFO_EXTENSION));
            $tmp_file = $_FILES['file']['tmp_name'];

            //  Process PDF with FPDM - Helper Class (FPDMH)
            if (!class_exists("FPDMH")) include_once("classes/FPDMH.php");
            $pdf = new FPDMH($tmp_file);
            $fieldNames = $pdf->getFieldNames();

            //  Bring array in correct form so it works also with Updates
            $fieldData = [];
            foreach ($fieldNames as $key => $fieldName) {
                $fieldData[] = [
                    "fieldName" => $fieldName,
                    "fieldValue" => ""
                ];
            }

            if($pdf->hasError) {
            //  Check for errors
                $this->errorResponse($pdf->errorMessage);
                
            } else {
            //  Return as json response
                $data = file_get_contents( $tmp_file );
                $response = array(
                    'file' => $filename,
                    'title' => $this->generateTitle($filename),
                    'fieldData' => $fieldData,
                    'pdf64' => base64_encode($data)
                );
                header('Content-Type: application/json; charset=UTF-8');                
                echo json_encode($response);
                exit();
            }

         }
         else {
                $this->errorResponse("Unknown Error");
         }
    }
    
   /**    
    *   -> Called via RequestHandler.php over AJAX
    *   Checks a given field value if is a variable
    */
    public function scanField($fieldName) {

        $valid = $this->checkSingleField($fieldName);

        if($valid) {
            header('Content-Type: application/json; charset=UTF-8');                
            echo json_encode(array("fieldName" => $fieldName));
        } else  $this->errorResponse("Field is invalid");
    
    }

   /**    
    *   -> Called via RequestHandler.php over AJAX
    *   Renders preview for a given Injection and optionally record
    */
    public function renderInjection($document_id, $record_id = null, $project_id = null) {

        $injections = self::getProjectSetting("pdf-injections");
        $injection = $injections[$document_id];
        //  Check if doc_id exists
        if(!$injections[$document_id]) {
            $this->errorResponse("Injection does not exist.");
        }

        //  get Edoc 
        $path = EDOC_PATH . Files::getEdocName( $document_id, true );
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $file = file_get_contents($path);


        //  Get Fields
        $fields = $injection["fields"];

        if($record_id != null){
            //  check if rec_id exists

            if( !\Records::recordExists($project_id, $record_id) ) {
                $this->errorResponse("Record does not exist.");
            }
           
            foreach ($fields as $key => &$value) {
                
                //  fetch variable value for each variable inside field
                $fieldname = $value;
                $sql = "SELECT value FROM redcap_data WHERE record = ? AND project_id = ? AND field_name = ? LIMIT 0, 1";
                $result = $this->query($sql, 
                    [
                        $record_id,
                        $project_id,
                        $fieldname,
                    ]
                );
                $fieldValue = $result->fetch_object()->value;

                //  Check if field value has variables or action tags
                $hasVariables = preg_match('/([\[\]])\w+/', $fieldValue);
                $hasActiontags = preg_match('/([\@])\w+/', $fieldValue);

                if($hasVariables) {

                    $data = \REDCap::getData($project_id, 'array', $record_id);
                    $value = Piping::replaceVariablesInLabel($fieldValue, $record_id, null, 1, $data, false, $project_id, false,
                    "", 1, false, false, "", null, true, false, false);

                } 
                else if($hasActiontags){

                    foreach (self::SUPPORTED_ACTIONTAGS as $key => $actiontag) {
                        if(\Form::hasActionTag($actiontag, $fieldValue)) {
                            $value = $this->replaceActionTagsInLabel($fieldValue, $actiontag);                            
                        }
                    }
                }                               
                else {
                    $value = $fieldValue;
                }

            }
        }

        if (!class_exists("FPDMH")) include_once("classes/FPDMH.php");
        $pdf = new FPDMH($path);

        $pdf->Load($fields,false);
        $pdf->Merge();

        $string = $pdf->Output( "S" );
        $base64_string = base64_encode($string);

        $data =  'data:application/' . $type . ';base64,' . base64_encode($string);

        header('Content-Type: application/json; charset=UTF-8');
        header("HTTP/1.1 200 ");
        echo json_encode(array("data" => $data, "test"=> $test));

    }

   /**
    * Gets injections
    *
    */ 
    public function getInjections() {
        return $this->injections;
    }

    /**
    * Converts png-file from edoc storage to base64 string
    *
    */ 
    public function base64FromId($doc_id) {

        $path = EDOC_PATH . Files::getEdocName( $doc_id, true );
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);

        return 'data:image/' . $type . ';base64,' . base64_encode($data);    
    }

    public function generateTitle($fileName) {
        $s = substr($fileName, 0, -4);
        $s = str_replace("_", " ", $s);
        $s = str_replace("-", " ", $s);
        return $s;
    }
   
   /**
    * Initializes the module
    *
    */
    private function initModule() {
        $this->injections = self::getProjectSetting("pdf-injections");
        $this->includePageJavascript();
        $this->includePageCSS();
        $this->handlePost();
    }

    private function initModuleTip(){
        $this->injections = self::getProjectSetting("pdf-injections");
        if(count($this->injections) > 0) {
            $this->includePageJavascript();
            $this->includePageCSS();
            $this->includeModuleTip();
        }

    }

    //  Post Handler
    //  Create, Update, Delete
    private function handlePost() {

        if($_POST) {           
            //  if Create:
            //  New Injection
            if($_POST["mode"] == "CREATE") {

                if($_FILES)  {

                    dump($_POST["fields"]);

                    //  Upload PDF and Thumbnail to REDCap edoc storage
                    $document_id = Files::uploadFile($_FILES['file']);
                    $thumbnail_id = $this->saveThumbnail($document_id, $_POST['thumbnail_base64']);
                    
                    //  Create new Injection entry in database if upload successful
                    if($document_id != 0 && $thumbnail_id != 0) {

                        //  Validate fields before save
                        $validFields = $this->filterForValidVariables($_POST["fields"]);

                        if (!class_exists("Injection")) include_once("classes/Injection.php");
                        $injection = new Injection;
                        $injection->setValues(
                            $_POST["title"],
                            $_POST["description"],
                            $validFields,
                            $_FILES['file']['name'],
                            $document_id,                            
                            $thumbnail_id
                         );

                        //  Save Injection to Storage
                        $this->saveInjection( $injection );
    
                    } else throw new Exception($this->tt("injector_15"));
                }

            } if($_POST["mode"] == "UPDATE") {

                if($_FILES)  {

                    if (!class_exists("Injection")) include_once("classes/Injection.php");

                    //  Create old injection instance by id
                    $oldInjection = new Injection;
                    $oldInjection->setInjectionById($this->injections, $_POST["document_id"] );

                    $document_id = $_POST["document_id"];
                    $thumbnail_id = $_POST["thumbnail_id"];
                    $filename = $oldInjection->get("fileName");
                    
                    //  If file has changed overwrite document and thumbnail ids
                    if( $_POST["hasFileChanged"] ) {

                        //  Upload PDF and Thumbnail to REDCap edoc storage
                        $document_id = Files::uploadFile($_FILES['file']);
                        $thumbnail_id = $this->saveThumbnail($document_id, $_POST['thumbnail_base64']);
                        $filename = $_FILES['file']['name'];
                        if( $document_id != 0 && $thumbnail_id != 0 ) {
                            //  Remove old injection with old id
                            $this->deleteInjection( $oldInjection );

                        } else throw new Exception($this->tt("injector_15")); 

                    }

                    $validFields = $this->filterForValidVariables($_POST["fields"]);

                    //  Create new injection instance
                    $newInjection = new Injection;
                    $newInjection->setValues(
                        $_POST["title"],
                        $_POST["description"],
                        $validFields,
                        $filename,
                        intval($document_id),                            
                        intval($thumbnail_id),
                        $oldInjection->get("created")
                    );

                    $hasUpdate  = $this->hasUpdate( $oldInjection, $newInjection );
                    if( $hasUpdate ) {
                        //  Save Injection to Storage
                        $this->saveInjection( $newInjection );
                    } 

                }

            } if($_POST["mode"] == "DELETE") {
                
                if (!class_exists("Injection")) include_once("classes/Injection.php");
                $injection = new Injection;
                $injection->setInjectionById( $this->injections, $_POST["document_id"]);

                //  Delete Injection from Storage
                $this->deleteInjection( $injection );

            }

            $this->forceRedirect();
        }

    }


    private function checkSingleField($fieldName) {
        $pid = PROJECT_ID;
        $sql = 'SELECT * FROM redcap_metadata WHERE project_id = ? AND field_name = ?';
        $result =  $this->query($sql, [$pid, $fieldName]);

        if($result->num_rows == 1) {
            return true;
        }

        return false;
    }

    private function filterForValidVariables($fields) {

        foreach ($fields as $fieldName => &$fieldValue) {
            $valid = $this->checkSingleField($fieldValue);
            if(!$valid) {
                $fieldValue = "";
            }
        }

        return $fields;

    }    

    //  Saves thumbnail from base64 string source as png into edoc storage
    private function saveThumbnail($d_id, $b64) {

        if ( isset( $b64 ) &&  $b64 != '' ) {        
            //  Retrieve Thumbnail as Base64 String and save to docs
            $_FILES['thumbnailFile']['type'] = "image/png";
            $_FILES['thumbnailFile']['name'] = "thumbnail_injection_" . $d_id . ".png";
            $_FILES['thumbnailFile']['tmp_name'] = APP_PATH_TEMP . "thumbnail_injection_" . $d_id . "_" . substr(sha1(mt_rand()), 0, 12) . ".png";
            file_put_contents($_FILES['thumbnailFile']['tmp_name'], base64_decode(str_replace(' ', '+', $b64)));
            $_FILES['thumbnailFile']['size'] = filesize($_FILES['thumbnailFile']['tmp_name']);

            //  Upload File to REDCap, returns edoc id
            return Files::uploadFile($_FILES['thumbnailFile']);

        } else return 0;

    }

    private function saveInjection( Injection $injection ) {
        $injections = $this->injections;

        //  Insert new injection to injections array
        $injections[ $injection->get("document_id") ] = $injection->getValuesAsArray();

        //  Save injections data into module data base
        $this->setProjectSetting("pdf-injections", $injections);
        $this->injections = self::getProjectSetting("pdf-injections");
    }
  
    private function deleteInjection( Injection $injection ) {
        $injections = $this->injections;
        
        //  Remove injection from Injections Array
        unset( $injections [$injection->get("document_id") ] );

        //  Remove documents from storage
        $deletedPDF = Files::deleteFileByDocId( $injection->get("document_id") );
        $deletedThumbnail = Files::deleteFileByDocId( $injection->get("thumbnail_id") );

        //  Save updated injections data into module data base
        if($deletedPDF && $deletedThumbnail) {
            $this->setProjectSetting("pdf-injections", $injections);
            $this->injections = self::getProjectSetting("pdf-injections");
            return true;

        } else throw new Exception($this->tt("injector_15"));
    }

    //  Checks if update is given by comparing array on every level
    private function hasUpdate(Injection $oldInjection, Injection $newInjection ) {

        $o_arr = $oldInjection->getValuesAsArray();
        $n_arr = $newInjection->getValuesAsArray();

        unset($o_arr["created"]);
        unset($n_arr["created"]);
        unset($o_arr["updated"]);
        unset($n_arr["updated"]);

        $diff_level_1 = !empty(array_diff_assoc($o_arr, $n_arr));

        $o_arr_f = $o_arr["fields"];
        $n_arr_f = $n_arr["fields"];

        $diff_level_2 = !empty(array_diff_assoc($o_arr_f, $n_arr_f));

        return ( $diff_level_1 || $diff_level_2 );
    }

    //  Force redirect to same page to clear $_POST data
    private function forceRedirect() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' 
        || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        header('Location: '.$protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    }

    private function errorResponse($msg) {
        header("HTTP/1.1 400 Bad Request");
        header('Content-Type: application/json; charset=UTF-8');
        die($msg);
    }


/*     public function base64ToImage($base64_string, $output_file) {
        $file = fopen($output_file, "wb");
    
        $data = explode(',', $base64_string);
    
        fwrite($file, base64_decode($data[1]));
        fclose($file);
    
        return $output_file;
    } */



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

    private function includeModuleTip() {

        $injections = $this->injections;
        $pid = $_GET["pid"];
        $record_id = $_GET["id"];

        ?>

        <div id="formSaveTip" style="position: fixed; left: 923px; display: block;">
            <div class="btn-group nowrap" style="display: block;">
                <button class="btn btn btn-primaryrc btn-savedropdown dropdown-toggle" tabindex="0" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="fas fa-syringe" aria-hidden="true"></span> PDF Injection
                </button>
                <div class="dropdown-menu">          
                <?php
                foreach ($injections as $key => $injection) {
                    print '<a class="dropdown-item" href="javascript:;" id="submit-btn-inject-pdf-'.$key.'" onclick="STPH_pdfInjector.previewInjection('.$key.','.$injection["document_id"].', '.$record_id. ', '.$pid.');">'.$injection["title"].'</a>';
                }
                ?>
                </div>           
            </div>
        </div>

        <!-- Preview Modal -->
        <div class="modal fade" id="external-modules-configure-modal-preview" tabindex="-1" role="dialog" data-toggle="modal" data-backdrop="static" data-keyboard="true" aria-labelledby="Codes">
            <div class="modal-dialog" role="document" style="width: 800px">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel">
                            <span id="myModalLabelA"><?=$lang['alerts_82']?></span>
                            <span id="myModalLabelB"><?=$lang['alerts_83']?></span>
                            <span id="modalPreviewNumber"></span>
                        </h4>
                    </div>
                    <div class="modal-body">
                        <div id="modal_message_preview" style="margin:0;width:100%;height:100vh;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-defaultrc" data-dismiss="modal"><?=$lang['calendar_popup_01']?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

}