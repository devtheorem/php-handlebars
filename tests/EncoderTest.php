<?php
/**
 * Generated by build/gen_test
 */
use LightnCandy\LightnCandy;
use LightnCandy\Runtime;
use LightnCandy\SafeString;
use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/test_util.php');

class EncoderTest extends TestCase
{
    public function testOn_enc() {
        $method = new \ReflectionMethod('LightnCandy\Encoder', 'enc');
        $this->assertEquals('a', $method->invokeArgs(null, array_by_ref(array(
            array('flags' => array()), 'a'
        ))));
        $this->assertEquals('a&amp;b', $method->invokeArgs(null, array_by_ref(array(
            array('flags' => array()), 'a&b'
        ))));
        $this->assertEquals('a&#039;b', $method->invokeArgs(null, array_by_ref(array(
            array('flags' => array()), 'a\'b'
        ))));
    }
    public function testOn_encq() {
        $method = new \ReflectionMethod('LightnCandy\Encoder', 'encq');
        $this->assertEquals('a', $method->invokeArgs(null, array_by_ref(array(
            array('flags' => array()), 'a'
        ))));
        $this->assertEquals('a&amp;b', $method->invokeArgs(null, array_by_ref(array(
            array('flags' => array()), 'a&b'
        ))));
        $this->assertEquals('a&#x27;b', $method->invokeArgs(null, array_by_ref(array(
            array('flags' => array()), 'a\'b'
        ))));
        $this->assertEquals('&#x60;a&#x27;b', $method->invokeArgs(null, array_by_ref(array(
            array('flags' => array()), '`a\'b'
        ))));
    }
}
