<?php

class HtmlUpTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataSrc
     *
     */
    public function testAll()
    {
        list($testName, $markdown, $expectedMarkup) = func_get_args();
        $actualMarkup = (string) new HtmlUp($markdown);

        $this->assertion($testName, $expectedMarkup, $actualMarkup);
    }

    /**
     * Prototype of test data:
     * <code>
     *   array(
     *      'test case name',
     *      'markdown text',
     *      'expected markup text'
     *   )
     * </code>
     */
    public function dataSrc()
    {
        return array(
            array(
                'Atx Header',
                $this->assemble('# HelloH1', '## HelloH2'),
                '<h1>HelloH1</h1><h2>HelloH2</h2>'
            ),
            array(
                'Unordered List',
                $this->assemble('- Hello', '* HelloAgain'),
                '<ul><li>Hello</li><li>HelloAgain</li></ul>'
            ),
            array(
                'Ordered List',
                $this->assemble('1. Hello', '2. HelloAgain'),
                '<ol><li>Hello</li><li>HelloAgain</li></ol>'
            ),
            array(
                'H8 is Paragraph',
                '######## NoHeader',
                '<p>######## NoHeader</p>'
            ),
            array(
                'Horizontal Rule',
                $this->assemble('', '***', '', '___'),
                '<hr /><hr />'
            ),
        );
    }

    protected function assertion($testName, $expected, $actual)
    {
        $this->assertEquals(
            $this->normalizeMarkup($expected), 
            $this->normalizeMarkup($actual), 
            $testName
        );
    }

    protected function normalizeMarkup($markup)
    {
        $markup = preg_replace(
            array('/\>[^\S ]+/s', '/[^\S ]+\</s', ), 
            array('>'           , '<'           , ),
            $markup
        );

        return trim($markup);
    }

    private function assemble()
    {
        return implode("\n", func_get_args());
    }
}
