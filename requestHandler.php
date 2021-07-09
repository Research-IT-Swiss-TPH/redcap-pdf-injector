<?php
/** @var \STPH\pdfInjector\pdfInjector $module */

if ($_REQUEST['action'] == 'fileScan') {
    $module->scanFile();
}

else if($_REQUEST['action'] == 'fieldScan') {
    $module->scanField($_POST["fieldName"]);
}

else if($_REQUEST['action'] == 'previewInjection') {
    $module->renderInjection(
        $_POST["document_id"],
        $_POST["record_id"],
        $_POST["project_id"],
        "json"
    );
}

else {
    header("HTTP/1.1 400 Bad Request");
    header('Content-Type: application/json; charset=UTF-8');    
    die("The action does not exist.");
}