<?php

    /**
    * Injects multiple PDFs at once an serves them as a .zip download
    * 
    * To Do: Serve Download as merged PDF. pdftk as server dependency is required
    */

    global $Proj;

    //  Simulates memory exhaust error
    function simulate_memory_exhaust() {
        $a = 'x';
        while (true) {
            $a = $a.$a;
        }
    }


    try {

        //simulate_memory_exhaust();

        $batchId = "";
        #  Retrieve and sanitize parameters
        $params = array(
            "document_id" => htmlspecialchars($_GET["did"]),
            "report_id" => htmlspecialchars($_GET["rid"]),
            "project_id" => htmlspecialchars($_GET["pid"]),
            "dl_format" => htmlspecialchars($_GET["dlf"])
        );

        //  Create a unique hash everytime we start a batch, so that our temporary files can be structured better
        $batchId = time() . "_" . substr(hash('sha256', json_encode($params)), 0, 20); ;

        #   Validate request parameters

        //  Retrieve reports and check if report exists
        $report = \DataExport::getReports($params["report_id"]);

        $reportExists = isset($report) && !empty($report);

        //  Retrieve List of reports that are enabled and check if retrieved report is enabled
        $str = $module->getProjectSetting("reports-enabled");
        $reportsEnabled = array_map('trim', explode(',', $str));
        $isReportEnabled = in_array($params["report_id"], $reportsEnabled);

        //  Retrieve Injections and check if retrieved injection exists
        $injections = $module->getProjectSetting("pdf-injections");    
        $injectionExists = array_key_exists($params["document_id"], $injections);

        //  Die if checks do not pass
        if(!$reportExists || !$isReportEnabled || !$injectionExists) {
            die("Invalid Request");
        }

        #   Prepare injection data

        // Build sort array of sort fields and their attribute (ASC, DESC)
        $sortArray = array();
        if ($report['orderby_field1'] != '') $sortArray[$report['orderby_field1']] = $report['orderby_sort1'];
        if ($report['orderby_field2'] != '') $sortArray[$report['orderby_field2']] = $report['orderby_sort2'];
        if ($report['orderby_field3'] != '') $sortArray[$report['orderby_field3']] = $report['orderby_sort3'];
        // If the only sort field is record ID field, then remove it (because it will sort by record ID and event on its own)
        if (count($sortArray) == 1 && isset($sortArray[$Proj->table_pk]) && $sortArray[$Proj->table_pk] == 'ASC') {
            unset($sortArray[$Proj->table_pk]);
        }


        //  Check syntax of logic string: If there is an issue in the logic, then return false and stop processing
        if ($report['limiter_logic'] != '' && !LogicTester::isValid($report['limiter_logic'])) {
            throw new Exception('Invalid Report Logic.');
        }

        //  Build Live Filters (Obtain any dynamic filters selected from query string params, i.e. lf1, lf2, lf3)
        list ($liveFilterLogic, $liveFilterGroupId, $liveFilterEventId) = \DataExport::buildReportDynamicFilterLogic($params['report_id']);    

        // If a live filter is being used, then append it to our existing limiter logic from the report's attributes
        if ($liveFilterLogic != "") {
            if ($report['limiter_logic'] != '') {
                $report['limiter_logic'] = "({$report['limiter_logic']}) and ";
            }
            $report['limiter_logic'] .= $liveFilterLogic;
        }

        //  Retrieve data with filter logic and sorting
        //  Records::getData() using $report['limiter_logic']

        $pid = $params["project_id"];
        $returnFormat = 'array';
        $fields = array($Proj->table_pk);
        $filterLogic = $report['limiter_logic'];

        $data = Records::getdata(
            $pid, $returnFormat, null, $fields, null, null, null, null, null, $filterLogic, null, null, null, null, null, $sortArray,false, false, false, true, false, false, $report['filter_type'], false, false, false, false, false, null, 0, false, null, null, false, 0, array(), false, false
        );

        //  Flatten $data array into $records
        $records = [];
        foreach ($data as $key => $record) {
            //  Ignore event id since it is irrelevant through report generation
            $records[] = reset($record)[$Proj->table_pk];
        }

        #   To Do: Create a new instance from PDF Merger class
        #   Limitation: If pdftk is installed and merging is enabled and PDF download is requested'
        #   Possible Libraries to use: 
        #   - http://www.fpdf.org/en/script/script94.php
        #   - https://github.com/clegginabox/pdf-merger

        //$pdf = new \Clegginabox\PDFMerger\PDFMerger;

        //require('classes/fpdf_merge.php');
        //$merge = new FPDF_Merge();

        //  Generate variables used for filenames
        $injection = $injections[$params["document_id"]];

        $lbl_ids = "P".$params["project_id"] . "R".$params["report_id"] . "I".$params["document_id"];
        $lbl_names = strtolower(str_replace(" ", "_", $injection["title"]) . "_" . str_replace(" ", "_", $report["title"]));

        //  Create temporary files by looping through records
        $files = [];
        foreach ($records as $key => $record) {

            //  Throw exception if records could not be retrieved with Records::getdata
            if(empty($record)) {
                throw new Exception("Record data empty. There seems to be an incompatiblity with your REDCap version.", 1);            
            }        
            
            //  Create directory if not exists
            mkdir(__DIR__ . "/tmp");
            mkdir(__DIR__ . "/tmp" . "/" . $batchId);
            //  Write pdf content into temporary file that is going to be deleted after the ZIP has been created
            $path = __DIR__ . "/tmp". "/". $batchId .  "/". $lbl_ids . "-" . $lbl_names . "_" . $record . ".pdf";
            $filename = $module->getSafePath($path);
            $fp = fopen($filename, 'x');

            //  Write to temp file
            $content = $module->renderInjection($params["document_id"], $record, $params["project_id"]);
            fwrite($fp, $content);
            fclose($fp);
            $files[] = $filename;

            #   To do: add files to merger instance
            //$pdf->addPDF($filename, 'all');            
            //$merge->add($filename);

        }

        #   To do: Merge multiple PDFs into one
        //$pdf->merge('browser', 'newTest.pdf', 'P');
        //$merge->output();

        //  Put all files into a zip archive and stream download
        $path = $lbl_ids . "_" . $lbl_names .".zip";
        $zipname = $module->getSafePath($path);
        $zip = new ZipArchive;
        $zip->open($zipname, ZipArchive::CREATE);
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }

        if( !$module->getProjectSetting("disable-readme")) {
            //  Add information file
            $zip->addFromString(
                "README.txt", 
                "===================================================" . "\n" .
                "\n" .
                "Project:\t\t\t" . $Proj->project["app_title"] . "\n" .
                "Report:\t\t\t\t" . $report["title"] . "\n" .
                "Injection:\t\t\t" . $injection["title"]  . "\n" .
                "Records Total:\t\t\t" . count($records) . "\n" .
                "Date Created:\t\t\t" . date("Y-m-d H:i:s") . "\n" .
                "\n" .
                "===================================================" . "\n" .
                "\n" .
                "PDF Batch Export created with PDF Injector" . "\n" . 
                "Documentation: https://research-it-swiss-tph.github.io/redcap-pdf-injector/" . "\n" . 
                "\n" .
                "===================================================" . "\n"
            );
        }

        $zip->close();

        if(true) {                
            header('Content-Type: application/zip');
            header('Content-disposition: attachment; filename='.basename($zipname));
            header('Content-Length: ' . filesize($zipname));
            readfile($zipname);
        }

        //  Cleanup (since using basename() caused the .zip to be saved in the module folder)
        unlink($zipname);

        //  Cleanup files
        foreach ($records as $key => $record) {
            $path = __DIR__ . "/tmp". "/". $batchId . "/".$lbl_ids."-".$lbl_names."_".$record.".pdf";
            if(file_exists($path)) {
                $filename = $module->getSafePath($path);
                unlink($filename);
            }
        }

        //  Cleanup directory
        if(is_dir(__DIR__ . "/tmp". "/". $batchId)) {
            rmdir(__DIR__ . "/tmp". "/". $batchId);
        }

        //  Reset enum data
        $module->enum[$pid] = [];

        exit();
    } catch (\Throwable $th) {
        header('HTTP/1.0 500 Internal Server Error');
        header('Content-Type: application/json');
        $exceptionType = get_class($th);

        echo get_class($th);

        if($exceptionType === "TypeError") {
            echo $th;
        }
         else {
            echo json_encode(array(
                'error' => array(
                    'msg' => $th->getMessage(),
                    'code' => $th->getCode(),
                    'stack' => $th->getTrace()
                ),
            ));            
         }
    }
    
   