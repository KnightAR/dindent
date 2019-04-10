<?php
/**
 * @internal
 * @coversNothing
 */
class CLITest extends PHPUnit_Framework_TestCase
{
    public function testMissingInputParameter()
    {
        $this->assertSame('Missing "input" parameter.', static::exec('--foo'));
    }

    public function testInputFileDoesNotExist()
    {
        $this->assertSame('"input" file does not exist.', static::exec('--input="./bar.html"'));
    }

    public function testIndentOutput()
    {
        $this->assertSame('<div></div>', static::exec('--input='.escapeshellarg(__DIR__.'/sample/input/0-empty-block.html')));
    }

    public static function exec($arguments)
    {
        return shell_exec('php '.escapeshellarg(__DIR__.'/../bin/dindent.php').' '.$arguments);
    }

    public function indentProvider()
    {
        return array_map(function ($e) {
            return [pathinfo($e, \PATHINFO_FILENAME)];
        }, glob(__DIR__.'/input/*.html'));
    }
}
