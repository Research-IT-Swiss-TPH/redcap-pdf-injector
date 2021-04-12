<?php
namespace STPH\pdfInjector;

class Injection {
    private $id;
    public $title;
    public $description;
    protected $created;
    protected $fields;

    public function __construct($title, $description, $fields) {

        $this->title = $title;
        $this->description = $description;
        $this->fields = $fields;

    }

    

}