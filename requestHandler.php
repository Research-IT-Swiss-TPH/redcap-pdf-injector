<?php
/** @var \STPH\pdfInjector\pdfInjector $module */

if ($_REQUEST['action'] == 'fileScan') {
    $module->scanFile();
}

elseif($_REQUEST['action'] == 'fieldCheck') {
    $module->checkField($_POST["fieldValue"]);
}