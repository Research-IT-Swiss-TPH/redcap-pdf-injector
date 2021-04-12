<?php
namespace STPH\pdfInjector;

renderPageTitle('<i class="fas fa-syringe"></i> PDF Injector');

?>

<div style="width:950px;max-width:950px;" class="d-none d-md-block mt-3 mb-2">
    PDF Injector enables you to fill form fields of your PDFs with your redcap data. 
</div>
<?php

print $module->getProjectSetting("injections");

dump($module);

$data = array();

$data = [
	"id" => 1,
	"title" => "Health Report",
	"description" => "A report about the general health.",
	"created" => "2021-04-12",
	"fields" => [
		"field_name" => "name",
		"field_age" => "age",
		"field_city" => "city"
	]
	];

//print json_encode($data);

//$module->setProjectSetting("pdf-injector", $data);

dump($module->getProjectSetting("pdf-injector"));

$module->addNewEntryToJSON();

?>
<!-- ALERTS TABLE -->
<div style="width:950px;max-width:950px;">
			<div class="mb-1 clearfix">
				<button id='addNewAlert' type="button" class="btn btn-sm btn-success float-left"><i class="fas fa-plus"></i> Add new PDF</button>				
				<div class="float-right mt-2 mr-1">
				</div>
			</div>
            
</div>