<?php
namespace STPH\pdfInjector;

use \FPDM;
use \Exception;

class FPDMH extends FPDM {

    public $hasError = false;
    public $errorMessage;

    public function getFieldNames() {
        
        $this->merge();
        $fieldNames = $this->value_entries;

        //  Remove xref information
        unset($fieldNames["\$_XREF_$"]);

        return array_keys($fieldNames);
    }

    function Error($msg) {
        $this->hasError = true;
        $this->errorMessage = $msg;
    }
    
}