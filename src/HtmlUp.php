<?php 

/**
 * HtmlUp - A **lightweight** and **fast** `markdown` to HTML Parser
 *
 * Supports most of the markdown specs except deep nested elements.
 * Check readme.md for the details of its features and limitations. 
 * **Crazy Part:** it is _single class_, _single function_ library.
 *                 because hey! construct() and toString() are magics
 * 
 * @author adhocore | Jitendra Adhikari <jiten.adhikary@gmail.com>
 * @copyright (c) 2014 Jitendra Adhikari
 * 
 */
class HtmlUp
{
    private $Lines;

    private $Pointer = -1;

    public function __construct($markdown) 
    {
        $this->Lines = 
            explode("\n",   # the lines !
                trim(       # trim trailing \n
                    str_replace(array("\r\n", "\r", ), "\n", # use standard newline
                        str_replace("\t", '    ', $markdown) # use 4 spaces for tab
                    ), "\n"
                ) . "\n"    # ensure atleast one \n
            );

        unset($markdown);
    }

    public function __toString()
    {
        return $this->parse();
    }

    public function parse()
    {
        if (empty($this->Lines)) 
            return '';
        
        $markup = '';
        $nestLevel = $quoteLevel = 0;
        $indent = $nextIndent = 0;
        $stackList = $stackBlock = array();
        $lastPointer = count($this->Lines) - 1;
        
        while (isset($this->Lines[++$this->Pointer])) {
            $line = $this->Lines[$this->Pointer];
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                while($stackList) {
                    $markup .= array_pop($stackList);
                }
                while($stackBlock) {
                    $markup .= array_pop($stackBlock);
                }
                    
                $inList = $inQuote = $inPara = null;
                $nestLevel = $quoteLevel = 0; 
                continue;
            }
            
            # raw html
            if ( preg_match('/^<\/?\w.*?\/?>/', $trimmedLine) ) {
                $markup .= "\n$line";
                continue;
            }

            $nextLine = $this->Pointer < $lastPointer 
                ? $this->Lines[$this->Pointer+1]
                : null;
            $trimmedNextLine = $nextLine ? trim($nextLine) : null;

            $indent = strlen($line) - strlen(ltrim($line));;            
            $nextIndent = $nextLine ? strlen($nextLine) - strlen(ltrim($nextLine)) : 0;
            
            $nextMark1 = isset($trimmedNextLine[0]) ? $trimmedNextLine[0] : null;
            $nextMark12 = $trimmedNextLine ? substr($trimmedNextLine, 0, 2) : null;

            # blockquote
            if ( preg_match('~^\s*(>+)\s+~', $line, $quoteMatch) ){
                $line = substr($line, strlen($quoteMatch[0]));
                $trimmedLine = trim($line);
                if (empty($inQuote) OR $quoteLevel < strlen($quoteMatch[1])) {
                    $markup .= "\n<blockquote>";
                    $stackBlock[] = "\n</blockquote>";
                    $quoteLevel++;                    
                }
                $inQuote = true;
            } 
            
            $mark1 = $trimmedLine[0];
            $mark12 = substr($trimmedLine, 0, 2);
            
            // atx
            if ($mark1 === '#') {
                $level = strlen($trimmedLine) - strlen(ltrim($trimmedLine, '#'));
                $markup .= "\n<h{$level}>" . ltrim($trimmedLine, '# ') . "</h{$level}>";
                continue;    
            }

            // setext
            if ( preg_match('~^\s*(={3,}|-{3,})\s*$~', $nextLine) ) {
                $level = trim($nextLine, '- ') === '' ? '2' : '1';
                $markup .= "\n<h{$level}>{$trimmedLine}</h{$level}>";
                $this->Pointer++;
                continue;
            }

            // fence code
            if ($codeBlock = preg_match('/^```\s*([\w-]+)?/', $line, $codeMatch) 
                OR (empty($inList) AND empty($inQuote) AND $indent >= 4)
            ) {
                $lang = ($codeBlock AND isset($codeMatch[1])) 
                    ? " class=\"language-{$codeMatch[1]}\" "
                    : '';
                $markup .= "\n<pre><code{$lang}>";
                if (! $codeBlock) $markup .= htmlspecialchars(substr($line, 4));
                
                while( isset($this->Lines[$this->Pointer+1]) 
                    AND ( ($line = htmlspecialchars($this->Lines[$this->Pointer+1])) OR true )
                    AND ( ($codeBlock AND substr(ltrim($line), 0, 3) !== '```') OR substr($line, 0, 4) === '    ' )
                ) {
                    $markup .= ( "\n" ); //todo: donot use \n for first line
                    $markup .= $codeBlock ? $line : substr($line, 4);
                    $this->Pointer++;
                }
                $this->Pointer++;
                $markup .= "</code></pre>";
                continue;
            }

            // rule
            if ( isset($this->Lines[$this->Pointer-1])
                AND trim($this->Lines[$this->Pointer-1]) === ''
                AND preg_match('~^(_{3,}|\*{3,}|-{3,})$~', $trimmedLine) 
            ) {
                $markup .= "\n<hr />";
                continue;
            }

            // list
            if ( $ul = in_array($mark12, array('- ', '* ', '+ '))
                OR preg_match('/^\d+\. /', $mark12)
            ) {
                $wrapper = $ul ? 'ul' : 'ol';
                if (empty($inList)) {
                    $stackList[] = "</$wrapper>";
                    $markup .= "\n<$wrapper>\n";
                    $inList = true;
                    $nestLevel++;                    
                }

                $markup .= "<li>".ltrim($trimmedLine, '-*0123456789. ');

                if ( $ul = in_array($nextMark12, array('- ', '* ', '+ '))
                    OR preg_match('/^\d+\. /', $nextMark12)
                ) {
                    $wrapper = $ul ? 'ul' : 'ol';
                    if ($nextIndent > $indent) {
                        $stackList[] = "</li>\n";
                        $stackList[] = "</$wrapper>";
                        $markup .= "\n<$wrapper>\n";
                        $nestLevel++;                        
                    } else {
                        $markup .= "</li>\n";
                    }

                    if ($nextIndent < $indent) {
                        $shift = intval( ($indent - $nextIndent)/4 );
                        while ($shift--) {
                            $markup .= array_pop($stackList);
                            if ($nestLevel > 2) {
                                $markup .= array_pop($stackList);                            
                            }
                        }
                    }
                } else {
                    $markup .= "</li>\n";
                }

                continue;
            }

            if (isset($inList)) {
                $markup .= $trimmedLine;
                continue;
            }

            // paragraph
            if (empty($inPara)) $markup .= "\n<p>";
            else $markup .= "\n<br />";
            $markup .= "{$trimmedLine}";
            if (empty($trimmedNextLine)) {
                $markup .= "</p>";
                $inPara = null;
            } else $inPara = true;
        }

