<?php
class ExampleTest extends PHPUnit_Framework_TestCase {
	function testUsage() {
		$this->assertOutput('usage');
	}
	
	function testSchema() {
		$this->assertOutput('schema');
	}
	
	function testForms() {
		$this->assertOutput('forms');
	}
	
	function testTables() {
		$this->assertOutput('tables');
	}
	
	/**
	 * Check we haven't introduced any memory leaks
	 */
	function testBenchmark() {
		if(gethostname() != 'TFC-SERVER') $this->markTestSkipped();
		
		$last = exec('php benchmark.php', $output, $ret);
		$this->assertEquals(0, $ret, "Failed to run benchmark script");
		$scores = sscanf(array_pop($output), "%f %f | %f %f");
		$this->assertLessThan(180, $scores[0], "Execution time");
		$this->assertLessThan(1400, $scores[2], "Memory usage");
	}
	
	function assertOutput($example) {
		ob_start();
		passthru("php docs/examples/{$example}.php", $ret);
		$output = ob_get_clean();
		$this->assertEquals(0, $ret, "Failed to run {$example}.php");
		$this->assertStringEqualsFile("docs/output/{$example}.txt", $output);
	}
}