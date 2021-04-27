# PDF Injector
PDF Injector is a REDCap module that helps you to fill your PDFs with record data.

![alt text](/img/pdf_injector_screen_1.png "Screenshot 1")

## Setup

Install the module from REDCap module repository and enable over Control Center. **Important** Please read Requirements and Limitations before your proceed to setup the module!

## Configuration

### Add a new Injection
After enabling the module for your project navigate to module configuration. You can add a new PDF Injection as follows:

![alt text](/img/pdf_injector_screen_2.png "Screenshot 2")


1. Upload you PDF
2. Add your title and description.
3. Bind variables to given form fields.

Repeat this for any other PDF file.

*Hint* If you would like to fill multiple variables into a form field, you can use CALCTEXT inside the field annotation of that field. PDF Injector can handle fields with CALCTEXT and also in combination with Smart Variables and Action Tags*.

### Set UI Mode
To define how your Injections should be displayed on the Record Home Page, choose the option in module configuration that suits you best.
![alt text](/img/pdf_injector_screen_3.png "Screenshot 3")


## Requirements & Limitations

### Fillable PDFs
This module requires **FILLABLE PDFS** which means that your PDF file has to be in a specific format:
1. Form fields:
The PDF has to have form fields that can be filled (otherwise you will only output an unfilled PDF). It is recommened to use Software such as 
"Adobe Acrobat Pro" to create PDFs with form fields. [Learn here how to create fillable pdf forms](https://acrobat.adobe.com/us/en/acrobat/how-to/create-fillable-pdf-forms-creator.html).

2. Redable PDF format:
To make the PDF and its fields readable through PDF Injector we have to process the pdf with an open source tool called `pdftk`.
If you do not do this step, PDF Injector will not be able to scan your document and read the fields or fill them! Download pdftk [here](https://www.pdflabs.com/tools/pdftk-server/) and run the following command from your command line:

```
    pdftk document.pdf output document.pdf

```

### Supported Action Tags
Currently the following Action Tags are supported:
- @TODAY

##  Roadmap
- support PDF flattening (Currently bugged with FPDM )
- support more (simple) Action Tags (@NOW, etc.)
- support more PDF form field types (barcodes, checkboxes, etc.)

## Changelog

Version | Description
------- | --------------------
v1.0.0  | Initial release.