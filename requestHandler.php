<?php
/** @var \STPH\pdfInjector\pdfInjector $module */

if ($_REQUEST['action'] == 'fileUpload') {
    $module->scanFile();
}

elseif($_REQUEST['action'] == 'checkField') {
    $module->checkField($_POST["fieldName"]);
}