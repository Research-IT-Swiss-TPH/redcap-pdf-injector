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


    //  Reset Modal on close
    $('[name=external-modules-configure-modal]').on('hidden.bs.modal', function () {
        //  Reset form
        document.getElementById("saveInjection").reset();
        //  Custom File Input
        $("#fpdm-success").addClass("d-none");
        $("#fpdm-error").addClass("d-none");
        $("#fileLabel").text("Choose file...");
        $("#file").removeClass("is-invalid").removeClass("is-valid");
        //  Thumbnail
        $("#pdf-preview-img").remove();
        $('[name="thumbnail"]').val("");
        //  Section 2
        $("section#step-2").addClass("disabled");
        $("#load-output").html("");
        //  Button
        $("#btnModalsaveInjection").attr("disabled", false);
    });    

    $(':file').on('change', function () {
        var file = this.files[0];
      
        if (file.size > 2.5e+7) {
          alert('max upload size is 25MB');
        }
        if(file.type != "application/pdf") {
          alert('File type has to be PDF');  
        }
        else {
            STPH_pdfInjector.scanFile(file);
        }        
        
      });

}

//  New/Edit Injection Modal
STPH_pdfInjector.editInjection = function(index=null, InjecNum=null){

    // returns a new object with the values at each key mapped using mapFn(value)
    function objectMap(object, mapFn) {
        return Object.keys(object).reduce(function(result, key) {
            result[key] = mapFn(object[key])
            return result
        }, {})
    }

    if (index == null) {
        $('#add-edit-title-text').html("Create New Injection");
        $('[name="mode"]').val("CREATE");
    } else {
        $('[name="mode"]').val("UPDATE");
        //  Pre-Fill Data
        var attr = STPH_pdfInjector.getInjectionData(index);
        
        if(attr) {
            //  Pre-Fill Data
            $('#add-edit-title-text').html('Edit Injection #'+InjecNum);
            $('[name="title"').val(attr.title);
            $('[name="description"').val(attr.description);
            $('[name="file"').addClass("is-valid");
            $("#fpdm-success").html("File is valid.");
            $('#fileLabel').text(attr.fileName);

            //  Set thumbnail image
            var img = $('<img id="pdf-preview-img">');
            var src = $('#pdf-preview-main-'+InjecNum).attr('src');
            img.attr('src', src);
            img.appendTo('#new-pdf-thumbnail');            

            var fieldNames = [];
            Object.keys(attr.fields).map(function(key, index) {
                fieldNames[index] = key;
            })

            var fieldValues = [];
            Object.keys(attr.fields).map(function(key, index) {
                fieldValues[index] = attr.fields[key];
            })
            
            STPH_pdfInjector.renderFields(fieldNames);

        }
    }

    //  Show Modal
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

    function uploadError(msg) {
        $("#file").addClass("is-invalid");
        $("#fpdm-success").addClass("d-none");
        //$("#fpdm-error").text("There was a problem processing your file: " + msg);
        $("#fpdm-error").html("The file you selected could not be processed. It seems like your PDF is not valid or not readable. <a style=\"font-size:10.4px\" href=\"#docs-pdftk\">Read more</a> on how to prepare your PDF to make it injectable!")
        $("#fpdm-error").removeClass("d-none");
        $("#fileLabel").text("Choose another file...");
        $("section#step-2").addClass("disabled");
        $("section#step-3").addClass("disabled");
    }

    function uploadSuccess(response) {

        $("#file").removeClass("is-invalid");
        $("#file").addClass("is-valid");
        $("#fpdm-success").html("Your file has been successfully processed. A total of <b>"+ response.fieldData.length +" fields</b> has been detected.")
        $("#fpdm-error").addClass("d-none");
        $("#fileLabel").text(response.file);
        //$("#file").attr("disabled", true);
        $("section#step-2").removeClass("disabled");

        renderFields(response.fieldData);
        renderPDF(response.pdf64);
        $("#btnModalsaveInjection").attr("disabled", false);
    }

    function renderFields(fields) {

        //  Reset fields
        $("#load-output").html("");

        if(fields.length > 0) {
            $.post( STPH_pdfInjector.templateURL, {fields: fields})
            .done(function( data ) {
                $("#load-output").append( data );
            });
        } else {
            $("#load-output").html("The uploaded PDF has no fields.");
        }     

    }

    function base64ToUint8Array(base64) {
        // convert base64 to int 8 Array
        var raw = atob(base64);
        var uint8Array = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) {
          uint8Array[i] = raw.charCodeAt(i);
        }
        return uint8Array;
    }

    function makeThumb(page) {
        // draw page to fit into 96x96 canvas
        var vp = page.getViewport({ scale: 1, });
        var canvas = document.createElement("canvas");
        var scalesize = 1;
        canvas.width = vp.width * scalesize;
        canvas.height = vp.height * scalesize;
        var scale = Math.min(canvas.width / vp.width, canvas.height / vp.height);
        console.log(vp.width, vp.height, scale);
        return page.render({ canvasContext: canvas.getContext("2d"), viewport: page.getViewport({ scale: scale }) }).promise.then(function () {
            return canvas; 
        });
      }

    function renderPDF(base64Data) {
        
        $("#pdf-preview-spinner").removeClass("d-none");
        var pdfData = base64ToUint8Array(base64Data);
        var div = document.getElementById("new-pdf-thumbnail");
        

        pdfjsLib.getDocument(pdfData).promise.then(function (doc) {
            var pages = []; while (pages.length < 1) pages.push(pages.length + 1);
            return Promise.all(pages.map(function (num) {
                // create a div for each page and build a small canvas for it
                
                return doc.getPage(num).then(makeThumb)
                .then(function (canvas) {
                    var img = new Image();
                    img.src = canvas.toDataURL();
                    $('[name="thumbnail_base64"]').val(img.src.split(';base64,')[1]);
                    //  .split(';base64,')[1]
                    img.id = "pdf-preview-img";
                    div.appendChild(img);
                    $("#pdf-preview-spinner").addClass("d-none");
                });
            }));
            }).catch(console.error);

            console.log("ok2")
    }

    //  Reset PDF Thumbnail
    $("#pdf-preview-img").remove();

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
            uploadSuccess(response);
       },
       error: function(error) {
            STPH_pdfInjector.log(error.responseJSON.message);
            uploadError(error.responseJSON.message);
       }
    });

}

STPH_pdfInjector.renderFields = function(fields) {

    //  Reset fields
    $("#load-output").html("");

    if(fields.length > 0) {
        $.post( STPH_pdfInjector.templateURL, {fields: fields})
        .done(function( data ) {
            $("#load-output").append( data );
        });
    } else {
        $("#load-output").html("The uploaded PDF has no fields.");
    }     

}