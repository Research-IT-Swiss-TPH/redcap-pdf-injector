![logo](/img/logo_pdfi.png "PDF Injector")

# PDF Injector
PDF Injector is a REDCap module that enables you to populate fillable PDFs with record data from variables. 

[Read Documentation](https://tertek.github.io/redcap-pdf-injector/)

## Minimum Requirements
This module uses REDCap External Module Framework Version 6. Please ensure that your REDCap Version fulfills this requirement.

##  Roadmap
- Improve custom field handling (Multi Checkbox)
- Imrove Report Injection UI (Reset Live Filter)
- Imporve Injection Overview (Organizing Order, Categories)
- Add Access Rights and Levels
- Add Unit Testing

## Developer Notice
Pull Requests are welcome. When opening a Pull Request please add a new Branch.

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
v1.3.0  | New Feature: Render Injections automatically by field type. Enumerations are now supported and do not need to be retrieved manually through @CALCTEXT anymore.
v1.3.1  | Minor fix for live filters. Reset filters correctly when multiple where selected.
v1.3.2  | Fix module page checks, so that Javascript is loaded correctly. 
v1.3.3  | Support Render of field validation formats (dates).
v1.3.4  | Add quick tools to Step 2 edit modal.
v1.3.5  | Minor fixes.
v1.3.6  | Fix edoc storage options. Thx @jgardner-qha!

## Author
Ekin Tertemiz
