<?php

namespace STPH\pdfInjector;

/**
 *  REDCap External Module: PDF Injector
 *  PDF Injector is a REDCap module that enables you to populate fillable PDFs with record data from variables.
 * 
 *  @author Ekin Tertemiz, Swiss Tropical and Public Health Institute
 * 
 */

require 'vendor/autoload.php';

use Exception;
use Files;
use Piping;
use REDCap;

class pdfInjector extends \ExternalModules\AbstractExternalModule {

    protected $isSurvey=false;
    protected $taggedFields= [];

    protected $lang;

    private $injections;
    private $report_id;
    private $ui;
    private $version;
    private $old_version;
    private $enum;
    private $validation_type;


    const SUPPORTED_ACTIONTAGS = ['@TODAY'];

    const ACTION_TAG = '@PDFI';

   
   /**
    *   Allows custom actions to be performed at the top of every page in REDCap 
    *   (including plugins that render the REDCap page header)
    *
    *   @return void
    *   @since 1.0.0
    *
    */
    function redcap_every_page_top($project_id = null) {      
       
        try {
            //  Check if user is logged in
            if($this->getUser()) {
               
                //  Include Javascript and Styles on module page
                if( $this->isModulePage("Injections")) {  
                    $this->initBase();
                    $this->initModule();            
                }

                //  Include Button on Record Home 
                if ($this->isREDCapPage("DataEntry/record_home.php") && isset($_GET["id"]) && isset($_GET["pid"])) {
                    $this->initBase();
                    $this->initPageRecord();
                }

                //  Include Button on Data Export (Reports) page
                if ( $this->isREDCapPage("DataExport/index.php") && isset($_GET["report_id"]) && isset($_GET["pid"]))  {
                    $this->initBase();
                    $str = $this->getProjectSetting("reports-enabled");                    
                    $reportsEnabled = array_map('trim', explode(',', $str));
                    $isReportEnabled = in_array($_GET["report_id"], $reportsEnabled);
                    if($isReportEnabled) {
                        $this->initPageDataExport();
                    }
                }
            }
        } catch(Exception $e) {
            //  Do nothing...
        }

    }

    /**
     *  Allows custom actions to be performed on a data entry form (excludes survey pages) 
     * 
     * @return void
     * @since 3.0.0
     */
    function redcap_data_entry_form($project_id, $record, $instrument, $event_id, $group_id, $repeat_instance) {
        $this->initPageDataEntry($project_id, $record);
    }

    
    //  ====    H A N D L E R S     ====

