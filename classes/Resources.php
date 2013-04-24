<?php
//	class Resources (issue #157)
//	outputs secondary files, such as css and javascript
//	data is stored gzipped (gzencode) and encoded (base64_encode)
//
class Resources {

	// set this to the file containing getInternalResource;
	// currently unused in split mode; set to __FILE__ for built PLA.
	public static $embedding_file = __FILE__;

	private static $_resources = array(
		'css' => array(
			'mime' => 'text/css',
			'data' => 'resources/phpliteadmin.css',
		),
		'javascript' => array(
			'mime' => 'text/javascript',
			'data' => 'resources/phpliteadmin.js',
		),
		'favicon' => array(
			'mime' => 'image/x-icon',
			'data' => 'resources/favicon.ico',
			'base64' => 'true',
		),
	);

	// outputs the specified resource, if defined in this class.
	// the main script should do no further output after calling this function.
	public static function output($resource)
	{
		if (isset(self::$_resources[$resource])) {
			$res =& self::$_resources[$resource];

			if (function_exists('getInternalResource') && $data = getInternalResource($res['data'])) {
				$filename = self::$embedding_file;
			} else {
				$filename = $res['data'];
			}

			// use last-modified time as etag; etag must be quoted
			$etag = '"' . filemtime($filename) . '"';

			// check headers for matching etag; if etag hasn't changed, use the cached version
			if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag) {
				header('HTTP/1.0 304 Not Modified');
				return;
			}

			header('Etag: ' . $etag);

			// cache file for at most 30 days
			header('Cache-control: max-age=2592000');

			// output resource
			header('Content-type: ' . $res['mime']);

			if (isset($data)) {
				if (isset($res['base64'])) {
					echo base64_decode($data);
				} else {
					echo $data;
				}
			} else {
				readfile($filename);
			}
		}
	}

}
