<?php
# Start with header information, default config and default language
# INCLUDE docs/header.txt | comment_lines
# INCLUDE phpliteadmin.config.sample.php
# INCLUDE languages/lang_en.php

# Load the main phpLiteAdmin source file
# INCLUDE index.php

# Append all class files
# INCLUDE classes/*.php

# Embed resources: embed file info this function, actual data below

// returns data from internal resources, available in single-file mode
function getInternalResource($res) {
	# EXPORT $resources

	if (isset($resources[$res]) && $f = fopen(__FILE__, 'r')) {
		fseek($f, __COMPILER_HALT_OFFSET__ + $resources[$res][0]);
		$data = fread($f, $resources[$res][1]);
		fclose($f);
		return $data;
	}
	return false;  
}

// resources embedded below, do not edit!
# Important: no semi-colon after the call to __halt_compiler, so the parser can consume
# the closing tag
__halt_compiler() ?>
# EMBED resources/phpliteadmin.css | minify_css
# EMBED resources/phpliteadmin.js | minify_js
# EMBED resources/favicon.ico | base64_encode

