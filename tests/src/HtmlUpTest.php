<?php

use Ahc\HtmlUp;

class HtmlUpTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataSrc
     */
    public function testAll()
    {
        list($testName, $markdown, $expectedMarkup) = func_get_args();
        $actualMarkup                               = (string) new HtmlUp($markdown);

        $this->assertion($testName, $expectedMarkup, $actualMarkup);
    }

    public function testParseWithArg()
    {
        $htmlup = new HtmlUp;

        $this->assertion(
            'Parse the markdown provided in runtime',
            '<p><code>abc</code></p>',
            $htmlup->parse('`abc`')
        );
    }

    /**
     * Prototype of test data:
     * <code>
     *   array(
     *      'test case name',
     *      'markdown text',
     *      'expected markup text'
     *   )
     * </code>.
     */
    public function dataSrc()
    {
        return [
            [
                'Empty',
                '',
                ''
            ],
            [
                'Raw html',
                '<div><span>this is html already</div>',
                '<div><span>this is html already</div>'
            ],
            [
                'Quotes',
                "> Here goes a quote\n\n> And another one",
                '<blockquote><p>Here goes a quote</p></blockquote>' .
                '<blockquote><p>And another one</p></blockquote>'
            ],
            [
                'Nested Quotes',
                "> Main quote\n>> And nested one",
                '<blockquote><p>Main quote' .
                    '<blockquote><br />And nested one</p></blockquote>' .
                '</blockquote>'
            ],
            [
                'Setext',
                "Header2\n---\nHeader1\n===",
                '<h2>Header2</h2><h1>Header1</h1>'
            ],
            [
                'Atx Header',
                $this->assemble('# HelloH1', '## HelloH2'),
                '<h1>HelloH1</h1><h2>HelloH2</h2>',
            ],
            [
                'Codes',
                "```php\n\necho 'HtmlUp rocks';",
                '<pre><code class="language-php">echo \'HtmlUp rocks\';</code></pre>'
            ],
            [
                'Unordered List',
                $this->assemble('- Hello', '* HelloAgain', '    + DeepHello', '    - DeepHelloAgain'),
                '<ul><li>Hello</li><li>HelloAgain' .
                    '<ul><li>DeepHello</li><li>DeepHelloAgain</li></ul>' .
                '</li></ul>',
            ],
            [
                'Ordered List',
                $this->assemble('1. Hello', '2. HelloAgain'),
                '<ol><li>Hello</li><li>HelloAgain</li></ol>',
            ],
            [
                'H8 is Paragraph',
                '######## NoHeader',
                '<p>######## NoHeader</p>',
            ],
            [
                'Horizontal Rule',
                $this->assemble('', '***', '', '___'),
                '<hr /><hr />',
            ],
            [
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
</table>',
            ],
        ];
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
            ['/\>[^\S ]+/s', '/[^\S ]+\</s'],
            ['>', '<'],
            $markup
        );

        return trim($markup);
    }

    private function assemble()
    {
        return implode("\n", func_get_args());
    }
}
