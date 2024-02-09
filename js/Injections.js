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

    //  Reset Modal on modal close
    $('[name=external-modules-configure-modal]').on('hidden.bs.modal', function () {
        //  Reset form
        document.getElementById("saveInjection").reset();
        //  Custom File Input
        $("#fpdm-success").addClass("d-none");
        $("#fpdm-error").addClass("d-none");
        $("#fileLabel").text("Choose file...");
        $("#file").removeClass("is-invalid").removeClass("is-valid");
        $('[name=hasFileChanged]').val(0);
        //  Thumbnail
        $("#pdf-preview-img").remove();
        $('[name="thumbnail"]').val("");
        //  Section 2
        $("section#step-2").addClass("disabled");
        $("#load-output").html("");
        //  Button
        $("#btnModalsaveInjection").attr("disabled", false);
    });    

    //  Trigger Scan File on file change
    $(':file').on('change', function () {
        var file = this.files[0];
        if (file.size > 2.5e+7) {
          alert('max upload size is 25MB');
        }
        if(file.type != "application/pdf") {
          alert('File type has to be PDF');  
        }
        else {
            //  Reset PDF Thumbnail
            $("#pdf-preview-img").remove();            
            STPH_pdfInjector.scanFile($('#saveInjection')[0]);
        }                
      });

}

/*  editInjection(index, InjecNum)
*   Prepares modal data to Create/Update Injection before triggering the modal
*   index: document_id and primary key of injection
*   InjecNum: chronological numbering  
*/
STPH_pdfInjector.editInjection = function(index=null, InjecNum=null){

    //  Prepare modal data
    if (index == null) {
        //  Create Injection
        $('[name="mode"]').val("CREATE");
        $('#add-edit-title-text').html("Create Injection");
    } else {
        //  Update Injection
        $('[name="mode"]').val("UPDATE");
        $('#add-edit-title-text').html('Edit Injection #'+InjecNum);

        var attr = STPH_pdfInjector.getInjectionData(index);
        if(attr) {
            //  Prepare Step 1 form data
            $('[name="title"').val(attr.title);
            $('[name="description"').val(attr.description);
            $('[name="file"').addClass("is-valid");
            $('[name="document_id"]').val(index);
            $('[name="thumbnail_id"]').val(attr.thumbnail_id);

            $("#fpdm-success").html("File is valid.");
            $('#fileLabel').text(attr.fileName);
            $("section#step-2").removeClass("disabled");

            //  Prepare Step 1 thumbnail
            var img = $('<img id="pdf-preview-img">');
            var src = $('#pdf-preview-main-'+InjecNum).attr('src');
            img.attr('src', src);
            img.appendTo('#new-pdf-thumbnail');            

            //  Prepare Step 2 form data        
            var fieldData = [];
            Object.keys(attr.fields).map(function(key, index) {
                fieldData[index] = {"fieldName": key, "fieldValue": attr.fields[key]}
            })
            STPH_pdfInjector.renderFields(fieldData);
            $("#btnModalsaveInjection").attr("disabled", false);

        }
    }
    //  Trigger Modal
    $('[name=external-modules-configure-modal]').modal('show');

}

/*  deleteInjection(index, InjecNum)
*   Deletes Injection by index
*   index: document_id and primary key of injection
*   InjecNum: chronological numbering  
*/
STPH_pdfInjector.deleteInjection = function(index=null, thumbnail_id, InjecNum=null){    
    $('[name="mode"]').val("DELETE");
    $('#injection-number').text(InjecNum);
    $('[name=document_id]').val(index);
    $('[name=thumbnail_id').val(thumbnail_id);
    $('[name=external-modules-configure-modal-delete-confirmation]').modal('show');
    //alert("Are you sure you want to delete Injection #" + InjecNum + "? \n\n //Show simpleDialog for id "+index+" instead & trigger callback");
}

/*  getInjectionData(id)
*   Gets Injection Data for an id
*  
*/
STPH_pdfInjector.getInjectionData = function(id) {
    return STPH_pdfInjector.params.injections[id];
}