   /**  
    * 
    *   Scans an uploaded PDF file and returns field data
    *   -> Called via RequestHandler.php over AJAX   
    *
    *   @return string
    *   @since 1.0.0
    */
    public function scanFile(){        
        if(isset($_FILES['file']['name'])){

            if(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION) != "pdf") {
                $this->errorResponse("Invalid file type.");
            }


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
                $this->errorResponse("Invalid PDF.");
                
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
    *   Scans field entered to modal to check if it is valid and returns meta data. 
    *   -> Called via RequestHandler.php over AJAX
    *      
    *   @return array
    *   @since 1.0.0
    *
    */
    public function scanField($fieldName) {

        $fieldMetaData = $this->getFieldMetaData($fieldName);
    
        if($fieldMetaData != "") {
            header('Content-Type: application/json; charset=UTF-8');                
            echo json_encode(array($fieldMetaData));
        } else  $this->errorResponse("Field is invalid");
    
    }

    /**
     *  Gets all enum data
     *  @param string $project_id
     *  @param array $fields
     *  @return array 
     *  @since 1.3.0
     * 
     */
    private function getEnumData($project_id, $fields){

       

        $field_names_array = [];
        foreach ($fields as $key => $field) {
            if( !empty($field["field_name"])){
                $field_names_array[] = '"'.$field["field_name"] . '"';
            }
        }

        $enum_data = [];

        if (!empty($field_names_array)) {
            $field_names = implode(",", $field_names_array);

        
            //  Get all enums (affects types: radio, checkbox and select)
            $sql = "SELECT element_enum,field_name FROM redcap_metadata WHERE project_id = ? AND field_name IN('.$field_names.') AND element_enum IS NOT NULL";
            $result = $this->query($sql, [ $project_id]);
    
            while($row = $result->fetch_object()) {
                //  use parseEnum 
                $enum_data[$row->field_name] = parseEnum($row->element_enum);
            }
        }

        return $enum_data;
    }

    /**
     *  Gets all validation type data
     *  @param string $project_id
     *  @param array $fields
     *  @return array 
     *  @since 1.3.1
     * 
     */    
    private function getValidationTypeData($project_id, $fields) {
        
        $field_names_array = [];
        foreach ($fields as $key => $field) {
           if( !empty($field["field_name"])){
            $field_names_array[] = '"'.$field["field_name"] . '"';
           }
        }

        $validation_type_data = [];

        if(!empty($field_names_array)) {
            $field_names = implode(",", $field_names_array);


            //  Gets all element validation types (Mainly to support date formats)
            $sql = 'SELECT element_validation_type, field_name FROM redcap_metadata WHERE project_id = ? AND field_name IN('.$field_names.') AND element_validation_type IS NOT NULL';
            $result = $this->query($sql, [ $project_id]);
    
            while($row = $result->fetch_object()) {
                $vDFormat = $this->getDateFormatDisplay($row->element_validation_type);
                if($vDFormat) {
                    $validation_type_data[$row->field_name] = $vDFormat;
                }
    
            }
        }


        return $validation_type_data;

    }  

   /**    
    *   Renders Injection by filling field data into file
    *   -> Called via RequestHandler.php over AJAX
    *   @param string $document_id
    *   @param string $record_id 
    *   @param string $project_id
    *   @param string $outputFormat
    *   @return string
    *   @since 1.0.0
    *   
    */
    public function renderInjection($document_id, $record_id = null, $project_id = null, $outputFormat = null) {
        
        //  Check if document id is given
        if( empty($document_id) ){
            $this->errorResponse("Document ID missing.");
        }

        //  Check if Injection data is available
        $injections = self::getProjectSetting("pdf-injections");
        if( empty($injections)) {
            $this->errorResponse("No injection data available.");
        }
        //  Check if doc_id exists
        $injection = $injections[$document_id];

        if(!$injection) {
            $this->errorResponse("Injection does not exist.");
        }

        //  Copy Edoc To Temp
	    $path = \Files::copyEdocToTemp( $document_id );

        //  Get Fields and check if has more than 0
        $fields = $injection["fields"];
        if(count( (array) $fields) == 0) {
            $this->errorResponse("PDF has no fields.");
        }

        if($record_id != null){
        # Do the actual render for a given record_id

            if( !\Records::recordExists($project_id, $record_id) ) {
                $this->errorResponse("Record does not exist.");
            }

            //  Get Enum Data if not already set during batch processing
            if( empty($this->enum[$project_id]) ) {
                $this->enum[$project_id] = $this->getEnumData($project_id, $fields);
            }

            //  Get Validation Type Data if not already set during batch processing
            if( empty($this->validation_type[$project_id]) ) {
                $this->validation_type[$project_id] = $this->getValidationTypeData($project_id, $fields);
            }            

            //  Prepare data access check arrays
            $user_id = USERID;
            $user_rights = \REDCap::getUserRights($user_id);
            $no_access_forms = array_keys(array_filter($user_rights[$user_id]["forms"], function($value){
                return $value == 0;
            }));
            $no_access_fields = [];
            foreach ($no_access_forms as $key => $form) {
                $no_access_fields =  array_merge($no_access_fields, $this->getFieldNames($form));
            }            
           
            foreach ($fields as $key => &$value) {

                //  Skip Injection if field is not mapped
                if($value == "") {
                    continue;
                }
               
                //  fetch variable value for each variable inside field
                $field_name = $value["field_name"];

                //  If user has no data access rights return *NO ACCESS* as value and skip rest
                if(in_array($field_name, $no_access_fields)) { 
                    $value = "*NO ACCESS*"; 
                    continue;
                }

                $element_type = $value["element_type"];

                //  support multiple redcap_data tables
                $data_table = method_exists('\REDCap', 'getDataTable') ? \REDCap::getDataTable($project_id) : "redcap_data";

                $sql = "SELECT value FROM ".$data_table." WHERE record = ? AND project_id = ? AND field_name = ? ORDER BY instance DESC LIMIT 1";
                $result = $this->query($sql, 
                    [
                        $record_id,
                        $project_id,
                        $field_name,
                    ]
                );

                $field_value = $result->fetch_object()->value;

                //  Detect empty record values and set their field value to empty string
                if (empty($field_value)) {
                    $value= "";
                    continue;
                }

                //  Check if element type has enum
                if( !empty($this->enum[$project_id][$field_name]) ) {

                    $value = $this->enum[$project_id][$field_name][$field_value];

                } else {


                    //  Check if field value has variables or action tags
                    $hasVariables = preg_match('/([\[\]])\w+/', $field_value);
                    $hasActiontags = preg_match('/([\@])\w+/', $field_value);

                    if($hasVariables) {

                        $data = \REDCap::getData($project_id, 'array', $record_id);
                        $value = Piping::replaceVariablesInLabel($field_value, $record_id, null, 1, $data, false, $project_id, false,
                        "", 1, false, false, "", null, true, false, false);

                    } 
                    else if($hasActiontags){

                        foreach (self::SUPPORTED_ACTIONTAGS as $key => $actiontag) {
                            if(\Form::hasActionTag($actiontag, $field_value)) {
                                $value = $this->replaceActionTag($field_value, $actiontag);                            
                            }
                            else {
                                //  Fix rendering of '@' without action tags!
                                $value = $field_value;
                            }
                        }
                    }                               
                    else {
                        $value = $field_value;
                    }

                }

                //  Check if element has validation type (support date formats)            
                if( !empty($this->validation_type[$project_id][$field_name]) ) {
                    $value = $this->renderDateFormat($value, $this->validation_type[$project_id][$field_name]);
                }

            }
        } else {
            //  For Preview
            foreach ($fields as $key => &$value) {
                if(!empty($value)) {
                    $value = "[" . $value["field_name"] . "]";
                } else {
                    $value = "(undefined)";
                }
                
            }
        }

        if (!class_exists("FPDMH")) include_once("classes/FPDMH.php");
        $pdf = new FPDMH($path);
        //  Add checkbox support
        $pdf->useCheckboxParser = true;
        //  Fill fields into PDF
        $pdf->Load($fields,true);
        $pdf->Merge();  // Does not support $pdf->Merge(true) yet (which would trigger PDF Flatten to "close" form fields via pdftk)        
        # Future support of PDF flattening would be implemented as optional module setting ensuring pdftk is installed on server

        $string = $pdf->Output( "S" );

        if( $outputFormat == "json" ) {
            header('Content-Type: application/json; charset=UTF-8');
            header("HTTP/1.1 200 ");
            //$base64_string = base64_encode($string);
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data =  'data:application/' . $type . ';base64,' . base64_encode($string);

            echo json_encode(array("data" => $data));
        }         
        if ($outputFormat == "pdf") {
            header('Content-type: application/pdf');
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');

            $filename = uniqid($record_id) . "_" . $injection["fileName"];            
            header('Content-Disposition: inline; filename="' . basename($filename) . '"');

            echo $string;
        } else {
            return $string;
        }
    }

    private function validateFileUpload() {
        if(!$_FILES) {
            //  Throw exception if no file has been set.
            throw new Exception("The file upload is not set.");
        }

        if( pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION) != "pdf") {
            //  Throw exception if file extension is not PDF
            throw new Exception("The file type is invalid.");
        }    

        if( $this->PDFhasError($_FILES['file']['tmp_name']) ) {
            //  Throw exception if PDF is not valid.
            throw new Exception("The PDF file is invalid.");
        }
    }

