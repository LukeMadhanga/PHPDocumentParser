<?php

namespace LukeMadhanga;

class DocumentParser {

    /**
     * Parse a document from the contents of the string
     * @param type $string
     * @param type $mimetype
     * @return type
     * @throws Exception
     */
    static function parseFromString($string, $mimetype = 'text/plain') {
        if (preg_match("/^text\/*/", $mimetype)) {
            return $string;
        }
        $tmpfilename = 'temp/' . time() . sha1($string) . 'tmp';
        file_put_contents($tmpfilename, $string);
        $contents = self::parseFromFile($tmpfilename, $mimetype);
        unlink($tmpfilename);
        return $contents;
    }
    
    /**
     * Parse the a document and get the text
     * @param string $filename The name of the file to read
     * @param string $mimetype The mimetype of the file. Used to decide which algorithm to use
     * @return string The parsed document
     * @throws Exception
     */
    static function parseFromFile($filename, $mimetype = null) {
        if (!is_readable($filename)) {
            throw new Exception("Cannot read file {$filename}");
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
            throw new Exception("Unknown mimetype {$mimetype}");
        }
    }

    /**
     * Parse zipped document, i.e. .docx or .odt (http://goo.gl/usI7PF)
     * @param string $filename The path to the document
     * @param string $datafile .odt and .docx documents are just zipped folders with an XML file. This variable is the path to the main
     *  xml file which holds the text for the document
     * @return string
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
                $xmldom = DOMDocument::loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                $content = $xmldom->saveXML();
            }
            $zip->close();
        } else {
            throw new Exception('Could not read document');
        }
        return strip_tags(str_replace(['</w:r></w:p></w:tc><w:tc>', '</w:r></w:p>'], [' ', "\r\n"], $content));
    }

    /**
     * Parse a .doc file (http://goo.gl/Wm29Aj)
     * @param string $filename The path to the word document
     * @return string The parsed document
     */
    private static function parseDoc($filename) {
        echo file_get_contents($filename);exit;
        $contents = mb_convert_encoding(file_get_contents($filename), 'utf8');
        $lines = mb_split("\r", $contents);
        $outtext = "";
        foreach ($lines as $thisline) {
            // 0x00 is the null value
            if (strpos($thisline, chr(0x00)) === false && strlen($thisline) !== 0) {
                $outtext .= "{$thisline}\n\n";
            }
        }
        return preg_replace("/[^a-zA-Z0-9\s\,\.\-\n\r\t@\/\_\(\)]/", "", $outtext);
    }

    /**
     * Determine whether a line in a .rtf string is plain text (http://goo.gl/yVojUP)
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
     * Parse a .rtf file (http://goo.gl/yVojUP).
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