/*  validateField(id)
*   Validates an input field on focus out if the entered value equals a variable
*  
*/
STPH_pdfInjector.validateField = function(id) {
    
    function setFieldState(state, elementType=null) {
        var helper = $("#variableHelpLine-"+id);
        var helperTextClass = getHelperTextClass(state);
        var helperTextType = "";
        
        if(elementType!=null){
            helperTextType = "Type: " + elementType;
        }        

        helper.removeClass("text-muted text-warning text-success text-danger");
        helper.addClass(helperTextClass);
        helper.text("Variable is "+ state + ". " + helperTextType);



        field.removeClass("is-empty is-loading is-valid is-invalid");
        field.addClass("is-"+state);        
    }

    function getHelperTextClass(state) {
        var helperTextClass = "";
        switch(state) {
            case "valid":
                helperTextClass = "text-success";
                break;
            case "invalid":
                helperTextClass = "text-danger";
                break;
            case "empty":
                helperTextClass = "text-warning"
                break;
            case "loading":
                helperTextClass = "text-muted"
                break;
        }
        return helperTextClass;
    }

    function isEmptyOrSpaces(str){
        return str === null || str.match(/^ *$/) !== null;
    }

    function checkField(fieldName) {
        fieldName = $.trim( fieldName );
        field.val(fieldName);

        $.post(STPH_pdfInjector.requestHandlerUrl + "&action=fieldScan", {fieldName:fieldName})
        .done(function(response){
            let elementType = response[0].element_type;
            setFieldState("valid", elementType);
        })
        .fail(function(){
            setFieldState("invalid");
        })
    }

    var field = $("#fieldVariableMatch-"+id);
    var fieldName = field.val();
    
    if(!isEmptyOrSpaces(fieldName)) {
        setFieldState("loading");        
        checkField(fieldName);
    } else {
        setFieldState("empty")
    }
    
}

