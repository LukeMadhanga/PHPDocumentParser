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
        $this->assertEquals('<!DOCTYPE html>', $html);
    }

    /**
     * Check that content extracted from RTF is valid
     */
    public function testParseFromFileReadsRTF() {
        $rtf = \LukeMadhanga\DocumentParser::parseFromFile('tests/testFile.rtf', 'application/rtf');

        $this->assertIsString($rtf);
        $this->assertStringMatchesFormat('%a', $rtf);
    }

}