   /**
    *   Handles $_POST for modes Create, Update, Delete 
    *   @return mixed
    *   @since 1.0.0
    *
    */     
    private function handlePost() {

        if($_POST) {           
            //  if Create:
            //  New Injection
            if($_POST["mode"] == "CREATE") {

                $this->validateFileUpload();

                //  Upload PDF to REDCap edoc storage
                $document_id = Files::uploadFile($_FILES['file']);

                if($document_id == 0 ) {
                    throw new Exception("The PDF upload to REDCap storage failed.");
                }

                //  Upload Thumbnail to REDCap edoc storage
                $thumbnail_id = $this->saveThumbnail($document_id, $_POST['thumbnail_base64']);

                if($thumbnail_id == 0) {
                    throw new Exception("The Thumbnail upload to REDCap storage failed.");
                }
                
                //  Create new Injection entry in database if upload successful  
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

            } if($_POST["mode"] == "UPDATE") {

                    //$this->validateFileUpload();

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

            } if($_POST["mode"] == "DELETE") {
                
                if(!$_POST["document_id"]) {
                    throw new Exception("The document id not set.");
                }

                if (!class_exists("Injection")) include_once("classes/Injection.php");
                $injection = new Injection;
                $injection->setInjectionById( $this->injections, $_POST["document_id"]);

                //  Delete Injection from Storage
                $this->deleteInjection( $injection );

            }

            $this->forceRedirect();
        }

    }

    //  Parse fields for @PDFI Action Tag
    //  https://github.com/lsgs/redcap-instance-table/blob/54f46e874d91c9faed907562df520f41b071ee7b/InstanceTable.php#L126
    private function setTaggedFields() {

        $this->taggedFields = array();
        $instrumentFields = REDCap::getDataDictionary('array', false, true, $this->instrument);

        if(count($this->injections) === 0) return;

        foreach ($instrumentFields as $fieldName => $fieldDetails) {
            $matches = array();
            //  check field_type and form_name
            if ($fieldDetails['field_type']==='descriptive' && $fieldDetails['form_name'] == $_GET["page"]){
                if( preg_match(
                    "/".self::ACTION_TAG."\s*=\s*'?((\d+(,\d+)*))'?\s?/", 
                    $fieldDetails['field_annotation'], 
                    $matches
                    )) {

                    $injection_ids = explode(',', $matches[1]);

                    $valid_injections = [];
                    //  validate injection ids
                    foreach ($injection_ids as $key => $injection_id) {
                        $injection = $this->injections[$injection_id];
                        //  skip if injection does not exist
                        if(!$injection) continue;

                        $valid_injections[] = $injection;
                    }

                    //  skip if no valid injections
                    if(empty($valid_injections)) {
                        continue;
                    }                    

                    $this->taggedFields[] = [
                        "form_name"=> $fieldDetails["form_name"],
                        "field_name" => $fieldDetails["field_name"],
                        "injections" => $valid_injections
                    ];
                }
            }
        }
    }

