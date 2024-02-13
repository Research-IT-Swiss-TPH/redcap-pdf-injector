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

STPH_pdfInjector.initButtons = function() {

    STPH_pdfInjector.log("PDF Injector - Adding button to field", STPH_pdfInjector);

    STPH_pdfInjector.params.tagged_fields.forEach(tagged_field => {
        console.log(tagged_field)
        STPH_pdfInjector.renderButton(tagged_field)
    });
}

STPH_pdfInjector.renderButton = function(tagged_field){
    var t = document.querySelector('#pdfi-download-button');
    var docFragment = document.importNode(t.content, true);
    var buttonWrapper = docFragment.querySelector('tr');
    console.log(buttonWrapper)

    var button = buttonWrapper.querySelector('button');
    button.id = "pdfi-download-btn-"+tagged_field.field_name;

    var dropdown = buttonWrapper.querySelector('ul');

    tagged_field.injections.forEach(injection => {        
        var href = `${STPH_pdfInjector.previewUrl}&did=${injection.document_id}&rid=${STPH_pdfInjector.params.record_id}`;
        var link = `<li><a style="text-decoration:none;" target="_blank" href="${href}" class="dropdown-item">${injection.title}</a></li>`;
        dropdown.insertAdjacentHTML('afterbegin', link)
    })


    var field = document.getElementById(tagged_field.field_name + '-tr');

    var td = field.querySelector('td');

    var fiedLabel = td.querySelector('div')

    //td.removeAttribute('colspan');
    fiedLabel.insertAdjacentElement('beforeend', buttonWrapper)
}