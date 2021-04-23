<?php
/** @var \STPH\pdfInjector\pdfInjector $module */

if ($_REQUEST['action'] == 'fileScan') {
    $module->scanFile();
}

if($_REQUEST['action'] == 'fieldCheck') {
    $module->checkField($_POST["fieldValue"]);
}

if($_REQUEST['action'] == 'previewInjection') {
    $module->renderInjection(
        $_POST["document_id"],
        $_POST["record_id"]
    );
}

else {
    header("HTTP/1.1 400 Bad Request");
    header('Content-Type: application/json; charset=UTF-8');    
    die("The action does not exist.");
}