    private function includeButtonJavaScript($project_id, $record) {

        // Prepare parameter array to be passed to Javascript
        $js_params = array (
            "debug" => $this->getProjectSetting("javascript-debug") == true,
            "project_id" => $project_id,
            "record_id" => $record,
            "tagged_fields" => $this->taggedFields
        );

        ?>
        <link rel="stylesheet" href="<?= $this->getUrl('css/styles.css'); ?>">
        <template id="pdfi-download-button">
            <tr style="display:flex;">
                <td class="col-7">
                    <i class="fa-solid fa-cube text-info me-2"></i>
                    <small style="font-weight:normal;">This field is modified by an external module: <b>PDF Injector</b></small>
                </td>
                <td class="data col-5">
                    <div class="btn-group">
                        <button style="font-size:13px;padding:6px 8px;" class="pdfi-download-button btn btn-defaultrc btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-syringe"></i> PDF Injection
                        </button>
                        <ul class="dropdown-menu">
                        </ul>
                    </div>
                </td>                           
            </tr>
        </template>
        <script src="<?php print $this->getUrl('js/Button.js'); ?>"></script>
        <script>
        STPH_pdfInjector.params = <?= json_encode($js_params) ?>;
        STPH_pdfInjector.previewUrl = "<?= $this->getUrl("preview.php") ?>";
        $(function() {
            $(document).ready(function(){
                STPH_pdfInjector.initButtons();
            })
        });
        </script>
        <?php
    }
    

    //  ====    I N I T I A L I Z E R S    ====
   
   /**
    *   Initializes module base
    *   @return void
    *   @since 1.0.0
    *
    */
    private function initBase() {
        $this->setInjections();
        $this->report_id = $this->sanitize($_GET["report_id"]);
        $this->ui = self::getProjectSetting("ui-mode");

        $this->includePageJavascript();
        $this->includePageCSS();    
    }

   /**
    *   Initializes module handler
    *   @return void
    *   @since 1.0.0
    *
    */    
    private function initModule() {
        $this->handlePost();
    }

   /**
    *   Initializes module on record page
    *   @return void
    *   @since 1.0.0
    *
    */        
    private function initPageRecord(){
        if(count((array)$this->injections) > 0) {
            $this->includePreviewModal();
            if($this->ui == 1 || $this->ui == 3) {
                $this->includeModuleTip();
            }
            if($this->ui == 2 || $this->ui == 3) {
                $this->includeModuleContainer();
            }
        }
    }

   /**
    *   Initializes module on Data Export page
    *   @return void
    *   @since 1.0.0
    *
    */     
    public function initPageDataExport() {                
        $this->includeModalDataExport();
        $this->includePageJavascriptDataExport();
    }

    /**
     * Initializes module on Data Entry page
     * 
     * @return void
     * @since 3.0.0
     */
    private function initPageDataEntry($project_id, $record) {
        $this->setInjections();
        $this->ui = self::getProjectSetting("ui-mode");

        //  Check if any field of type descriptive contains PDFI action tags
        $this->setTaggedFields();
        if(count($this->taggedFields)) {
            $this->includeButtonJavaScript($project_id, $record);
        }        
    }    


    //  ====    I N J E C T I O N S   ====

   /**
    *   Gets injections
    *   @return array
    *   @since 1.0.0
    *
    */ 
    public function getInjections() {
        return $this->injections;
    }

    /**
     * Sets Injections
     * @return void
     * @since 1.3.8
     * 
     */
    private function setInjections() {
        $this->injections = self::getProjectSetting("pdf-injections") ?? [];
    }

    /**
     * Saves Injection to database
     * @param Injection $injection
     * @return void
     * @since 1.0.0
     */
    private function saveInjection( $injection ) {
        $injections = $this->injections;

        //  Insert new injection to injections array
        $injections[ $injection->get("document_id") ] = $injection->getValuesAsArray();

        //  Save injections data into module data base
        $this->setProjectSetting("pdf-injections", $injections);

        //  Set Injections variable to latest state
        $this->setInjections();
    }
  
