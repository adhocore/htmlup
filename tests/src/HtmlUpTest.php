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
'# HelloH1
## HelloH2',
                '<h1>HelloH1</h1><h2>HelloH2</h2>'
            ),
            array(
                'Unordered List',
'- Hello
* HelloAgain',
                '<ul><li>Hello</li><li>HelloAgain</li></ul>'
            ),
            array(
                'H8 is Paragraph',
                '######## NoHeader',
                '<p>######## NoHeader</p>'
            ),
            array(
                'Horizontal Rule',
'

***

___
',
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
}
