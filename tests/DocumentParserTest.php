<?php

namespace LukeMadhanga;

class testDocumentParser extends \PHPUnit\Framework\TestCase {

    /**
     * Check that exception is thrown on invalid MIME types
     */
    public function testParseFromFileThrowsExceptionMIME() {
        try {
            \LukeMadhanga\DocumentParser::parseFromFile('tests/testFile.unknown');

            $this->expectException(\Exception::class);

        } catch (\Exception $e) {
            $this->assertIsObject($e);
            $this->assertEquals('Failed to read file: unknown mimetype: inode/x-empty', $e->getMessage());
        }
    }

    /**
     * Check that content extracted from html remains
     */
    public function testParseFromFileReadsHTML() {
        $html = \LukeMadhanga\DocumentParser::parseFromFile('tests/testFile.html');

        $this->assertIsString($html);
        $this->assertEquals('<h1>header</h1>
    <p>Test html for lukemadhanga/php-document-parser.</p>', $html);
    }

    public function testParseFromFileReadsODT() {
        $odt = \LukeMadhanga\DocumentParser::parseFromFile('tests/testFile.odt');

        $this->assertIsString($odt);
        $this->assertEquals("\n<h1>Header 1</h1><h2>Header 2</h2><p>Styled text</p><ol><li><p>ol1</p></li><li><p>ol2 </p></li></ol><ol><li><p>ul1</p></li><li><p>ul2</p></li></ol>\n", $odt);
    }

    /**
     * Check that content extracted from RTF is valid
     */
    public function testParseFromFileReadsRTF() {
        $rtf = \LukeMadhanga\DocumentParser::parseFromFile('tests/testFile.rtf', 'application/rtf');

        $this->assertIsString($rtf);
        $this->assertStringMatchesFormat('%a', $rtf);
        $this->assertEquals('Test string for lukemadhanga/php-document-parser.', $rtf);
    }

}
