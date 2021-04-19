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
      
        if (file.size > 2.5e+7) {
          alert('max upload size is 25MB');
        }
        if(file.type != "application/pdf") {
          alert('File type has to be PDF');  
        }
        
        STPH_pdfInjector.scanFile(file);

        
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

STPH_pdfInjector.validateField = function(id) {

    var field = $("#fieldVariableMatch-"+id);
    var value = field.val();
    var helper = $("#variableHelpLine-"+id);

    function resetField(newClass) {
        field.removeClass("is-loading");
        field.removeClass("is-empty");
        field.removeClass("is-valid");
        field.removeClass("is-invalid");
        field.addClass(newClass);
    }

    function resetHelper(newText, newClass) {
        helper.removeClass("text-muted");
        helper.removeClass("text-warning");
        helper.removeClass("text-success");
        helper.removeClass("text-danger");
        helper.addClass(newClass);
        helper.text(newText);
    }

    function checkField(fieldName) {
        $.post(STPH_pdfInjector.requestHandlerUrl + "&action=checkField", {fieldName:fieldName})
        .done(function(response){
            resetField("is-valid");
            resetHelper("Variable is valid.", "text-success");
        })
        .fail(function(response){
            resetField("is-invalid");
            resetHelper("Variable is invalid.", "text-danger");
        })
    }

    function isEmptyOrSpaces(str){
        return str === null || str.match(/^ *$/) !== null;
    }

    function triggerValidation() { 
        resetField("is-loading");
        resetHelper("Checking..", "text-muted");
        checkField(value);
    }

    
    if(!isEmptyOrSpaces(value)) {
        $("#fieldVariableMatch-"+id).val( $.trim(value));
        triggerValidation();
    } else {
        resetField("is-empty");
        resetHelper("No variable defined", "text-warning");
    }
    
}

STPH_pdfInjector.scanFile = function (file) {

    function showError(msg) {
        $("#file").addClass("is-invalid");
        $("#fpdm-error").text("There was a problem processing your file: " + msg);
        $("#fpdm-error").removeClass("d-none");
        $("section#step-2").addClass("disabled");
        $("section#step-3").addClass("disabled");
    }

    function showFile(response) {
        $("#file").removeClass("is-invalid");
        $("#file").addClass("is-valid");
        $("#fpdm-success").html("Your file has been successfully processed. A total of <b>"+ response.fieldData.length +" fields</b> has been detected.")
        $("#fpdm-error").addClass("d-none");
        $("#fileLabel").text(response.file);
        //$("#file").attr("disabled", true);
        $("section#step-2").removeClass("disabled");
        $("section#step-3").removeClass("disabled");

        renderFields(response.fieldData);
    }

    function renderFields(fields) {

        $("#load-output").html("");
       
        $.post( STPH_pdfInjector.templateURL, {fields: fields})
            .done(function( data ) {
                $("#load-output").append( data );
            });

    }

    //  Post send file via ajax formdata
    var fd = new FormData();   
    fd.append('file',file);

    $.ajax({
       url: STPH_pdfInjector.requestHandlerUrl + "&action=fileUpload",
       type: 'post',
       data: fd,
       contentType: false,
       processData: false,
       success: function(response){
            STPH_pdfInjector.log(response);
            showFile(response);
       },
       error: function(error) {
            STPH_pdfInjector.log(error.responseJSON.message);
            showError(error.responseJSON.message);
       }
    });

}
