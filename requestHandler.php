<?php
/** @var \STPH\pdfInjector\pdfInjector $module */

if ($_REQUEST['action'] == 'fileScan') {
    $module->scanFile();
}

else if($_REQUEST['action'] == 'fieldScan') {

    //  Sanitize
    $fieldName = $module->sanitize($_POST["fieldName"]);

    $module->scanField(htmlentities($fieldName));
}

else if($_REQUEST['action'] == 'previewInjection') {

    //  Sanitize
    $document_id = $module->sanitize($_POST["document_id"]);
    $record_id = $module->sanitize($_POST["record_id"]);
    $project_id = $module->sanitize($_POST["project_id"]);

    $module->renderInjection( $document_id, $record_id, $project_id, "json" );
}

else {
    header("HTTP/1.1 400 Bad Request");
    header('Content-Type: application/json; charset=UTF-8');    
    die("The action does not exist.");
}