<?php

namespace Ahc\HtmlUp\Test;

use Ahc\HtmlUp;
use PHPUnit\Framework\TestCase;

class HtmlUpTest extends TestCase
{
    /**
     * @dataProvider dataSrc
     */
    public function testAllWith4spaces($testName, $markdown, $expected)
    {
        $actualMarkup = (string) new HtmlUp($markdown, 4);

        $this->assertion($testName, $expected, $actualMarkup);
    }

    /**
     * @dataProvider dataSrc
     */
    public function testAllWith2spaces($testName, $markdown, $expected)
    {
        $actualMarkup = (string) new HtmlUp(str_replace('    ', '  ', $markdown), 2);

        $this->assertion($testName, $expected, $actualMarkup);
    }

    public function testParseWithArg()
    {
        $htmlup = new HtmlUp('paragraph');

        $this->assertion(
            'Parse constructor injected markdown',
            '<p>paragraph</p>',
            (string) $htmlup
        );

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
                '',
            ],
            [
                'Raw html',
                '<div><span>this is html already</div>',
                '<div><span>this is html already</div>',
            ],
            [
                'Quotes',
                "> Here goes a quote\n\n> And another one",
                '<blockquote><p>Here goes a quote</p></blockquote>' .
                '<blockquote><p>And another one</p></blockquote>',
            ],
            [
                'Nested Quotes',
                "> Main quote\n>> And nested one",
                '<blockquote><p>Main quote' .
                    '<blockquote><br />And nested one</p></blockquote>' .
                '</blockquote>',
            ],
            [
                'Setext',
                "Header2\n---\nHeader1\n===",
                '<h2>Header2</h2><h1>Header1</h1>',
            ],
            [
                'Atx Header',
                "# HelloH1\n## HelloH2",
                '<h1>HelloH1</h1><h2>HelloH2</h2>',
            ],
            [
                'Codes',
                "```php\n\necho 'HtmlUp rocks';",
                '<pre><code class="language-php">echo \'HtmlUp rocks\';</code></pre>',
            ],
            [
                'Code with indent',
                '    <?php phpinfo();',
                '<pre><code>&lt;?php phpinfo();</code></pre>',
            ],
            [
                'Unordered List',
                "- Hello\n* HelloAgain\n    + DeepHello\n    - Deeper\n        - Deepest" .
                    "\n    - Undeep\n* OutAgain",
                '<ul>' .
                    '<li>Hello</li>' .
                    '<li>HelloAgain' .
                        '<ul>' .
                            '<li>DeepHello</li>' .
                            '<li>Deeper' .
                                '<ul><li>Deepest</li></ul>' .
                            '</li>' .
                            '<li>Undeep</li>' .
                        '</ul>' .
                    '</li>' .
                    '<li>OutAgain</li>' .
                '</ul>',
            ],
            [
                'Ordered List',
                "1. Hello\n2. HelloAgain",
                '<ol><li>Hello</li><li>HelloAgain</li></ol>',
            ],
            [
                'H8 is Paragraph',
                '######## NoHeader',
                '<p>######## NoHeader</p>',
            ],
            [
                'Horizontal Rule',
                "***\n\n___",
                '<hr /><hr />',
            ],
            [
                'Table',
                "a | b\n---|---\n1 | 2 | 3\n4 | 5",
                '<table><thead><tr><th>a</th><th>b</th></tr></thead>' .
                    '<tbody>' .
                        '<tr><td>1</td><td>2</td></tr>' .
                        '<tr><td>4</td><td>5</td></tr>' .
                    '</tbody>' .
                '</table>',
            ],
            [
                'Font faces',
                '**Bold** _em_ `code` __strong__ ~~strike~~',
                '<p><strong>Bold</strong> <em>em</em> <code>code</code>' .
                ' <strong>strong</strong> <del>strike</del></p>',
            ],
            [
                'Image',
                '[![alt](http://imageurl)](https://contenturl)',
                '<p><a href="https://contenturl"><img src="http://imageurl" alt="alt" /></a></p>',
            ],
            [
                'URLs',
                '[label](https://link) <http://anotherlink> <mail@localhost>',
                '<p><a href="https://link">label</a>' .
                    ' <a href="http://anotherlink">http://anotherlink</a>' .
                    ' <a href="mailto:mail@localhost">mail@localhost</a></p>',
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
