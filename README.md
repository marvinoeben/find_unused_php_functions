# find_unused_php_functions

File which finds php files in the same folder, sees if they are called and then prints them into the browser.  
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
$ingored_folders: Names folders should include which can be ignored.
  Example: if $ignored_folders=['archive'], all folders and subfolders with the name 'archive' in them will be ignored.
