<?php

namespace Tests\Unit\Services\Sword;

use App\Services\Sword\ConfParser;
use PHPUnit\Framework\TestCase;

class ConfParserTest extends TestCase
{
    private ConfParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ConfParser();
    }

    public function test_parse_basic_conf(): void
    {
        $conf = <<<CONF
[KJV]
DataPath=./modules/texts/ztext/kjv/
ModDrv=zText
SourceType=OSIS
Lang=en
Description=King James Version (1769) with Strongs Numbers and Morphology and CrossReferences
About=The King James Version
Encoding=UTF-8
LCSH=Bible. English.
SwordVersionDate=2020-01-01
Version=2.9
Versification=KJV
Feature=StrongsNumbers
GlobalOptionFilter=OSISStrongs
GlobalOptionFilter=OSISMorph
GlobalOptionFilter=OSISFootnotes
GlobalOptionFilter=OSISHeadings
GlobalOptionFilter=OSISRedLetterWords
CONF;

        $result = $this->parser->parse($conf);

        $this->assertEquals('KJV', $result['module_name']);
        $this->assertArrayHasKey('config', $result);
        $this->assertEquals('zText', $result['config']['ModDrv']);
        $this->assertEquals('OSIS', $result['config']['SourceType']);
        $this->assertEquals('en', $result['config']['Lang']);
        $this->assertStringContainsString('King James', $result['config']['Description']);
    }

    public function test_parse_with_versification(): void
    {
        $conf = <<<CONF
[TestMod]
ModDrv=zText
Versification=Catholic
CONF;

        $result = $this->parser->parse($conf);
        $this->assertEquals('Catholic', $result['config']['Versification']);
    }

    public function test_extract_metadata(): void
    {
        $conf = <<<CONF
[KJV]
DataPath=./modules/texts/ztext/kjv/
ModDrv=zText
SourceType=OSIS
Lang=en
Description=King James Version with Strongs
About=The King James Version
Versification=KJV
CONF;

        $parsed = $this->parser->parse($conf);
        $meta = $this->parser->extractMetadata($parsed);

        $this->assertEquals('KJV', $meta['key']);
        $this->assertEquals('zText', $meta['mod_drv']);
        $this->assertEquals('en', $meta['language']);
        $this->assertStringContainsString('King James', $meta['description']);
    }

    public function test_parse_multiline_about(): void
    {
        $conf = <<<CONF
[TestMod]
ModDrv=rawText
About=This is a long description \\
that continues on the next line \\
and one more line.
Lang=en
CONF;

        $result = $this->parser->parse($conf);
        $this->assertStringContainsString('long description', $result['config']['About']);
    }

    public function test_parse_empty_conf(): void
    {
        $result = $this->parser->parse('');
        $this->assertIsArray($result);
    }
}
