# PDF Injector
PDF Injector is a REDCap module that helps you to fill your PDFs with record data.

![alt text](/img/pdf_injector_screen_1.png "Screenshot 1")

## Setup

Install the module from REDCap module repository and enable over Control Center.

## Configuration

### Add a new Injection
After enabling the module for your project navigate to module configuration. You can add a new PDF Injection as follows:

![alt text](/img/pdf_injector_screen_2.png "Screenshot 2")


1. Upload you PDF
2. Scan for form fields
3. Bind your data to the according form field.

Repeat this for any other PDF file.

### Set UI Mode
To define how your Injections should be displayed on the Record Home Page, choose the option in module configuration that suits you best.
![alt text](/img/pdf_injector_screen_3.png "Screenshot 3")


## Requirements

This module requires **FILLABLE PDFS** which means that your PDF has to be in a specific format. To achieve this you need to run `pdftk`.
If you do not do this step, PDF Injector will not be able to scan your document and read the fields or fill them! Download pdftk [here](https://www.pdflabs.com/tools/pdftk-server/) and run the following command from your command line:

```
    pdftk document.pdf output document.pdf

```

##  Roadmap
- support PDF flattening (Currently bugged with FPDM )
- support more (simple) Action Tags (@NOW, etc.)
- support more PDF form field types (barcodes, checkboxes, etc.)

## Changelog

Version | Description
------- | --------------------
v1.0.0  | Initial release.