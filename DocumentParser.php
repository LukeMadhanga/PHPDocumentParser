<?php

namespace LukeMadhanga;

class DocumentParser {

    /**
     * Parse a document from the contents of the string
     * @param mixed $string The contents of the file to process
     * @param string $mimetype [optional] The mimetype of the file. Defaults to text/plain
     * @return type
     * @throws Exception
     */
    static function parseFromString($string, $mimetype = 'text/plain') {
        if (preg_match("/^text\/*/", $mimetype)) {
            return $string;
        }
        $tmpfilename = 'temp/' . time() . sha1($string) . '.tmp';
        cLib::filePutContents($tmpfilename, $string);
        $contents = self::parseFromFile($tmpfilename, $mimetype);
        cLib::unlink($tmpfilename);
        return $contents;
    }
    
    /**
     * Parse the a document and get the text
     * @param string $filename The name of the file to read
     * @param string $mimetype The mimetype of the file. Used to decide which algorithm to use
     * @return string|html The extracted document. For DOC(X) and ODT documents, the content is returned in a HTML format
     * @throws Exception
     */
    static function parseFromFile($filename, $mimetype = null) {
        if (!is_readable($filename)) {
            throw new Exception(sprintf('Failed to read file: cannot read file %s', $filename));
        }
        if (!$mimetype) {
            $mimetype = mime_content_type($filename);
        }
        if (preg_match("/^text\/*/", $mimetype)) {
            return file_get_contents($filename);
        } else if ($mimetype === 'application/rtf') {
            return self::parseRtf($filename);
        } else if ($mimetype === 'application/msword') {
            return self::parseDoc($filename);
        } else if ($mimetype === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return self::parseZipped($filename, 'word/document.xml');
        } else if ($mimetype === 'application/vnd.oasis.opendocument.text') {
            return self::parseZipped($filename, 'content.xml');
        } else {
            throw new Exception(sprintf('Failed to read file: unknown mimetype %s', $mimetype));
        }
    }

    /**
     * Parse zipped document, i.e. .docx or .odt (adapted from http://goo.gl/usI7PF)
     * @param string $filename The path to the document
     * @param string $datafile .odt and .docx documents are just zipped folders with an XML file. This variable is the path to the main
     *  xml file which holds the text for the document
     * @return html
     * @throws Exception
     */
    private static function parseZipped($filename, $datafile) {
        // Zip function requires a read from a file
        $zip = new ZipArchive;
        $content = '';
        if ($zip->open($filename)) {
            // If done, search for the data file in the archive
            if (($index = $zip->locateName($datafile)) !== false) {
                // If the data file can be found, read it to the string
                $data = $zip->getFromIndex($index);
                $xmldoc = new DOMDocument();
                $xmldoc->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                if ($datafile === 'word/document.xml') {
                    // Perform docx specific processing
                    self::convertWordToHtml($xmldoc);
                } else {
                    self::convertOdtToHtml($xmldoc);
                }
                $content = $xmldoc->saveXML();
            }
            $zip->close();
        } else {
            throw new Exception(sprintf('Failed to read file'));
        }
        return strip_tags($content, '<p><em><strong>');
    }
    
    /**
     * Convert a DOCX XMLDocument to html
     * @param DOMDocument $xmldoc The xml document describing the word file
     */
    private static function convertWordToHtml(DOMDocument $xmldoc) {
        // To make processing easier, remove the 'w' namespace from all elements
        self::removeDomNamespace($xmldoc, 'w');
        $xpath = new DOMXPath($xmldoc);
        // Get all 'r' elements
        foreach ($xpath->query("//r") as $node) {
            /*@var $node DOMElement*/
            /*@var $stylenodes DOMNodeList*/
            /*@var $textnode DOMNodeList*/
            // The rPr tag defines style elements 
            $stylenodes = $node->getElementsByTagName('rPr');
            $textnode = $node->getElementsByTagName('t')->item(0);
            if ($stylenodes && $stylenodes->length) {
                $stylenode = $stylenodes->item(0);
                $itags = $stylenode->getElementsByTagName('i');
                $btags = $stylenode->getElementsByTagName('b');
                if ($itags->length) {
                    self::renameTag($textnode, 'em');
                } else if ($btags->length) {
                    self::renameTag($textnode, 'strong');
                }
                $toremove = array_merge(iterator_to_array($itags), iterator_to_array($btags));
                foreach ($toremove as $nodetoremove) {
                    $nodetoremove->parentNode->removeChild($nodetoremove);
                }
            }
        }
        self::removeEmptyTag($xmldoc, 'p');
    }
    
