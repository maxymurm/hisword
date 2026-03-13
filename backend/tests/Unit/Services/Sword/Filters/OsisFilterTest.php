<?php

namespace Tests\Unit\Services\Sword\Filters;

use App\Services\Sword\Filters\OsisFilter;
use PHPUnit\Framework\TestCase;

class OsisFilterTest extends TestCase
{
    private OsisFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new OsisFilter();
    }

    public function test_name(): void
    {
        $this->assertEquals('OSIS', $this->filter->getName());
    }

    public function test_to_html_with_strongs(): void
    {
        $osis = '<w lemma="strong:G2316" morph="robinson:N-NSM">God</w>';
        $html = $this->filter->toHtml($osis, ['strongs' => true]);

        $this->assertStringContainsString('data-strongs="G2316"', $html);
        $this->assertStringContainsString('has-strongs', $html);
        $this->assertStringContainsString('God', $html);
    }

    public function test_to_html_strongs_link_when_enabled(): void
    {
        $osis = '<w lemma="strong:H1254">created</w>';
        $html = $this->filter->toHtml($osis, ['strongs' => true]);

        $this->assertStringContainsString('strongs-link', $html);
        $this->assertStringContainsString('H1254', $html);
    }

    public function test_to_html_no_strongs_link_when_disabled(): void
    {
        $osis = '<w lemma="strong:H1254">created</w>';
        $html = $this->filter->toHtml($osis, ['strongs' => false]);

        $this->assertStringNotContainsString('strongs-link', $html);
    }

    public function test_to_html_divine_name(): void
    {
        $osis = '<divineName>LORD</divineName>';
        $html = $this->filter->toHtml($osis);

        $this->assertStringContainsString('divine-name', $html);
        $this->assertStringContainsString('LORD', $html);
    }

    public function test_to_html_red_letter_paired(): void
    {
        $osis = '<q who="Jesus">For God so loved the world</q>';
        $html = $this->filter->toHtml($osis, ['redLetters' => true]);

        $this->assertStringContainsString('red-letter', $html);
        $this->assertStringContainsString('For God so loved', $html);
    }

    public function test_to_html_red_letter_with_marker_attr(): void
    {
        $osis = '<q marker="" who="Jesus"><w lemma="strong:G2316">God</w></q>';
        $html = $this->filter->toHtml($osis, ['redLetters' => true, 'strongs' => true]);

        $this->assertStringContainsString('red-letter', $html);
        $this->assertStringContainsString('data-strongs="G2316"', $html);
    }

    public function test_to_html_trans_change_italics(): void
    {
        $osis = '<transChange type="added">the</transChange>';
        $html = $this->filter->toHtml($osis);

        $this->assertStringContainsString('added-word', $html);
        $this->assertStringContainsString('<em', $html);
    }

    public function test_to_html_milestone_paragraph(): void
    {
        $osis = 'text<milestone type="x-p" />more';
        $html = $this->filter->toHtml($osis);

        $this->assertStringContainsString('paragraph-break', $html);
    }

    public function test_to_html_poetry(): void
    {
        $osis = '<lg><l level="1">The Lord is my shepherd</l></lg>';
        $html = $this->filter->toHtml($osis);

        $this->assertStringContainsString('poetry', $html);
        $this->assertStringContainsString('indent-1', $html);
    }

    public function test_to_html_footnote(): void
    {
        $osis = 'text<note type="x-footnote">Some note</note>';
        $html = $this->filter->toHtml($osis, ['footnotes' => true]);

        $this->assertStringContainsString('footnote', $html);
    }

    public function test_to_html_cross_reference(): void
    {
        $osis = 'text<note type="crossReference">Gen 1:1</note>';
        $html = $this->filter->toHtml($osis, ['crossRefs' => true]);

        $this->assertStringContainsString('cross-ref', $html);
    }

    public function test_to_plain_text(): void
    {
        $osis = '<w lemma="strong:H7225">In the beginning</w> <w lemma="strong:H430">God</w>';
        $plain = $this->filter->toPlainText($osis);

        $this->assertEquals('In the beginning God', $plain);
    }

    public function test_self_closing_w_tags_removed(): void
    {
        $osis = '<w lemma="strong:G3588" morph="robinson:T-NSM" src="17"/><w lemma="strong:G1063">For</w>';
        $html = $this->filter->toHtml($osis, ['strongs' => true]);

        $this->assertStringNotContainsString('<w ', $html);
        $this->assertStringContainsString('For', $html);
    }

    public function test_extract_strongs(): void
    {
        $osis = '<w lemma="strong:G2316" morph="robinson:N-NSM">God</w> <w lemma="strong:G25">loved</w>';
        $result = $this->filter->extractStrongs($osis);

        $this->assertCount(2, $result);
        $this->assertEquals('God', $result[0]['word']);
        $this->assertEquals(['G2316'], $result[0]['strongs']);
        $this->assertEquals('robinson:N-NSM', $result[0]['morph']);
        $this->assertEquals('loved', $result[1]['word']);
        $this->assertEquals(['G25'], $result[1]['strongs']);
        $this->assertNull($result[1]['morph']);
    }

    public function test_extract_strongs_multiple_numbers(): void
    {
        $osis = '<w lemma="strong:G3588 strong:G2316">the God</w>';
        $result = $this->filter->extractStrongs($osis);

        $this->assertCount(1, $result);
        $this->assertEquals(['G3588', 'G2316'], $result[0]['strongs']);
    }

    public function test_extract_strongs_no_strongs_returns_empty(): void
    {
        $osis = '<w>word</w>';
        $result = $this->filter->extractStrongs($osis);

        $this->assertCount(0, $result);
    }

    public function test_title_psalm_heading(): void
    {
        $osis = '<title type="psalm">A Psalm of David</title>';
        $html = $this->filter->toHtml($osis, ['headings' => true]);

        $this->assertStringContainsString('psalm-title', $html);
        $this->assertStringContainsString('A Psalm of David', $html);
    }

    public function test_scripture_reference(): void
    {
        $osis = '<reference osisRef="Gen.1.1">Genesis 1:1</reference>';
        $html = $this->filter->toHtml($osis);

        $this->assertStringContainsString('scripture-ref', $html);
        $this->assertStringContainsString('data-ref="Gen.1.1"', $html);
    }
}