    /**
     * Deletes Injection from database
     * @param Injection $injection
     * @return boolean
     * @since 1.0.0
     */
    private function deleteInjection( $injection ) {
        $injections = $this->injections;
        
        //  Remove injection from Injections Array
        unset( $injections [$injection->get("document_id") ] );

        //  Flag files for deletion
        //  Actual deletion through (Cron) Jobs::RemoveTempAndDeletedFiles()
        $deletedPDF = Files::deleteFileByDocId( $injection->get("document_id") , PROJECT_ID);
        $deletedThumbnail = Files::deleteFileByDocId( $injection->get("thumbnail_id") , PROJECT_ID);

        //  Save updated injections data into module data base
        if($deletedPDF && $deletedThumbnail) {
            $this->setProjectSetting("pdf-injections", $injections);
            //$this->injections = self::getProjectSetting("pdf-injections");    //replaced by setInjections()
            $this->setInjections();
            return true;

        } else throw new Exception($this->tt("injector_15"));
    }

    /**
     *  Checks if Injection has update by comparing old and new Injections
     *  @param Injection $oldInjection
     *  @param Injection $newInjection
     *  @return boolean
     *  @since 1.0.0
     */
    private function hasUpdate(Injection $oldInjection, Injection $newInjection ) {

        $o_arr = $oldInjection->getValuesAsArray();
        $n_arr = $newInjection->getValuesAsArray();

        unset($o_arr["created"]);
        unset($n_arr["created"]);
        unset($o_arr["updated"]);
        unset($n_arr["updated"]);

        $diff_level_1 = !empty(array_diff_assoc($o_arr, $n_arr));

        if($diff_level_1) {
            return true;
        }

        $o_arr_f = $o_arr["fields"];
        $n_arr_f = $n_arr["fields"];

        $diff_level_2 = !empty(array_diff_assoc($o_arr_f, $n_arr_f));

        if($diff_level_2) {
            return true;
        }

        foreach ($o_arr_f as $field => $meta) {

            //  Typecast to array if is not array, otherwise array_diff_assoc will throw an exception
            $o_arr_f_n = is_array($meta) ? $meta : (array) $meta;
            $n_arr_f_n = is_array($n_arr_f[$field]) ? $n_arr_f[$field] : (array) $n_arr_f[$field];

            $diff_level_3 = !empty(array_diff_assoc($o_arr_f_n, $n_arr_f_n));

            if( $diff_level_3 ){
                break;
                //  exit loop if difference found
            }
        }

        return ( $diff_level_1 || $diff_level_2 || $diff_level_3);
    }

    //  ====    H E L P E R S      ====

    /**
     *  Gets Field Meta Data 
     *  @param string $fieldName
     *  @return array|string
     *  @since 1.3.0
     * 
     */    
    private function getFieldMetaData($fieldName, $pid=null) {
        if($pid == null) {
            $pid = PROJECT_ID;
        }
        
        $sql = 'SELECT * FROM redcap_metadata WHERE project_id = ? AND field_name = ?';
        $result =  $this->query($sql, [$pid, $fieldName]);

        if($result->num_rows == 1) {

            $fieldMetaData = $result->fetch_object();
            $result->close();

            return array(
                "field_name" => $fieldMetaData->field_name,
                "element_type" => $fieldMetaData->element_type
            );                
        }

        else return "";
    }

    /**
     *  Filters fields, checks if valid and returns their meta data
     *  @param array $fields
     *  @return array
     *  @since 1.0.0
     * 
     */
    private function filterForValidVariables($fields = null) {

            if($fields != null) {
                foreach ($fields as $fieldName => &$fieldValue) {
                    $fieldValue = $this->getFieldMetaData($fieldValue);
                }
            } else {
                $fields = [];
            }
            return $fields;
    }

    /**
     *  Saves thumbnail from BASE64 string source as PNG into edoc storage
     *  @param string $d_id
     *  @param string $b64
     *  @return string
     *  @since 1.0.0
     * 
     */
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

    /**
    *   Converts PNG from edoc storage to BASE64
    *   @param string $doc_id
    *   @return string
    *   @since 1.0.0
    *
    */ 
    public function base64FromId($doc_id) {

        //$path = EDOC_PATH . Files::getEdocName( $doc_id, true );
	    $path = \Files::copyEdocToTemp( $doc_id );

        if($path) {
            $type = pathinfo($path, PATHINFO_EXTENSION);
            $data = file_get_contents($path);
    
            return 'data:image/' . $type . ';base64,' . base64_encode($data);   
        } 
    }

   /**
    *   Generates injection default title from file name
    *   @param string $fileName
    *   @return string
    *   @since 1.0.0    
    *
    */
    public function generateTitle($fileName) {
        $s = substr($fileName, 0, -4);
        $s = str_replace("_", " ", $s);
        $s = str_replace("-", " ", $s);
        return $s;
    }

