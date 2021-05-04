<?php
namespace STPH\pdfInjector;

/**
 * Class Injection
 *
 * A class for helping to store and manage Injections Data
 * @author Ekin Tertemiz, Swiss Tropical and Public Health Institute
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

    //  MySQL Date format
    const MYSQL_DATE_FORMAT = "Y-m-d H:i:s";

    function __construct(){        
        $this->created = date(self::MYSQL_DATE_FORMAT);
        $this->updated = NULL;
    }

    public function setInjectionById( array $injections, int $id) {

        $injectionValues = $injections[$id];

        foreach($injectionValues as $key => $value) {

            $this->$key = $value;

        }

    }

    public function setValues(string $title, string $description, array $fields, string $fileName, int $document_id, int $thumbnail_id, string $created = null) {

        $this->title = $title;
        $this->description = $description;
        $this->fields = $fields;
        $this->fileName = $fileName;
        $this->document_id = $document_id;
        $this->thumbnail_id = $thumbnail_id;
        
        if($created != null) {
            //  Keep created date and add updated date
            $this->created = $created;
            $this->updated = date(self::MYSQL_DATE_FORMAT);
        }

    }

    public function getValuesAsArray() {
        return get_object_vars($this);
    }

    public function get($key) {
        return $this->$key;
    }

    

}