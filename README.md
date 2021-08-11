![logo](/img/logo_pdfi.png "PDF Injector")

# PDF Injector
PDF Injector is a REDCap module that enables you to populate fillable PDFs with record data from variables. 

[Read Documentation](https://tertek.github.io/redcap-pdf-injector/)

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
v1.1.1  | Minor Fix.
v1.2.0  | New Feature: Reports Injection
v1.2.1  | Security Fix & UI Improvements
v1.2.2  | Security Fix (Psalm errors)
v1.2.3  | Security Fix (Psalm errors)
v1.2.4  | Compatibility fix for older REDCap versions
v1.2.5  | Support variable primary keys.
v1.2.6  | Support live filters for Reports Injection. Add setting to disable Readme Generation.
v1.2.7  | Minor fix for live filters. Improve Mutation Observer script.
v1.2.8  | Fix rendering of '@' without action tags.

## Author
Ekin Tertemiz