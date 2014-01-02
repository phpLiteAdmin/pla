<?php
// ===== phpLiteAdmin build script =====

// === Build directives that can appear in the source code ===
//
// # EMBED <filename or glob pattern>  [ | <function> [ | ...] ]
// Embeds an external file in the source code as a gzipped and base64-encoded string;
// optional filter functions can be "piped" at the end of the line;
// multiple files can be specified with glob syntax. E.g., classes/*.php
//
// # INCLUDE <php filename or glob pattern>  [ | <function> [ | ...] ]
// Includes code from another php file; expects the first line of the file to
// start with '<?php';
// optional filter functions can be "piped" at the end of the line;
// multiple files can be specified with glob syntax. E.g., classes/*.php
//
// # EXPORT <$variable>
// Assigns a variable using the current value in the build script;
// will generate a valid and indented line of php code. E.g.
//   $myvar = array('key'=>'value','otherkey'=>'othervalue');
//
// ###identifier###
// Is replaced with the value of $build_data['identifier']; if the array
// element is not defined, the string will be left untouched.
//
// # REMOVE_FROM_BUILD ... # END REMOVE_FROM_BUILD
// Anything between the two directive will be removed from the final code.
//
// # other build comments
// Any remaining lines starting with a # will be removed.
//

// === build script configuration ===
$inputfile  = 'phpliteadmin-build-template.php';
$outputfile = 'phpliteadmin.php';

// === identifiers recognized in EXPORT and ###something### directives ===
$build_data = array(
	// used for resource embedding
	'resources' => array(),
	'resourcesize' => 0,
	// custom variables
	'build_date' => date('Y-m-d'),
	'build_year' => date('Y'),
);

?>
<!doctype html>
<html><head>
	<meta charset="utf-8" />
	<title>phpLiteAdmin build script</title>
	<style type="text/css">
		body { font-family: monospace; color: #222; }
		span { color: #22A; }
		li.warn { color: #C52; }
		body > ol > li { list-style-type: upper-alpha; margin-top: 0.5em; }
		li li { list-style-type: decimal-leading-zero; }
	</style>
</head><body><?php

// initialize script
$output = '';
error_reporting(E_ALL);

echo "<h1>Building phpLiteAdmin</h1><ol>";

// load the build template only this file will be parsed for build directives
echo "<li>Loading template: <span>{$inputfile}</span>";
$output .= file_get_contents($inputfile);

// parse EMBED
echo "<li>Embedding resources<ol>";
$output = preg_replace_callback('@(\r?\n?)^#\s*EMBED\s+(?P<pattern>\S+)\s*(?P<filters>(\|\s*\w+\s*)+|)\r?\n?$@m', 'replace_embed', $output);
echo "</ol>";

// parse INCLUDE
echo "<li>Including files<ol>";
$output = preg_replace_callback('@^#\s*INCLUDE\s+(?P<pattern>\S+)\s*(?P<filters>(\|\s*\w+\s*)+|)$@m', 'replace_include', $output);
echo "</ol>";

// parse EXPORT
echo "<li>Replacing variables<ol>";
$output = preg_replace_callback('@^(?P<indent>\s*)#\s*EXPORT\s+\$?(?P<identifier>\w+)\s*$@m', 'replace_export', $output);

// parse ###identifier###
$output = preg_replace_callback('@###(?P<identifier>\w+)###@', 'replace_value', $output);
echo "</ol>";

// parse REMOVE_FROM_BUILD
echo "<li>Removing ignored code";
$output = preg_replace('@(\r?\n?)^#\s*REMOVE_FROM_BUILD.*?^#\s*END\s+REMOVE_FROM_BUILD\s*$@ms', '', $output);

// remove other comments starting with '#'
echo "<li>Removing build comments";
$output = preg_replace('@(\r?\n)^#.*\r?\n?$@m', '', $output);

// save result script to file
echo "<li>Saving code to <span>{$outputfile}</span>";
file_put_contents($outputfile, $output);


// ===== end of main code, support functions follow =====
?></ol></body></html><?php


// filter functions for embedded files

function minify_css($css)
{
	include 'support/Compressor.php';
	return preg_replace('/\r?\n/', ' ', Minify_CSS_Compressor::process($css));
}

function minify_js($js)
{
	include 'support/Minifier.php';
	return Minifier::minify($js);
}

function comment_lines($text)
{
	return preg_replace('/^/m', "//\t", $text);
}


// replace functions for build script directives

function replace_include($m)
{
	if ($m['filters']) {
		$filters = array_map('trim', preg_split('@\s*\|\s*@', trim($m['filters'], ' |')));
	} else {
		$filters = array();
	}

	$source = '';
	$matching_files = glob($m['pattern']);
	if ($matching_files) {
		foreach ($matching_files as $filename) {
			if (is_file($filename) && is_readable($filename)) {
				echo "<li><span>{$filename}</span>";

				// read file and remove first '<?php' line
				$data = preg_replace('/^<\?php\s*\r?\n/', '', file_get_contents($filename));

				// pipe $data through filter functions
				foreach ($filters as $function) {
					$data = call_user_func($function, $data);
				}

				$source .= $data;
			} else {
				echo "<li class='warn'><span>{$filename}</span> - cannot read file";
			}
		}
	} else {
		echo "<li class='warn'><span>{$m['pattern']}</span> - no files matching";
	}
	return $source;      
}

function replace_embed($m)
{
	if ($m['filters']) {
		$filters = array_map('trim', preg_split('@\s*\|\s*@', trim($m['filters'], ' |')));
	} else {
		$filters = array();
	}

	$result = '';

	global $build_data;
	$matching_files = glob($m['pattern']);
	if ($matching_files) {
		foreach ($matching_files as $filename) {
			if (is_file($filename) && is_readable($filename)) {
				echo "<li><span>{$filename}</span>";
				$data = file_get_contents($filename);

				// pipe $data through filter functions
				foreach ($filters as $function) {
					$data = call_user_func($function, $data);
				}

				// encode filtered data,
	//			$data = base64_encode(gzencode($data)); // disabled after #197
				$result .= $data;

				// evaluate size and position relative to __COMPILER_HALT_OFFSET__
				$size = strlen($data);
				$build_data['resources'][$filename] = array($build_data['resourcesize'], $size);
				$build_data['resourcesize'] += $size;
			} else {
				echo "<li class='warn'><span>{$filename}</span> - cannot read file";
			}
		}
	} else {
		echo "<li class='warn'><span>{$m['pattern']}</span> - no files matching";
	}

	return $result;
}

function replace_export($m)
{
	global $build_data;
	$variable = $m['identifier'];

	if (isset($build_data[$variable])) {
	  echo "<li><span>\${$variable}</span> = " . gettype($build_data[$variable]);
	  return $m['indent'] . '$' . $variable . ' = ' . preg_replace('/\s+/','', var_export($build_data[$variable], true)) . ';' . PHP_EOL;
	}
	
	// remove line if variabile is not defined
	echo "<li class='warn'><span class=\"warn\">\${$variable}</span> - variable not defined";
	return '';
}

function replace_value($m)
{
	global $build_data;
	$variable = $m['identifier'];

	if (isset($build_data[$variable])) {
	  echo "<li><span>\${$variable}</span> = " . gettype($build_data[$variable]);
		return $build_data[$variable];
	}

	// leave the string if the variable is not defined
	echo "<li class='warn'><span class=\"warn\">\${$variable}</span> - variable not defined";
	return $m[0];
}

// end of build script