        $markup = preg_replace(
            '~<(https?:[\/]{2}[^\s]+?)>~', # urls
            '<a href="$1">$1</a>', 
            preg_replace(
                '~<(\S+?@\S+?)>~', # emails
                '<a href="mailto:$1">$1</a>', 
                $markup
            )
        );

        # images
        $markup = preg_replace_callback('~!\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~', function($img){
            $title = isset($img[3]) ? " title={$img[3]} " : '';
            $alt = $img[1] ? " alt=\"{$img[1]}\" " : '';
            return "<img src=\"{$img[2]}\"{$title}{$alt}/>";
        }, $markup);

        # anchors
        $markup = preg_replace_callback('~\[(.+?)\]\s*\((.+?)\s*(".+?")?\)~', function($a){
            $title = isset($a[3]) ? " title={$a[3]} " : '';
            return "<a href=\"{$a[2]}\"{$title}>{$a[1]}</a>";
        }, $markup);

        # em/code/strong/del
        $markup = preg_replace_callback('!(\*{1,2}|_{1,2}|`|~~)(.+?)\\1!', function($em){
            switch (true) {
                case substr($em[1], 0, 2) === '**':
                case substr($em[1], 0, 2) === '__':
                    $tag = 'strong';
                    break;                
                case substr($em[1], 0, 2) === '~~':
                    $tag = 'del';
                    break;
                case $em[1] === '*': case $em[1] === '_':
                    $tag = 'em';
                    break;
                default: 
                    $tag = 'code';
                    $em[2] = htmlspecialchars($em[2]);
            }

            return "<$tag>{$em[2]}</$tag>";
        }, $markup);

        return $markup;
    }

}