    /**
     * Convert an ODT XMLDocument to html
     * @param DOMDocument $xmldoc The XML document to manipulate
     */
    private static function convertOdtToHtml(DOMDocument $xmldoc) {
        self::removeDomNamespace($xmldoc, 'office');
        self::removeDomNamespace($xmldoc, 'style');
        self::removeDomNamespace($xmldoc, 'text');
        $xpath = new DOMXPath($xmldoc);
        // Cannot select using XPath attributes for some reason, cannot seem to access 'style-name' attr
        $spans = $xpath->query("//body/text/p/span");
        foreach ($spans as $span) {
            /*@var $span DOMElement*/
            if (!$span->attributes->length) {
                continue;
            }
            $attributes = iterator_to_array($span->attributes);
            if (isset($attributes['style-name'])) {
                /*@var $attr DOMAttr*/
                $attr = $attributes['style-name'];
                if ($attr->value === 'T3') {
                    self::renameTag($span, 'em');
                } else if ($attr->value === 'T4') {
                    self::renameTag($span, 'strong');
                }
            }
        }
        self::removeEmptyTag($xmldoc, 'p');
    }
    
    /**
     * Remove empty tags from a document
     * @param DOMDocument $xmldoc The document to look in
     * @param string $tagname The name of the tag to test for emptiness
     */
    private static function removeEmptyTag(DOMDocument $xmldoc, $tagname) {
        $xpath = new DOMXPath($xmldoc);
        foreach ($xpath->query("//$tagname") as $node) {
            /*@var $node DOMElement*/
            if ($node->textContent === '') {
                // Remove empty p tags
                $node->parentNode->removeChild($node);
            }
        }
    }
    
    /**
     * Remove the namespace from an XML document (adapted from http://goo.gl/RBlUPU)
     * @param DOMDocument $xmldoc The document to process
     * @param string $namespace The namespace to remove
     */
    private static function removeDomNamespace(DOMDocument $xmldoc, $namespace) {
        $xpath = new DOMXPath($xmldoc);
        $nodes = $xpath->query("//*[namespace::{$namespace} and not(../namespace::{$namespace})]");
        foreach ($nodes as $n) {
            $namespaceuri = $n->lookupNamespaceURI($namespace);
            $n->removeAttributeNS($namespaceuri, $namespace);
        }
    }
    
    /**
     * Rename a DOMElement (i.e. rename a tag, e.g. i -> em). (adapted from http://goo.gl/Crll0b)
     * @param DOMElement $tag The tag to rename
     * @param string $newtagname The name of the new tag
     * @return DOMElement
     */
    private static function renameTag(DOMElement $tag, $newtagname) {
        $document = $tag->ownerDocument;
        $newtag = $document->createElement($newtagname);
        $tag->parentNode->replaceChild($newtag, $tag);
        foreach (iterator_to_array($tag->childNodes) as $child) {
            $newtag->appendChild($tag->removeChild($child));
        }
        return $newtag;
    }

    /**
     * Parse a .doc file (adapted from http://goo.gl/Wm29Aj)
     * @param string $filename The path to the word document
     * @return html
     */
    private static function parseDoc($filename) {
        $contents = mb_convert_encoding(file_get_contents($filename), 'utf8', 'binary');
        $lines = mb_split("\r", $contents);
        $outtext = "";
        foreach ($lines as $thisline) {
            // 0x00 is the null value
            if (strpos($thisline, chr(0x00)) === false && strlen($thisline) !== 0) {
                $outtext .= "<p>{$thisline}</p>";
            }
        }
        return $outtext;
    }

