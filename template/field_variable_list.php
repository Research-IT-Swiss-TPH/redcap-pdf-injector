<?php

$response = "";
$fields = $_POST["fields"];

foreach ($fields as $key => $field) {

    if( empty($field["fieldValue"])) {
        $state = "is-empty";
        $text = "Variable is empty";
        $class = "warning";
    } else {
        $state = "is-valid";
        $text = "Variable is valid";
        $class = "success";
    }
    
    $response .= '<div class="form-row align-items-center">';
    $response .= '<div class="col-auto">';
    $response .= '<label class="sr-only" for="inlineFormInputGroup"></label>';
    $response .= '<div class="input-group mb-2">';
    $response .= '<div class="input-group-prepend"><div class="input-group-text">'.htmlspecialchars($field["fieldName"]).'</div></div>';
    $response .= '<input type="text" class="form-control '.$state.' variable-input" name="fields['.htmlspecialchars($field["fieldName"]).']" id="fieldVariableMatch-'.htmlspecialchars($key).'" value="'.htmlspecialchars($field["fieldValue"]).'" placeholder="" onfocusout="STPH_pdfInjector.validateField('.htmlspecialchars($key).');">';
    $response .= '</div></div><div class="col-auto"><div class="form-check mb-2"> <label class="form-check-label" for="autoSizingCheck">';
    $response .= '<small id="variableHelpLine-'.htmlspecialchars($key).'" class="text-'.$class.'">'.$text.'</small>';
    $response .= '</label></div></div></div>';
}

print $response;

?>

