# PDF Injector
PDF Injector is a REDCap module that helps you to fill your PDFs with record data.

## Setup

Install the module from REDCap module repository and enable over Control Center.

## Configuration

After enabling the module for your project navigate to module configuration. You can add a new PDF Injection as follows:

1. Upload you PDF
2. Scan for form fields
3. Bind your data to the according form field.

Repeat this for any other PDF file.

## Requirements

This module requires **FILLABLE PDFS** which means that your PDF has to be in a specific format. To achieve this you need to run `pdftk`:

```
    pdftk document.pdf output document.pdf

```

You can get pdftk [here](https://www.pdflabs.com/tools/pdftk-server/).

##  Roadmap
- support PDF flattening (Currenlty bugged with FPDM )
- support more (simple) Action Tags (@NOW, etc.)
- support more PDF form field types (barcodes, checkboxes, etc.)

## Changelog

Version | Description
------- | --------------------
v1.0.0  | Initial release.