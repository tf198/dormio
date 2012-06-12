<?php
class ExampleTest extends PHPUnit_Framework_TestCase {
	function testUsage() {
		$this->assertOutput('usage');
	}
	
	function assertOutput($example) {
		exec("php docs/examples/{$example}.php", $output, $ret);
		$this->assertEquals(0, $ret);
		$expected = file("docs/examples/{$example}.out", FILE_IGNORE_NEW_LINES);
		$this->assertEquals($expected, $output);
	}
}