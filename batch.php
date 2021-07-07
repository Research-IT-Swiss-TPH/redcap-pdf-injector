<?php
/**
 * Previews multiple PDF in new tab
 * 
 */


global $Proj;
dump($Proj);

#  Get params from url
#  

$params = array(
    "document_id" => htmlspecialchars($_GET["did"]),
    "report_id" => htmlspecialchars($_GET["rid"]),
    "project_id" => htmlspecialchars($_GET["pid"])
);
dump($params);

#  Get records from report_id
#  from \DataExport::doReport() as model procedure

//  1. Get report attributes
$report = \DataExport::getReports(3);
dump($report);

//  2. Prepare limiters, orders, etc.

    // Build sort array of sort fields and their attribute (ASC, DESC)
    $sortArray = array();
    if ($report['orderby_field1'] != '') $sortArray[$report['orderby_field1']] = $report['orderby_sort1'];
    if ($report['orderby_field2'] != '') $sortArray[$report['orderby_field2']] = $report['orderby_sort2'];
    if ($report['orderby_field3'] != '') $sortArray[$report['orderby_field3']] = $report['orderby_sort3'];
    // If the only sort field is record ID field, then remove it (because it will sort by record ID and event on its own)
    if (count($sortArray) == 1 && isset($sortArray[$Proj->table_pk]) && $sortArray[$Proj->table_pk] == 'ASC') {
        unset($sortArray[$Proj->table_pk]);
    }

    dump($sortArray);

    //  Check syntax of logic string: If there is an issue in the logic, then return false and stop processing
    if ($report['limiter_logic'] != '' && !LogicTester::isValid($report['limiter_logic'])) {
        throw new Exception('Invalid Report Logic.');
    }

    //  Retrieve data with filter logic and sorting
    //  Records::getData() using $report['limiter_logic']

    $pid = 20;
    $returnFormat = 'array';
    $fields = array("record_id");
    $filterLogic = $report['limiter_logic'];

    $data = Records::getdata(
        $pid, $returnFormat, null, $fields, null, null, null, null, null, $filterLogic, null, null, null, null, null, $sortArray,false, false, false, true, false, false, $report['filter_type'], false, false, false, false, false, null, 0, false, null, null, false, 0, array(), false, array("record_id")
    );

    dump($data);
