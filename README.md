# PHP DocumentParser #
*A PHP parser for getting the text from a .doc, .docx, .rtf or .txt file*


----------


This library is perfect if you want users to be able to upload word documents to your content management system, instead of forcing them to copy and paste. Supported file types are **.doc**, **.docx**, **.txt** and **.rtf**.


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

#### April 29th 2016
**Improved .doc process**

The script to parse .doc files is unreliable: it breaks on complicated documents. I would suggest installing the antiiword command line utility as that works almost perfectly for the larger majority of documents. 
