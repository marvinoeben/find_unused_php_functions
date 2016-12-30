<?php
/*
================================================================================
File which finds php files in the same folder, sees if they are called and then
prints them into the browser.
Borrowed from:
https://github.com/andreybutov/find-unused-php-functions
------------------------
Find unused functions in a set of PHP files.

USAGE: find_unused_functions.php <root_directory>

NOTE: This is a ‘quick-n-dirty’ approach to the problem. This script only
performs a lexical pass over the files, and does not respect situations where
different modules define identically named functions or methods. If you use an
IDE for your PHP development, it may offer a more comprehensive solution.

Requires PHP 5
------------------------
Parameters:
$root_dir : The directory in which you want to search.
	Default: The directory the file is in.
$ingored_folders: Folders inside the root_dir wishing to be ignored.
	Default: []; (Empty array)
	Example:
	var/www/html
		/archive
			/old_2015
		/testing_Functions
			/old_2015
	$root_dir = www/html/;
	$ignored_folders = ["/archive","/testing_Functions/old_2015"];
	will give the unused functions in all but /archive and
	/testing_functions/old_functions
*/

// Some styling:
echo "<html><title>Overview of unused functions</title><body>";
echo "<style>table, th, td {
						 border: 1px solid black;
						 border-collapse: collapse;
						 padding: 10px;
						 text-align: left;
					   }
						 div {
							 max-height:500px;
 						 	 overflow:auto;
						 }
			</style>";
// Setting the memory_limit
ini_set('memory_limit', '2048M');

// Set the dir as current dir
$root_dir = dirname(__FILE__);
$ignored_folders = ['archive'];

// Show the ignored folders
echo "<h3> Folders in $root_dir which will be ignored:</h3>";
echo "<div>";
foreach($ignored_folders as &$foldr){
	echo "$foldr<br>";
}
echo "</div>";

// Define the files in the current dir.
$files = php_files($root_dir, $ignored_folders);

// Print the files:
echo "<h3> Files found in the \"$root_dir\" folder:</h3>";
echo "<div>";
foreach($files as &$fl){
	$tmp_fl = explode($root_dir.'/',$fl);
	echo "$tmp_fl[1]<br>";
}
echo "</div>";

// Define empty arrays.
$tokenized = [];

$defined_functions = [];

foreach ( $files as $file )
{
	$tokens = tokenize($file);

	if ( $tokens )
	{
		// We retain the tokenized versions of each file,
		// because we'll be using the tokens later to search
		// for function 'uses', and we don't want to
		// re-tokenize the same files again.

		$tokenized[$file] = $tokens;

		for ( $i = 0 ; $i < count($tokens) ; ++$i )
		{
			$current_token = $tokens[$i];
			$next_token = safe_arr($tokens, $i + 2, false);

			if ( is_array($current_token) && $next_token && is_array($next_token) )
			{
				if ( safe_arr($current_token, 0) == T_FUNCTION )
				{
					// Find the 'function' token, then try to grab the
					// token that is the name of the function being defined.
					//
					// For every defined function, retain the file and line
					// location where that function is defined. Since different
					// modules can define a functions with the same name,
					// we retain multiple definition locations for each function name.

					$function_name = safe_arr($next_token, 1, false);
					$line = safe_arr($next_token, 2, false);

					if ( $function_name && $line )
					{
						$function_name = trim($function_name);
						if ( $function_name != "" )
						{
							$defined_functions[$function_name][] = array('file' => $file, 'line' => $line);
						}
					}
				}
			}
		}
	}
}

// We now have a collection of defined functions and
// their definition locations. Go through the tokens again,
// and find 'uses' of the function names.

foreach ( $tokenized as $file => $tokens )
{
	foreach ( $tokens as $token )
	{
		if ( is_array($token) && safe_arr($token, 0) == T_STRING )
		{
			$function_name = safe_arr($token, 1, false);
			$function_line = safe_arr($token, 2, false);;

			if ( $function_name && $function_line )
			{
				$locations_of_defined_function = safe_arr($defined_functions, $function_name, false);

				if ( $locations_of_defined_function )
				{
					$found_function_definition = false;

					foreach ( $locations_of_defined_function as $location_of_defined_function )
					{
						$function_defined_in_file = $location_of_defined_function['file'];
						$function_defined_on_line = $location_of_defined_function['line'];

						if ( $function_defined_in_file == $file &&
							 $function_defined_on_line == $function_line )
						{
							$found_function_definition = true;
							break;
						}
					}

					if ( !$found_function_definition )
					{
						// We found usage of the function name in a context
						// that is not the definition of that function.
						// Consider the function as 'used'.

						unset($defined_functions[$function_name]);
					}
				}
			}
		}
	}
}

print_report($defined_functions);
exit;


// ============================================================================

function php_files($path, $ignored_folders = [])
{
	// Get a listing of all the .php files contained within the $path
	// directory and its subdirectories.
	/*foreach($ignored_folders as &$foldr){
		$foldr = $path.$foldr;
	}*/
	$matches = array();
	$folders = array(rtrim($path, DIRECTORY_SEPARATOR));
	while( $folder = array_shift($folders) ){
		$matches = array_merge($matches, glob($folder.DIRECTORY_SEPARATOR."*.php", 0));
		$moreFolders = glob($folder.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR);
		foreach($moreFolders as &$foldr){
			foreach($ignored_folders as &$ignrd){
				if(strpos($foldr, $ignrd)!==false){
					if(($key = array_search($foldr, $moreFolders)) !== false) {
					    unset($moreFolders[$key]);
					}
				}
			}
		}
		$moreFolders = array_diff($moreFolders,$ignored_folders);
		$folders = array_merge($folders, $moreFolders);
	}
	return $matches;
}

// ============================================================================

function safe_arr($arr, $i, $default = "")
{
	return isset($arr[$i]) ? $arr[$i] : $default;
}

// ============================================================================

function tokenize($file)
{
	$file_contents = file_get_contents($file);

	if ( !$file_contents )
	{
		return false;
	}

	$tokens = token_get_all($file_contents);
	return ($tokens && count($tokens) > 0) ? $tokens : false;
}

// ============================================================================

function print_report($unused_functions)
{
	$root_dir = dirname(__FILE__);
	if ( count($unused_functions) == 0 )
	{
		echo "No unused functions found.<br>";
	}

	$count = 0;
	echo "<h3>Unused functions:</h3>";
	echo "<div>";
	echo "<table><tr><th>function</th><th>file</th><th>line</th></tr>";
	foreach ( $unused_functions as $function => $locations )
	{
		foreach ( $locations as $location )
		{
			$tmp_fl = explode($root_dir.'/',$location['file']);
			echo "<tr><td>$function</td><td>$tmp_fl[1]</td><td>{$location['line']}</td></tr>";
			$count++;
		}
	}

	echo "</table>";

	if($count > 1000){
		echo "<div><font size='100'> :-( </font></div>";
	}
}

echo "</body></html>"
?>
