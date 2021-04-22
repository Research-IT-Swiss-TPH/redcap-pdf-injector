<?php
namespace STPH\pdfInjector;

/**
 * Class Injection
 *
 * A class for helping to store and manage Injections Data
 * Author: Ekin Tertemiz
 * 
 */


class Injection
{
    private $title;
    private $description;
    private $fields;
    private $fileName;
    private $document_id;
    private $thumbnail_id;
    private $created;
    private $updated;

    const MYSQL_DATE_FORMAT = "Y-m-d H:i:s";

    function __construct(){
        //  MySQL Date format
        $this->created = date(self::MYSQL_DATE_FORMAT);
        $this->updated = NULL;
    }

    public function setValues(string $title, string $description, array $fields, string $fileName, int $document_id, int $thumbnail_id) {

        $this->title = $title;
        $this->description = $description;
        $this->fields = $fields;
        $this->fileName = $fileName;
        $this->document_id = $document_id;
        $this->thumbnail_id = $thumbnail_id;

    }

    public function getValuesAsArray() {
        return get_object_vars($this);
    }

    public function getId() {
        return $this->document_id;
    }
    

}