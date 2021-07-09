<?php
/**
 * Previews PDF in new tab
 * 
 */

$document_id = htmlspecialchars($_GET["did"]);
$record_id = htmlspecialchars($_GET["rid"]);
$project_id = htmlspecialchars($_GET["pid"]);

if(isset($document_id) && isset($record_id) && isset($project_id)) {
    //  Render Injection as PDF output format
    $module->renderInjection($document_id, $record_id, $project_id, "pdf");

} else {
    header("HTTP/1.1 400 Bad Request");
    header('Content-Type: text/plain; charset=UTF-8');
    die("Error: Cannot render preview. Are you sure this record, document or project exists?");
}