    /**
     *  Replaces Action Tag within Injection
     *  @param string $label
     *  @param string $actiontag
     *  @return string
     *  @since 1.0.0
     */
    private function replaceActionTag($label, $actiontag) {
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
     *  Forces redirect to same page to clear $_POST data
     *  @return void
     *  @since 1.0.0
     */
    private function forceRedirect() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' 
        || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
        header('Location: '.$protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    }

    /**
     *  Returns error and exit with message
     *  @return void
     *  @since 1.0.0
     */
    private function errorResponse($msg) {
        header("HTTP/1.1 400 Bad Request");
        header('Content-Type: application/json; charset=UTF-8');
        die($msg);
    }

    /**
     *  Sanitizes array or variable
     *  @param string|array $arg
     *  @return string|array
     *  @since 1.2.0
     */
    public function sanitize($arg) {
        if (is_array($arg)) {
            return array_map('sanitize', $arg);
        }
    
        return htmlentities($arg, ENT_QUOTES, 'UTF-8');
    }


    /**
     *  Formats date input to given format string
     *  @param string $value
     *  @param string $format
     *  @return string 
     *  @since 1.3.1
     * 
     */      
    private function renderDateFormat($value, $format) {
        $date = date_create($value);
        return date_format($date,$format);
    }

    /**
     *  Gets Date Format String from validation type
     *  Taken from MetaData::getDateFormatDisplay and adjusted to apply with PHP date_format()
     *  https://www.php.net/manual/en/datetime.format.php
     *  @param string $valtype
     *  @return string 
     *  @since 1.3.1
     * 
     */     
	public static function getDateFormatDisplay($valtype)
	{
		switch ($valtype)
		{
			case 'time':
				$dformat = "H:i";
				break;
			case 'date':
			case 'date_ymd':
				$dformat = "y-m-d";
				break;
			case 'date_mdy':
				$dformat = "m-d-y";
				break;
			case 'date_dmy':
				$dformat = "d-m-y";
				break;
			case 'datetime':
			case 'datetime_ymd':
				$dformat = "y-m-d H:i";
				break;
			case 'datetime_mdy':
				$dformat = "m-d-y H:i";
				break;
			case 'datetime_dmy':
				$dformat = "d-m-y H:i";
				break;
			case 'datetime_seconds':
			case 'datetime_seconds_ymd':
				$dformat = "y-m-d H:i:s";
				break;
			case 'datetime_seconds_mdy':
				$dformat = "m-d-y H:i:s";
				break;
			case 'datetime_seconds_dmy':
				$dformat = "d-m-y H:i:s";
				break;
			default:
				$dformat = '';
		}
		return $dformat;
	}
    
    public function PDFhasError($file) {

        if (!class_exists("FPDMH")) include_once("classes/FPDMH.php");

        $pdf = new FPDMH($file);
        return $pdf->hasError;

    }

    //  ====    I N C L U D E S     ====

   /**
    *   Includes Page JavaScript files
    *   @return void
    *   @since 1.0.0
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
            STPH_pdfInjector.batchLoaderUrl = "<?= $this->getUrl("batchLoader.php") ?>";
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
    *   Includes Page Style files
    *   @return void
    *   @since 1.0.0
    *
    */
    private function includePageCSS() {
        ?>
        <link rel="stylesheet" href="<?= $this->getUrl('style.css')?>">
        <?php
    }

    /**
     *  Includes module container 
     *  @return void
     *  @since 1.0.0
     */
    private function includeModuleContainer(){

        //  Get Output mode from module settings
        $previewMode = $this->getProjectSetting("preview-mode");

        $injections = $this->injections;
        $pid = $this->sanitize($_GET["pid"]);
        $record_id = $this->sanitize($_GET["id"]);
        $header = '<div style=\"margin:20px 0px 10px;font-size:15px;border-bottom:1px dashed #ccc;padding-bottom:5px !important;\">PDF Injector</div>';

        $row = '<div class=\"row\">';
        $columns = '';
        foreach ($injections as $key => $injection) {
            $thumbnailBase64 = $this->base64FromId($injection["thumbnail_id"]);
            $column = '';
            $column = '<div class=\"col-sm-2\">';
            if($previewMode == 'modal') {
                $column .= '<div onclick=\"STPH_pdfInjector.previewInjection('.$key.','.$injection["document_id"].', '.htmlspecialchars($record_id). ', '.htmlspecialchars($pid).');\" class=\"pdf-thumbnail thumbnail-hover my-shadow d-flex justify-content-center align-items-center\">';
            } else {
                $column .= '<div class=\"pdf-thumbnail thumbnail-hover my-shadow d-flex justify-content-center align-items-center\"><a class=\"dropdown-item\" id=\"submit-btn-inject-pdf-'.$key.'\" target=\"_blank\" href=\"'.$this->getUrl("preview.php").'&did='.$injection["document_id"].'&rid='.htmlspecialchars($record_id).'\">';
            }
            $column .= '<img id=\"pdf-preview-img\" src=\"'.$thumbnailBase64.'\">';
            if($previewMode == 'modal') {
                $column .= '</div>';
            } else {
                $column .= '</a></div>';
            }
            $column .= '<span style=\"display:block;margin-top:15px;text-align:center;font-weight:bold;letter-spacing:1px;\">'.$injection["title"].'</span>';
            $column .= '</div>';
            $columns .= $column;
        }
        $row .= $columns . '</div>';
        $body = '<div class=\"container-fluid\">'.$row.'</div>';
        $moduleContainer = $header . $body;

        ?>
        <script type="text/javascript">
        // IIFE - Immediately Invoked Function Expression
        (function($, window, document) {
            $( document ).ready(function() {
                var moduleContainer = "<?= $moduleContainer ?>" ;                
                $("#event_grid_table").after(moduleContainer);
            });
        }(window.jQuery, window, document));
        </script>
        <?php

    }

    /**
     *  Includes module tip
     *  @return void
     *  @since 1.0.0
     * 
     */
    private function includeModuleTip() {

        //  Get Output mode from module settings
        $previewMode = $this->getProjectSetting("preview-mode");

        $injections = $this->injections;
        $pid = $this->sanitize($_GET["pid"]);
        $record_id = $this->sanitize($_GET["id"]);
        ?>

        <div id="formSaveTip" style="position: fixed; left: 923px; display: block;">
            <div class="btn-group nowrap" style="display: block;">
                <button class="btn btn btn-primaryrc btn-savedropdown dropdown-toggle" tabindex="0" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="fas fa-syringe" aria-hidden="true"></span> PDF Injection
                </button>
                <div class="dropdown-menu">          
                <?php
                foreach ($injections as $key => $injection) {
                    if($previewMode == 'modal') {
                        print '<a class="dropdown-item" href="javascript:;" id="submit-btn-inject-pdf-'.$key.'" onclick="STPH_pdfInjector.previewInjection('.$key.','.$injection["document_id"].', '.htmlspecialchars($record_id). ', '.htmlspecialchars($pid).');">'.$injection["title"].'</a>';
                    } else {
                        print '<a class="dropdown-item" id="submit-btn-inject-pdf-'.$key.'" target="_blank" href="'.$this->getUrl("preview.php").'&did='.$injection["document_id"].'&rid='.htmlspecialchars($record_id).'">'.$injection["title"].'</a>';
                    }
                }
                ?>
                </div>           
            </div>
        </div>
        <?php
    }

    /**
     *  Includes Modal for Preview
     *  @return void
     *  @since 1.0.0
     */
    private function includePreviewModal() {
        global $lang;
        ?>
        <!-- Preview Modal -->
        <div class="modal fade" id="external-modules-configure-modal-preview" tabindex="-1" role="dialog" data-bs-toggle="modal" data-bs-backdrop="static" data-keyboard="true" aria-labelledby="Codes">
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
                        <button type="button" class="btn btn-defaultrc" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>        
        <?php
    }

    /**
     *  Includes Modal for Data Export
     *  @return void
     *  @since 1.0.0
     */
    private function includeModalDataExport() {
        ?>
        <!-- Data Export Modal -->
        <div class="modal fade" id="external-modules-configure-modal-data-export" tabindex="-1" role="dialog" data-bs-backdrop="static" data-keyboard="true" aria-labelledby="Codes">
            <div class="modal-dialog" role="document" style="width: 800px">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close closeCustomModal" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel">
                            <span id="">PDF Injector - </span>
                            <span id="">Report <?= $this->report_id ?></span>                            
                        </h4>
                    </div>
                    <div class="modal-body">
                        <div id="modal_message_preview" style="margin:0;width:100%;">
                            <div class="mb-3">
                            <div id="batch-load-livefilters">
                            </div>
                            </div>
                            <div class="mb-3">
                                <select id="batch-load-select" onChange="STPH_pdfInjector.setDownload(this.value)" class="form-select">
                                <option hidden>Choose an Injection</option>
                                <?php
                                    foreach ($this->injections as $key => $injection) {                         
                                        $option = '<option value="'.$injection["document_id"].'">'.$injection["title"].'</option>';
                                        echo $option;
                                    }
                                ?>
                                </select>
                            </div>

                            <?php
                        foreach ($this->injections as $key => $injection) {
                            ?>                         
                            <div 
                                id="report-injection-download-<?= $injection["document_id"] ?>"
                                class="mb-3 injection-report-download d-none">
                                <span>Injection Title: <?= $injection["title"] ?></span><br>
                                <span>Injection ID: <?= $injection["document_id"] ?></span><br><br>
                                <div class="text-center">
                                <button                                     
                                    class="btn btn-rcgreen"
                                    onClick="STPH_pdfInjector.loadBatch('<?= $injection["document_id"] ?>',<?= $this->report_id ?>)" type="button" 
                                    class="btn btn-primary">
                                    Download
                                </button>
                                </div>
                            </div>
                            <?php
                        }
                        ?>      

                        </div>
                        <div id="batch-load-spinner" class="text-center d-none">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                             Loading..
                        </div>

                        <div id="batch-load-success" class="d-none">
                        <div class="alert alert-success" role="alert">
                                Success! PDF Injector has downloaded PDFs based on Report. You can safely close this dialog.
                            </div>                            
                        </div>
                        <div id="batch-load-failure" class="d-none">Failure</div>
                        <div id="batch-load-error-name" class="d-none"></div>
                        <div id="batch-load-error-content" class="d-none"></div>

                    </div>
                    <div class="modal-footer">
                                      
                    <button type="button" class="btn btn-defaultrc" onclick="STPH_pdfInjector.closeModalExportData()">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     *  Includes Page Javavscript for Data Export
     *  @return void
     *  @since 1.0.0
     * 
     */
    private function includePageJavascriptDataExport() {
        ?>
        <script>
        $(function() {
            $(document).ready(function(){
                STPH_pdfInjector.initPageDataExport();
            })
        });                    
        </script>
        <?php        
    }

    /**
     * Duplicate of escape method from ExternalModules class, since it has been added in v13.1.2 and might not be supported
     * on most insitutions
     * 
     * @since 1.0.0
     */
	public static function escape($value){
		$type = gettype($value);

		/**
		 * The unnecessary casting on these first few types exists solely to inform psalm and avoid warnings.
		 */
		if($type === 'boolean'){
			return (bool) $value;
		}
		else if($type === 'integer'){
			return (int) $value;
		}
		else if($type === 'double'){
			return (float) $value;
		}
		else if($type === 'array'){
			$newValue = [];
			foreach($value as $key=>$subValue){
				$key = static::escape($key);
				$subValue = static::escape($subValue);
				$newValue[$key] = $subValue;
			}

			return $newValue;
		}
		else if($type === 'NULL'){
			return null;
		}
		else{
			/**
			* Handle strings, resources, and custom objects (via the __toString() method. 
			* Apart from escaping, this produces that same behavior as if the $value was echoed or appended via the "." operator.
			*/
			return htmlspecialchars(''.$value, ENT_QUOTES);
		}
	}

    //  ====    H O U S E K E E P I N G     ====

    /**
     * Check if we new Bootstrap
     * 
     * 
     */
    function bs_v5() {
        if (\REDCap::versionCompare(REDCAP_VERSION, '13.4.6') >= 0) {
            return true;
        }
        return false;
    }


   /**
    *   Triggered when a module version is changed.
    *   @return void
    *   @since 1.3.0
    *
    */
    function redcap_module_system_change_version($version, $old_version) {
        $this->version = substr($version, 1);
        $this->old_version = substr($old_version, 1);
        //  since Update to Version 1.3

        $this->updateTo_v1_3_x();
    }

   /**
    *   Runs database update when $old_version < 1.2.9 and $version >= 1.3.0
    *   
    *   @return void        
    *   @since 1.3.0
    *
    */
    private function updateTo_v1_3_x(){

        $from_1_2_x = version_compare('1.2.9', $this->old_version) == 1;
        $to_1_3_x = version_compare('1.2.9', $this->version) == -1;
        
        if( $from_1_2_x && $to_1_3_x ) {

            //  First get all project id's for external module setting where key==pdf-injections
            $sql = "SELECT project_id FROM redcap_external_module_settings WHERE `key` = 'pdf-injections'";
            $result = $this->query($sql, []);

            $pids = [];
            while($row = $result->fetch_assoc()){
                $pids[]=$row["project_id"];
            }

            //  Loop over projects
            foreach ($pids as $key => $pid) {
                
                \REDCap::logEvent(
                    'PDFI Module Update from version '.$this->old_version.' to version '.$this->version.'.', 
                    'Upgrade PDF Injections in database to store element types for fields.',
                    'SELECT project_id FROM redcap_external_module_settings WHERE `key` = "pdf-injections"',
                    null,null, $pid
                );
            
                $injections = $this->getProjectSetting("pdf-injections", $pid);
                $new_injections = [];
    
                foreach ($injections as $key => $injection) {
                    $new_injection = [];
                    foreach ($injection as $element => $data) {
                        $new_data = [];
                        if($element == 'fields') {
                            $new_fields = [];
                            foreach ( $data as $field => $value) {
                                if(is_string($value)){
                                    $new_value = $this->getFieldMetaData($value, $pid);
                                } else {
                                    $new_value = $value;
                                }
                                $new_fields[$field] = $new_value;
                            }
                            $new_data = $new_fields;
                        } else {
                            $new_data = $data;
                        }
                        $new_injection[$element] = $new_data;
                    }
                    $new_injections[$key] = $new_injection;
                }
    
                try {
                    $this->setProjectSetting("pdf-injections", $new_injections, $pid);
                    \REDCap::logEvent("PDF Injector Database update was successfull!");
                } catch(\Exception $e) {
                    \REDCap::logEvent("PDF Injector Database update failed!");
                    throw new Exception($e);                
                }
            }
        }
    }    
}
