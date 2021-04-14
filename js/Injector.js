/**
 * PDF Injector - a REDCap External Module
 * Author: Ekin Tertemiz
*/

var STPH_pdfInjector = STPH_pdfInjector || {};

// Debug logging
STPH_pdfInjector.log = function() {
    if (STPH_pdfInjector.params.debug) {
        switch(arguments.length) {
            case 1: 
                console.log(arguments[0]); 
                return;
            case 2: 
                console.log(arguments[0], arguments[1]); 
                return;
            case 3: 
                console.log(arguments[0], arguments[1], arguments[2]); 
                return;
            case 4:
                console.log(arguments[0], arguments[1], arguments[2], arguments[3]); 
                return;
            default:
                console.log(arguments);
        }
    }
};

// Initialization
STPH_pdfInjector.init = function() {
    // DataTable
    STPH_pdfInjector.log("PDF Injector - Initializing", STPH_pdfInjector);

    var injectionsDataTable;
	var dataTableSettings = {
		"autoWidth": false,
		"processing": true,
		"paging": false,
		"info": false,
		"aaSorting": [],
		"fixedHeader": { header: false, footer: false },
		"searching": true,
		"ordering": false,
		"oLanguage": { "sSearch": "" },
	}

    // DataTable
    STPH_pdfInjector.log("PDF Injector - Drawing Data Table");

    injectionsDataTable = $('#injectionsPreview').DataTable(dataTableSettings);
    $('#injectionsPreview input[type="search"]').attr('type','text').prop('placeholder','Search');
    $('#injectionsPreview').show();
    injectionsDataTable.draw();

    //  Bindings
    STPH_pdfInjector.log("PDF Injector - Adding Binding(s)");

    $('#addNewInjection').click(() => {
        STPH_pdfInjector.editInjection();
    });

    $(':file').on('change', function () {
        var file = this.files[0];
      
        if (file.size > 1024) {
          alert('max upload size is 1k');
        }

        console.log(file);
      
        // Also see .name, .type
      });    

}

//  New/Edit Injection Modal
STPH_pdfInjector.editInjection = function(index=null, InjecNum=null){

    if (index == null) {
        $('#add-edit-title-text').html("Create New Injection");
    } else {
        $('#add-edit-title-text').html('Edit Injection #'+InjecNum);
        var attr = STPH_pdfInjector.getInjectionData(index);
    }

    if(attr) {
        //  Pre-Fill Data
    }

    $('[name=external-modules-configure-modal]').modal('show');

}

STPH_pdfInjector.deleteInjection = function(index=null, InjecNum=null){
    //Show simpleDialog instead & trigger callback
    alert("Are you sure you want to delete Injection #" + InjecNum + "? \n\n //Show simpleDialog for id "+index+" instead & trigger callback");
}

STPH_pdfInjector.getInjectionData = function(id) {

    return STPH_pdfInjector.params.injections[id];

}
