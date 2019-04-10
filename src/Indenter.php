<?php
namespace Gajus\Dindent;

/**
 * @link https://github.com/gajus/dindent for the canonical source repository
 * @license https://github.com/gajus/dindent/blob/master/LICENSE BSD 3-Clause
 */
class Indenter {
    private
        $log = array(),
        $options = array(
            'indentation_character' => '    '
        ),
        $inline_elements = array('b', 'big', 'i', 'small', 'tt', 'abbr', 'acronym', 'cite', 'code', 'dfn', 'em', 'kbd', 'strong', 'samp', 'var', 'a', 'bdo', 'br', 'img', 'span', 'sub', 'sup'),
        $ignore_elements = array('script','pre','textarea'),
        $ignore_blade_elements = array('php'),
        $temporary_replacements_ignore = array(),
        $temporary_replacements_inline = array();

    const ELEMENT_TYPE_BLOCK = 0;
    const ELEMENT_TYPE_INLINE = 1;

    const MATCH_INDENT_NO = 0;
    const MATCH_INDENT_DECREASE = 1;
    const MATCH_INDENT_INCREASE = 2;
    const MATCH_DISCARD = 3;

    /**
     * @param array $options
     */
    public function __construct (array $options = array()) {
        foreach ($options as $name => $value) {
            if (!array_key_exists($name, $this->options)) {
                throw new Exception\InvalidArgumentException('Unrecognized option.');
            }

            $this->options[$name] = $value;
        }
    }

    /**
     * @param string $element_name Element name, e.g. "b".
     * @param ELEMENT_TYPE_BLOCK|ELEMENT_TYPE_INLINE $type
     * @return null
     */
    public function setElementType ($element_name, $type) {
        if ($type === static::ELEMENT_TYPE_BLOCK) {
            $this->inline_elements = array_diff($this->inline_elements, array($element_name));
        } else if ($type === static::ELEMENT_TYPE_INLINE) {
            $this->inline_elements[] = $element_name;
        } else {
            throw new Exception\InvalidArgumentException('Unrecognized element type.');
        }

        $this->inline_elements = array_unique($this->inline_elements);
    }