    /**
     * Determine whether a line in a .rtf string is plain text (adapted from http://goo.gl/yVojUP)
     * @param string $string The string to parse
     * @return boolean True if the rtf string is plain text
     */
    private static function rtfIsPlainText($string) {
        $arrfailat = ["*", "fonttbl", "colortbl", "datastore", "themedata"];
        for ($i = 0; $i < count($arrfailat); $i++) {
            if (!empty($string[$arrfailat[$i]])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Parse a .rtf file (adapted from http://goo.gl/yVojUP).
     * @todo Parse properly. Returning a peculiar result.
     * @param string $filename The path to the .rtf file
     * @return string The plain text .rtf contents
     */
    private static function parseRtf($filename) {
        // Read the data from the input file.
        $text = file_get_contents($filename);
        if (!strlen($text)) {
            return "";
        }
        // Create empty stack array.
        $document = "";
        $stack = [];
        $j = -1;
        // Read the data character-by- character…
        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $c = $text[$i];
            $isplaintext = isset($stack[$j]) ? self::rtfIsPlainText($stack[$j]) : false;
            // Depending on current character select the further actions.
            switch ($c) {
                // the most important key word backslash
                case "\\":
                    // read next character
                    $nc = $text[$i + 1];

                    // If it is another backslash or nonbreaking space or hyphen,
                    // then the character is plain text and add it to the output stream.
                    if ($nc == '\\' && $isplaintext) {
                        $document .= '\\';
                    } else if ($nc == '~' && $isplaintext) {
                        $document .= ' ';
                    } else if ($nc == '_' && $isplaintext) {
                        $document .= '-';
                    } else if ($nc == '*') {
                        // If it is an asterisk mark, add it to the stack.
                        $stack[$j]["*"] = true;
                    }
                    // If it is a single quote, read next two characters that are the hexadecimal notation
                    // of a character we should add to the output stream.
                    elseif ($nc == "'") {
                        $hex = substr($text, $i + 2, 2);
                        if ($isplaintext) {
                            $document .= html_entity_decode("&#" . hexdec($hex) . ";");
                        }
                        //Shift the pointer.
                        $i += 2;
                        // Since, we’ve found the alphabetic character, the next characters are control word
                        // and, possibly, some digit parameter.
                    } elseif ($nc >= 'a' && $nc <= 'z' || $nc >= 'A' && $nc <= 'Z') {
                        $word = "";
                        $param = null;

                        // Start reading characters after the backslash.
                        for ($k = $i + 1, $m = 0; $k < strlen($text); $k++, $m++) {
                            $nc = $text[$k];
                            // If the current character is a letter and there were no digits before it,
                            // then we’re still reading the control word. If there were digits, we should stop
                            // since we reach the end of the control word.
                            if ($nc >= 'a' && $nc <= 'z' || $nc >= 'A' && $nc <= 'Z') {
                                if (empty($param)) {
                                    $word .= $nc;
                                } else {
                                    break;
                                }
                                // If it is a digit, store the parameter.
                            } else if ($nc >= '0' && $nc <= '9') {
                                $param .= $nc;
                            }
                            // Since minus sign may occur only before a digit parameter, check whether
                            // $param is empty. Otherwise, we reach the end of the control word.
                            elseif ($nc == '-') {
                                if (empty($param)) {
                                    $param .= $nc;
                                } else {
                                    break;
                                }
                            } else {
                                break;
                            }
                        }
                        // Shift the pointer on the number of read characters.
                        $i += $m - 1;

                        // Start analyzing what we’ve read. We are interested mostly in control words.
                        $totext = "";
                        switch (strtolower($word)) {
                            // If the control word is "u", then its parameter is the decimal notation of the
                            // Unicode character that should be added to the output stream.
                            // We need to check whether the stack contains \ucN control word. If it does,
                            // we should remove the N characters from the output stream.
                            case "u":
                                $totext .= html_entity_decode("&#x" . dechex($param) . ";");
                                $ucdelata = @$stack[$j]["uc"];
                                if ($ucdelata > 0) {
                                    $i += $ucdelata;
                                }
                                break;
                            // Select line feeds, spaces and tabs.
                            case "par": case "page": case "column": case "line": case "lbr":
                                $totext .= "\n";
                                break;
                            case "emspace": case "enspace": case "qmspace":
                                $totext .= " ";
                                break;
                            case "tab": $totext .= "\t";
                                break;
                            // Add current date and time instead of corresponding labels.
                            case "chdate": $totext .= date("m.d.Y");
                                break;
                            case "chdpl": $totext .= date("l, j F Y");
                                break;
                            case "chdpa": $totext .= date("D, j M Y");
                                break;
                            case "chtime": $totext .= date("H:i:s");
                                break;
                            // Replace some reserved characters to their html analogs.
                            case "emdash": $totext .= html_entity_decode("&mdash;");
                                break;
                            case "endash": $totext .= html_entity_decode("&ndash;");
                                break;
                            case "bullet": $totext .= html_entity_decode("&#149;");
                                break;
                            case "lquote": $totext .= html_entity_decode("&lsquo;");
                                break;
                            case "rquote": $totext .= html_entity_decode("&rsquo;");
                                break;
                            case "ldblquote": $totext .= html_entity_decode("&laquo;");
                                break;
                            case "rdblquote": $totext .= html_entity_decode("&raquo;");
                                break;
                            // Add all other to the control words stack. If a control word
                            // does not include parameters, set &param to true.
                            default:
                                $stack[$j][strtolower($word)] = empty($param) ? true : $param;
                                break;
                        }
                        // Add data to the output stream if required.
                        if ($isplaintext) {
                            $document .= $totext;
                        }
                    }
                    $i++;
                    break;
                // If we read the opening brace {, then new subgroup starts and we add
                // new array stack element and write the data from previous stack element to it.
                case "{":
                    if ($stack) {
                        array_push($stack, $stack[$j++]);
                    }
                    break;
                // If we read the closing brace }, then we reach the end of subgroup and should remove 
                // the last stack element.
                case "}":
                    array_pop($stack);
                    $j--;
                    break;
                // Skip “trash”.
                case '\0':
                case '\r':
                case '\f':
                case '\n':
                    break;
                // Add other data to the output stream if required.
                default:
                    if ($isplaintext) {
                        $document .= $c;
                    }
                    break;
            }
        }
        // Return result.
        return $document;
    }

}
