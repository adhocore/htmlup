<?php

use Ahc\HtmlUp;

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
            array(
                'Table',
                $this->assemble('a | b', '---|---', '1 | 2', '4 | 5'),
                '<table>
<thead>
<tr>
<th>a</th>
<th>b</th>
</tr>
</thead>
<tbody>
<tr>
<td>1</td>
<td>2</td>
</tr>
<tr>
<td>4</td>
<td>5</td>
</tr>
</tbody>
</table>'
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
            array('>', '<', ),
            $markup
        );

        return trim($markup);
    }

    private function assemble()
    {
        return implode("\n", func_get_args());
    }
}
