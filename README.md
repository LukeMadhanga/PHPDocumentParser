# PHP DocumentParser #
*A PHP parser for getting the text from a .doc, .docx, .rtf or .txt file*


----------


Authors
- @facuonline
- Luke Madhanga @LukeMadhanga


----------


This library is perfect if you want users to be able to upload word documents to your content management system, instead of forcing them to copy and paste. Supported file types are **.doc**, **.docx**, **.txt** and **.rtf**.

> composer require lukemadhanga/php-document-parser

May require you to install PHP Zip

> sudo apt-get install php7.0-zip

The above `Ubuntu` command will vary depending on your version of PHP and what OS is running on your server

----------


## Methods

#### parseFromFile
*Parse a document from a file*

Arguments

string  `$filename` The path to the file to parse

string `$mimetype` The mimetype of the file. This will be used to determine which algorithm to use when decoding

**returns** string The text from the file

---

#### parseFromString
*Parse a file from a string*

Arguments

string  `$string` The contents of the file to parse

string `$mimetype` The mimetype of the file. This will be used to determine which algorithm to use when decoding

**returns** string The text in the document

---

## Change log

#### September 21 2019 (0.1.4)
**Better ODT Support**
Merged in PR#13 for better ODT support. Author: facuonline

#### August 1 2019 (0.1.3)
**PHP Unit**
Merged in PR#12 for PHP Unit testing. Author: facuonline

#### March 21 2019 (0.1.2)
**DOCX Handling**
Merged in PR#10 For better DOCX handling. Includes bug fixes for exception handling. Author: facuonline


#### September 13th 2017
**Added composer**

> composer require lukemadhanga/php-document-parser

#### April 29th 2016
**Improved .doc process**

The script to parse .doc files is unreliable: it breaks on complicated documents. I would suggest installing the `antiword` command line utility as that works almost perfectly for the larger majority of documents. 
