<?php
namespace STPH\pdfInjector;

use \FPDM;

class FPDMH extends FPDM {

    public function getFieldData() {
        $this->merge();
        return $this->value_entries;
    }
    
}