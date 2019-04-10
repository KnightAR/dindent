<?php
/**
 * @internal
 * @coversNothing
 */
class IndenterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Gajus\Dindent\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unrecognized option.
     */
    public function testInvalidSetupOption()
    {
        new \Gajus\Dindent\Indenter(['foo' => 'bar']);
    }

    /**
     * @expectedException \Gajus\Dindent\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unrecognized element type.
     */
    public function testSetInvalidElementType()
    {
        $indenter = new \Gajus\Dindent\Indenter();
        $indenter->setElementType('foo', 'bar');
    }

    /*public function testSetElementTypeInline () {
        $indenter = new \Gajus\Dindent\Indenter();
        $indenter->setElementType('foo', \Gajus\Dindent\Indenter::ELEMENT_TYPE_BLOCK);

        $output = $indenter->indent('<p><span>X</span></p>');

        die(var_dump( $output ));
    }*/

    public function testIndentCustomCharacter()
    {
        $indenter = new \Gajus\Dindent\Indenter(['indentation_character' => 'X']);

        $indented = $indenter->indent('<p><p></p></p>');

        $expected_output = '<p>X<p></p></p>';

        $this->assertSame($expected_output, str_replace("\n", '', $indented));
    }

    /**
     * @dataProvider logProvider
     *
     * @param mixed $token
     * @param mixed $log
     */
    public function testLog($token, $log)
    {
        $indenter = new \Gajus\Dindent\Indenter();
        $indenter->indent($token);

        $this->assertSame([$log], $indenter->getLog());
    }

    public function logProvider()
    {
        return [
            [
                '<p></p>',
                [
                    'rule' => 'NO',
                    'pattern' => '/^(<([a-z]+)(?:[^>]*)>(?:[^<]*)<\\/(?:\\2)>)/',
                    'subject' => '<p></p>',
                    'match' => '<p></p>',
                ],
            ],
        ];
    }

    /**
     * @dataProvider indentProvider
     *
     * @param mixed $name
     */
    public function testIndent($name)
    {
        $indenter = new \Gajus\Dindent\Indenter();

        $input = file_get_contents(__DIR__.'/sample/input/'.$name.'.html');
        $expected_output = file_get_contents(__DIR__.'/sample/output/'.$name.'.html');

        $this->assertSame($expected_output, $indenter->indent($input));
    }

    public function indentProvider()
    {
        return array_map(function ($e) {
            return [pathinfo($e, \PATHINFO_FILENAME)];
        }, glob(__DIR__.'/sample/input/*.html'));
    }
}
