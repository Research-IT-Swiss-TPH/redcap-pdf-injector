<?php

$response = "";
$fields = $_POST["fields"];

foreach ($fields as $key => $value) {
    
    $response .= '<div class="form-row align-items-center">';
    $response .= '<div class="col-auto">';
    $response .= '<label class="sr-only" for="inlineFormInputGroup"></label>';
    $response .= '<div class="input-group mb-2">';
    $response .= '<div class="input-group-prepend"><div class="input-group-text">'.$value.'</div></div>';
    $response .= '<input type="text" class="form-control is-empty variable-input" name="fields['.$value.']" id="fieldVariableMatch-'.$key.'" placeholder="" onfocusout="STPH_pdfInjector.validateField('.$key.');">';
    $response .= '</div></div><div class="col-auto"><div class="form-check mb-2"> <label class="form-check-label" for="autoSizingCheck">';
    $response .= '<small id="variableHelpLine-'.$key.'" class="text-warning">Variable is empty</small>';
    $response .= '</label></div></div></div>';
}

print $response;

?>

