## Learn using identifiable object and grid hooks as well as combining CQRS pattern from module

### Requirements

 1. Composer, see [Composer](https://getcomposer.org/) to learn more
 
### How to install

 1. Download or clone module into `modules` directory of your PrestaShop installation
 2. Rename the directory to make sure that module directory is named `ps_democqrshooksusage`*
 3. `cd` into module's directory and run following commands:
	 - `composer dumpautoload` to generate autoloader for module
 4. Install module from Back Office

*Because the name of the directory and the name of the main module file must match.

### What it does

This module adds a new field to Customer: a yes/no field "is allowed to review".
This new field appears:
- in the Customers listing as a new column
- in the Customers Add/Edit form as a new field you can manage

This modules shows how to add this field, manage its content and its
properties using only hooks