STPH_pdfInjector.scanFile = function (file) {

    function fileScanError(msg) {
        $("#file").addClass("is-invalid");
        $("#fpdm-success").addClass("d-none");
        $("#fpdm-error").html("The file you selected could not be processed. It seems like your PDF is not valid or not readable. Please read the docs \"Requirements & Limitations\" to learn how to make your <b>PDF fillable</b>! <code>FPDM error message: "+msg+"</code>")
        $("#fpdm-error").removeClass("d-none");
        $("#fileLabel").text("Choose another file...");
        $("section#step-2").addClass("disabled");
        $("#btnModalsaveInjection").attr("disabled", true);
    }

    function fileScanSuccess( response ) {

        var fileData = response.fieldData;
        var fileName = response.file;
        var fileBase64 = response.pdf64;
        var title = response.title;
        var description = response.description;

        $("#file").removeClass("is-invalid");
        $("#file").addClass("is-valid");
        $("#fpdm-success").html("Your file has been successfully processed. A total of <b>"+ fileData.length +" fields</b> has been detected.")
        $("#fpdm-error").addClass("d-none");
        $("#fileLabel").text(fileName);
        $("section#step-2").removeClass("disabled");
        $("#pdf-preview-spinner").removeClass("d-none");
        $('[name=hasFileChanged]').val(1);
        $("#btnModalsaveInjection").attr("disabled", false);
        $('[name=title]').val(title);
        $('[name=description]').val(description);

        STPH_pdfInjector.renderFields(fileData);
        STPH_pdfInjector.createThumbnail(fileBase64);

    } 

    //  Send file via ajax post & formdata
    var fd = new FormData(file);
    $.ajax({
       url: STPH_pdfInjector.requestHandlerUrl + "&action=fileScan",
       type: 'post',
       data: fd,
       contentType: false,
       processData: false,
       success: function(response){
            STPH_pdfInjector.log(response);
            fileScanSuccess(response);
       },
       error: function(error) {
            STPH_pdfInjector.log(error);
            fileScanError(error.responseText);
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

STPH_pdfInjector.createThumbnail = function(base64Data) {

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
        //console.log(vp.width, vp.height, scale);
        return page.render({ canvasContext: canvas.getContext("2d"), viewport: page.getViewport({ scale: scale }) }).promise.then(function () {
            return canvas; 
        });
    }

    var pdfData = base64ToUint8Array(base64Data);
    
    //  PDFJS Script
    pdfjsLib.getDocument(pdfData).promise.then(function (doc) {
        var pages = []; while (pages.length < 1) pages.push(pages.length + 1);
        return Promise.all(pages.map(function (num) {
            // create a div for each page and build a small canvas for it
            
            return doc.getPage(num).then(makeThumb)
            .then(function (canvas) {
                var div = document.getElementById("new-pdf-thumbnail");
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
}

/**
 * Function to preview the PDF Injection
 * @param index, the injection unique id
 */
 STPH_pdfInjector.previewInjection = function(index, injectionnumber, record_id = null, project_id = null){

    $.post(STPH_pdfInjector.requestHandlerUrl + "&action=previewInjection", 
    {
        document_id:index,
        record_id: record_id,
        project_id: project_id
    })
    .done(function(response){
        console.log(response);
        if (response.error) {
            // handle the error
            throw response.error.msg;
        }
        if( typeof response === 'string' && response.includes("FPDF-Merge Error:") ){
            alert(response);
            console.log(response);

        } else {
            $('#modal-pdf-preview').remove();
            var embed = '<object id="modal-pdf-preview" type="application/pdf" frameBorder="0" scrolling="auto" height="100%" width="100%" data="'+response.data+'">'
            $('#modal_message_preview').append(embed);
            $('#modal_message_preview').css("max-height", $(document).height() * 0.75 );
    
            $('#modalPreviewNumber').text("PDF Injector- Preview Injection #"+injectionnumber);
            $('#myModalLabelA').show();
            $('#myModalLabelB').hide();
            $('#external-modules-configure-modal-preview').modal('show'); 
        }

    })
    .fail(function(error){
        alert(error.responseText);
    })

}

STPH_pdfInjector.previewInjectionRecord = function(index, injectionnumber) {
    $('#index_modal_record_preview').val(index)
    $('#modalRecordNumber').text("- Preview Injection by record - Injection #"+injectionnumber);

    $('#external-modules-configure-modal-record').modal('show');

}

STPH_pdfInjector.initPageDataExport = function() {

    //  Observe report_load_progress2 and insert markup when ready
    STPH_pdfInjector.observeReportLoad();
}

STPH_pdfInjector.observeReportLoad = function() {

    //  Hacky Approach since there is no other way to detect end of ajax request
    //  Use Mutation Observer to include Button into Ajax loaded area
    //  Source: https://developer.mozilla.org/en-US/docs/Web/API/MutationObserver

    // Select the node that will be observed for mutations
    const targetNode = document.getElementById("report_parent_div");

    // Options for the observer (which mutations to observe)
    const config = { attributes: false, childList: true, subtree: true };

    // Callback function to execute when mutations are observed
    const callback = (mutationList, observer) => {       
        if(mutationList.length > 0) {
            if(mutationList[0].target.id == "report_parent_div") {
                STPH_pdfInjector.log("report_parent_div has been mutated.")
                STPH_pdfInjector.insertReportBtn();
            }
        }
    };

    // Create an observer instance linked to the callback function
    const observer = new MutationObserver(callback);

    // Start observing the target node for configured mutations
    observer.observe(targetNode, config);

    // We will not stop observing, because then changes in live filters or reset button won't trigger
    //observer.disconnect();
}

STPH_pdfInjector.insertReportBtn = function() {
    STPH_pdfInjector.log("Inserting PDF Injector Button.")
    //  Remove button first, otherwise we will have too many :-S
    //$("#pdfi-report-btn").remove();
    // BS5 Syntax change, using data-bs-*
    // data-bs-target="#external-modules-configure-modal-data-export" data-bs-toggle="modal" 
    let button = '<a onClick="STPH_pdfInjector.openModalExportData()" id="pdfi-report-btn" class="report_btn jqbuttonmed ui-button ui-corner-all ui-widget" style="color:#34495e;font-size:12px;"><i class="fas fa-syringe"></i> PDF Injector</a>';
    $(".report_btn").first().parent().prepend(button);
}

STPH_pdfInjector.getLiveFilters = function() {
    
    const queryString = window.location.search;    
    let searchParams  = new URLSearchParams(queryString);
    let paramsDelete = [];

    const liveFilterNames = ["lf1","lf2","lf3"];
    searchParams.forEach( ( value, key ) => {
        if(!liveFilterNames.includes(key)) {
            paramsDelete.push(key)
        }
    })

    paramsDelete.forEach((param)=>{
        searchParams.delete(param)
    })

    return searchParams
}

STPH_pdfInjector.openModalExportData = function() {
    var liveFilters = STPH_pdfInjector.getLiveFilters();
    var lifeFiltersHTML = "";
    liveFilters.forEach((value,key) => {
        lifeFiltersHTML += key + ": " + value + "<br>";
    })

    if(liveFilters.toString() !== "") {
        $('#batch-load-livefilters').html("<div><b>Live Filters</b><br>"+lifeFiltersHTML+"</div>")
    }   
    $('#external-modules-configure-modal-data-export').modal('show'); 
}

STPH_pdfInjector.closeModalExportData = function() {

    //  Reset modal
    $(".injection-report-download").addClass("d-none");
    $('#batch-load-success').addClass("d-none");
    $('#batch-load-failure').addClass("d-none");
    $('#batch-load-error-name').addClass("d-none");
    $('#batch-load-error-content').addClass("d-none");
    $("#batch-load-select").prop('disabled', '');



    $("#batch-load-select").prop('selectedIndex',0);
  
    $('#external-modules-configure-modal-data-export').modal('hide');
}

STPH_pdfInjector.setDownload = function (value) {
    $(".injection-report-download").addClass("d-none");
    $("#report-injection-download-"+value).removeClass("d-none");
}

STPH_pdfInjector.loadBatch = function (did, rid) {

    console.log("Starting batch load..")

    //  show loading
    $("#batch-load-spinner").removeClass("d-none");
    $(".injection-report-download").addClass("d-none");
    $("#batch-load-select").prop('disabled', 'disabled');

    function isJsonString(str) {
        try {
            JSON.parse(str);
        } catch (e) {
            return false;
        }
        return true;
    }    

    function handleError(xhr, status, error){

        var errorData = "";

        console.log(xhr.responseText)

        if(typeof xhr.getResponseHeader === "function" && xhr.getResponseHeader('content-type') == 'application/json' && xhr.responseText !== "" ) {
            if(isJsonString(xhr.responseText)) {
                var errorJSON = JSON.parse(xhr.responseText).error
                var msg = '<div>Message: '+errorJSON.msg+'</div>';
                var code = '<div>Code: '+errorJSON.code+'</div>';
                var track = '<p><i>Check JavaScript console log for stack trace.</i></p>'
    
                errorData = msg + code + track;
            } else {
                errorData = "<div class='red'>"+xhr.responseText+"</div>"
            }
            STPH_pdfInjector.log(error)

        } else {
            errorData = xhr;
        }

        $("#batch-load-error-name").text(error)
        $("#batch-load-error-content").html(errorData)

        $("#batch-load-spinner").addClass("d-none");
        $("#batch-load-failure").removeClass("d-none");
        $("#batch-load-error-name").removeClass("d-none");
        $("#batch-load-error-content").removeClass("d-none");
    }
    
    // Handle Live Filters
    liveFilters = STPH_pdfInjector.getLiveFilters()


    $.ajax({
        url: STPH_pdfInjector.batchLoaderUrl + "&did=" + did + "&rid=" + rid + "&" + liveFilters.toString(),
        type: 'get',
        xhr: function() {
            //  Adjust responseType based on our responseHeader
            //  https://stackoverflow.com/a/55120956/3127170
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 2) {
                    if (xhr.getResponseHeader('content-type') == 'application/zip') {
                        xhr.responseType = "blob";
                    } else {
                        console.log("xhr")
                        console.log(xhr)
                        if (xhr.status == 200) {
                            xhr.responseType = "text";
                        } else {
                            xhr.responseType = "text";
                        }
                    }
                }
            };
            return xhr;
        },
        success: function(xhr, textStatus, request){

            //var headers = request.getAllResponseHeaders();
            //console.log(headers) 

            var contentType = request.getResponseHeader('content-type')

            //  If the response is not application/zip
            //  we most probably have a REDCap Error
            if(contentType !== 'application/zip') {
                handleError(xhr, textStatus, new Error("REDCap Error"));
                return;
            }

            //  In other cases we have a zip returend as blob

            //  Get fileName
            var fileName = request.getResponseHeader('content-disposition').split('filename=')[1].split(';')[0];

            //  Download via JavaScript
            //  https://stackoverflow.com/a/42830315/3127170
            var link=document.createElement('a');
            link.href=window.URL.createObjectURL(xhr);
            link.download=fileName;
            link.click();

            $("#batch-load-spinner").addClass("d-none");
            $("#batch-load-success").removeClass("d-none");

        },
        error: handleError
     });
 
}

STPH_pdfInjector.quickfill = function () {
    var variableInputs = $(".variable-input");

    $.each(variableInputs, function( index, element ) {
        if(!element.value) {
            element.value = element.dataset.quickFillValue;
            setTimeout(STPH_pdfInjector.validateField(element.dataset.quickFillKey), 2000 );
        }      
    })
}

STPH_pdfInjector.clear = function() {
    var variableInputs = $(".variable-input");
    $.each(variableInputs, function( index, element ) {
        element.focus();
        element.value = "";
        element.blur();
    });
}
