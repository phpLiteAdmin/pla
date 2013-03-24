<?php
//	class MicroTimer (issue #146)
//	wraps calls to microtime(), calculating the elapsed time and rounding output
//
class MicroTimer {

	private $startTime, $stopTime;

	// creates and starts a timer
	function __construct()
	{
		$this->startTime = microtime(true);
	}

	// stops a timer
	public function stop()
	{
		$this->stopTime = microtime(true);
	}

	// returns the number of seconds from the timer's creation, or elapsed
	// between creation and call to ->stop()
	public function elapsed()
	{
		if ($this->stopTime)
			return round($this->stopTime - $this->startTime, 4);

		return round(microtime(true) - $this->startTime, 4);
	}

	// called when using a MicroTimer object as a string
	public function __toString()
	{
		return (string) $this->elapsed();
	}

}