    /**
     * @param string $input HTML input.
     * @return string Indented HTML.
     */
    public function indent ($input) {
        if (empty($input)) {
            return $input;
        }
        $this->log = array();

        // Dindent does not indent. Instead, it temporary removes it from the code, indents the input, and restores the script body.
        foreach ($this->ignore_elements as $key) {
            if (preg_match_all('/<'.$key.'\b[^>]*>([\s\S]*?)<\/'.$key.'>/mi', $input, $matches)) {
                $this->temporary_replacements_ignore[$key] = $matches[0];
                foreach ($matches[0] as $i => $match) {
                    $input = str_replace($match, '<'.$key.'>' . ($i + 1) . '</'.$key.'>', $input);
                }
            }
        }

        foreach ($this->ignore_blade_elements as $key) {
            if (preg_match_all('/@'.$key.'([\s\S]*?)@end'.$key.'/mi', $input, $matches)) {
                $this->temporary_replacements_ignore[$key] = $matches[0];
                foreach ($matches[0] as $i => $match) {
                    $input = str_replace($match, '@'.$key. ($i + 1) . '@end'.$key, $input);
                }
            }
        }

        if (preg_match_all('/<!--([\s\S]*?)-->/mi', $input, $matches)) {
            $this->temporary_replacements_ignore['<!---->'] = $matches[0];
            foreach ($matches[0] as $i => $match) {
                $input = str_replace($match, '<!--'. ($i + 1) . '-->', $input);
            }
        }

        if (preg_match_all('/{{([\s\S].+?)}}/mi', $input, $matches)) {
            $this->temporary_replacements_ignore['{{}}'] = $matches[0];
            foreach ($matches[0] as $i => $match) {
                $input = str_replace($match, '{{'. ($i + 1) . '}}', $input);
            }
        }
        // Removing double whitespaces to make the source code easier to read.
        // With exception of <pre>/ CSS white-space changing the default behaviour, double whitespace is meaningless in HTML output.
        // This reason alone is sufficient not to use Dindent in production.
        $input = str_replace("\t", '', $input);
        $input = preg_replace('/\s{2,}/', ' ', $input);

        // Remove inline elements and replace them with text entities.
        if (preg_match_all('/<(' . implode('|', $this->inline_elements) . ')[^>]*>(?:[^<]*)<\/\1>/', $input, $matches)) {
            $this->temporary_replacements_inline = $matches[0];
            foreach ($matches[0] as $i => $match) {
                $input = str_replace($match, 'ᐃ' . ($i + 1) . 'ᐃ', $input);
            }
        }

        $subject = $input;

        $output = '';

        $next_line_indentation_level = 0;

        do {
            $indentation_level = $next_line_indentation_level;

            //'php', 'istrue', 'isfalse', 'isnull', 'isnotnull', 'style', 'script', 'routeis', 'routeisnot', 'instanceof', 'typeof', 'pushonce', 'repeat', 'haserror'

            $patterns = array(
                // blade tags
                '/^@(foreach|if|istrue|isfalse|isnull|isnotnull|style|script|routeis|routeisnot|instanceof|typeof|pushonce|repeat|haserror)(?:[\)$]+)(?:[^@]*)@end(foreach|if|istrue|isfalse|isnull|isnotnull|style|script|routeis|routeisnot|instanceof|typeof|pushonce|repeat|haserror)/' => static::MATCH_INDENT_NO,
                '/^@(foreach|if|istrue|isfalse|isnull|isnotnull|style|script|routeis|routeisnot|instanceof|typeof|pushonce|repeat|haserror)(?:[\)$]+)/' => static::MATCH_INDENT_NO,
                '/^@end(foreach|if|istrue|isfalse|isnull|isnotnull|style|script|routeis|routeisnot|instanceof|typeof|pushonce|repeat|haserror)/' => static::MATCH_INDENT_NO,
                // block tag
                '/^(<([a-z]+)(?:[^>]*)>(?:[^<]*)<\/(?:\2)>)/' => static::MATCH_INDENT_NO,
                // DOCTYPE
                '/^<!([^>]*)>/' => static::MATCH_INDENT_NO,
                // tag with implied closing
                '/^<(input|link|meta|base|br|img|source|hr)([^>]*)>/' => static::MATCH_INDENT_NO,
                // self closing SVG tags
                '/^<(animate|stop|path|circle|line|polyline|rect|use)([^>]*)\/>/' => static::MATCH_INDENT_NO,
                // opening tag
                '/^<[^\/]([^>]*)>/' => static::MATCH_INDENT_INCREASE,
                // closing tag
                '/^<\/([^>]*)>/' => static::MATCH_INDENT_DECREASE,
                // self-closing tag
                '/^<(.+)\/>/' => static::MATCH_INDENT_DECREASE,
                // whitespace
                '/^(\s+)/' => static::MATCH_DISCARD,
                // text node
                '/([^<]+)/' => static::MATCH_INDENT_NO
            );
            $rules = array('NO', 'DECREASE', 'INCREASE', 'DISCARD');

            foreach ($patterns as $pattern => $rule) {
                if ($match = preg_match($pattern, $subject, $matches)) {
                    if (empty($matches[0])) {
                        $output .= PHP_EOL;
                    }
                    $this->log[] = array(
                        'rule' => $rules[$rule],
                        'pattern' => $pattern,
                        'subject' => $subject,
                        'match' => empty($matches[0]) ? NULL : $matches[0]
                    );

                    $subject = mb_substr($subject, mb_strlen($matches[0]));

                    if ($rule === static::MATCH_DISCARD) {
                        break;
                    }

                    if ($rule === static::MATCH_INDENT_NO) {

                    } else if ($rule === static::MATCH_INDENT_DECREASE) {
                        $next_line_indentation_level--;
                        $indentation_level--;
                    } else {
                        $next_line_indentation_level++;
                    }

                    if ($indentation_level < 0) {
                        $indentation_level = 0;
                    }

                    $output .= (!empty($matches[0]) ? str_repeat($this->options['indentation_character'], $indentation_level) . $matches[0] : NULL ) . PHP_EOL;
                    break;
                }
            }
        } while ($match);

        $interpreted_input = '';
        foreach ($this->log as $e) {
            $interpreted_input .= $e['match'];
        }

        if ($interpreted_input !== $input) {
            print $interpreted_input;
            throw new Exception\RuntimeException('Did not reproduce the exact input. ');
        }

        $output = preg_replace('/(<(\w+)[^>]*>)\s*(<\/\2>)/', '\\1\\3', $output);

        foreach ($this->ignore_elements as $key) {
            if(isset($this->temporary_replacements_ignore[$key])){
                foreach ($this->temporary_replacements_ignore[$key] as $i => $original) {
                 $output = str_replace('<'.$key.'>' . ($i + 1) . '</'.$key.'>', $original, $output);
                }
            }
        }

        foreach (array_reverse($this->ignore_blade_elements) as $key) {
            if(isset($this->temporary_replacements_ignore[$key])){
                foreach ($this->temporary_replacements_ignore[$key] as $i => $original) {
                    $output = str_replace('@'.$key . ($i + 1) . '@end'.$key, $original, $output);
                }
            }
        }

        foreach ($this->temporary_replacements_inline as $i => $original) {
            $output = str_replace('ᐃ' . ($i + 1) . 'ᐃ', $original, $output);
        }

        if(isset($this->temporary_replacements_ignore['{{}}'])){
            foreach ($this->temporary_replacements_ignore['{{}}'] as $i => $original) {
                $output = str_replace('{{'. ($i + 1) . '}}', $original, $output);
            }
        }

        if(isset($this->temporary_replacements_ignore['<!---->'])){
            foreach ($this->temporary_replacements_ignore['<!---->'] as $i => $original) {
                $output = str_replace('<!--'. ($i + 1) . '-->', $original, $output);
            }
        }

        return trim($output);
    }

    /**
     * Debugging utility. Get log for the last indent operation.
     *
     * @return array
     */
    public function getLog () {
        return $this->log;
    }
}
