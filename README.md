# PDF Injector
PDF Injector is a REDCap module that enables you to populate fillable PDFs with record data from variables. 

![alt text](/img/pdf_injector_screen_1.png "Screenshot 1")

## Setup & Configuration

Install the module from REDCap module repository and enable over Control Center. **Important** Please read Requirements and Limitations before your proceed to setup the module! After enabling the module for your project navigate to module configuration. 

### Set UI Mode
To define how your Injections should be displayed on the Record Home Page, choose the option in module configuration that suits you best.
![Screenshot Configuration UI Mode](/img/pdf_injector_screen_config.png "Screenshot Configuration UI Mode")

![alt text](/img/pdf_injector_screen_3.png "Screenshot 3")

### Set Preview Mode
You can choose in module configuration settings how the filled PDF should be previewed on the record page: either within a modal or in a new tab. Previewing in a new tab has the advantage that you can configure your browser to directly open the PDF in Adobe Reader, that enhances given browsers issues as described below. [Learn here how to configure Firefox to chose another viewer for PDFs](https://support.mozilla.org/en-US/kb/view-pdf-files-firefox-or-choose-another-viewer).

![Screenshot Configuration Preview Mode](/img/pdf_injector_screen_config_2.png "Screenshot Configuration Preview Mode")


## How to use

### Add a new Injection
You can add a new PDF Injection as follows:

![alt text](/img/pdf_injector_screen_2.png "Screenshot 2")

1. Upload you PDF
2. Add your title and description.
3. Bind variables to given form fields.

Repeat this for any other PDF file.

*Hint* If you would like to fill multiple variables into a form field, you can use CALCTEXT inside the field annotation of that field. PDF Injector can handle fields with CALCTEXT and also in combination with Smart Variables and Action Tags*.



## Requirements & Limitations
Please notice that the current module version has several limitations and pre-requirements. Ensure to test your setup before use in production.

### Fillable PDFs
This module requires **FILLABLE PDF** which means that your PDF file has to be in a specific format:
1. Form fields:
The PDF has to have form fields that can be filled (otherwise you will only output an unfilled PDF). It is recommened to use Software such as 
"Adobe Acrobat Pro" to create PDFs with form fields. [Learn here how to create fillable pdf forms](https://acrobat.adobe.com/us/en/acrobat/how-to/create-fillable-pdf-forms-creator.html).

2. Readable PDF format:
To make the PDF and its fields readable through PDF Injector we have to process the pdf with an open source tool called `pdftk`.
If you do not do this step, PDF Injector will not be able to scan your document and read the fields or fill them!

a. Use the [pdfk web service](https://pdftk-web-service.herokuapp.com/) to upload, convert and download your PDF.

b. Use [pdftk](https://www.pdflabs.com/tools/pdftk-server/) for your system and run:

```
    $ pdftk document.pdf output document_converted.pdf

```

### Known Issues with Mozilla Firefox
#### "This PDF document might not be displayed correctly."
Direct printing within Mozilla Firefox PDF Preview is currently not possible. To ensure proper displaying &  printing please download the file and open in Adobe Acrobat or use Chrome Browser.

### PDF form fields accessible
Filled form fields will still be accessible after render. This means that anybody can edit their content which is not best practise. To "close" form fields an additional step called "flattening" is necessary which currently is not supported by the module. A future is in consideration though (see Roadmap below).

### Supported PDF fields
Currently the following PDF field types are supported:
- Textfield: Insert text as one line or also as multiline (option multline for textfield has to be active)
- Checkbox: Insert a value that is true or false to tick or untick the checkbox.

### Supported Action Tags
Currently the following Action Tags are supported:
- @TODAY: returns date in format "d.m.Y"

##  Roadmap
- support PDF flattening (currently bugged within FPDM, exec pdftk directly or use of alternative libraries such as pdfcairo, external web service)
- support additional (simple) Action Tags (@NOW, etc.), improve Action tag support (e.g. formating for dates)
- support additional PDF form field types (barcodes, ~~checkboxes~~, etc.)

## Changelog

Version | Description
------- | --------------------
v1.0.0  | Initial release.
v1.0.1  | Minor Security Fix.
v1.0.2  | Cleanup & added preview mode 'new tab'.
v1.1.0  | Add form field type checkbox support.