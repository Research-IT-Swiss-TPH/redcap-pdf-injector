<?php
namespace STPH\pdfInjector;

use \FPDM;
use \Exception;

class FPDMH extends FPDM {

    public $hasError = false;
    public $errorMessage;

    public function getFieldData() {
        
        $this->merge();
        $fieldData = $this->value_entries;

        //  Remove xref information
        unset($fieldData["\$_XREF_$"]);

        return array_keys($fieldData);
    }

    function Error($msg) {
        $this->hasError = true;
        $this->errorMessage = $msg;
    }
    
}