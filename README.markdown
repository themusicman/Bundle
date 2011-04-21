# Bundle #

It is class/file loading system based on the Kohana class/file loading system. So it gives you the same cascading file system that Kohana has. It can be used to load Kohana and Kohana modules in non-Kohana projects.  

Bundle has the concept of a main bundle, which would be the application directory in Kohana, and it will be the first bundle searched for a file.  It also has the concept of a core bundle, which would be the system directory in Kohana, and it will be the last bundle searched for a file. You specify whether the bundle is the main or core bundle by the index used when you load the bundles - see below.  Every other bundle is loaded in the order it is in the array passed to the load method.

## Installation

* Add the bundle.php file to your project and include it in one of your files.  

		require 'path/to/the/bundle/file/bundle.php';
		
## Usage

* Load the bundles

		Bundle::load(array(
			'main' 			=> BASEPATH.'app/',
			'curlparty'		=>	BASEPATH.'curlparty/',
			'core' 			=> BASEPATH.'kohana/',
		));


		
## Issues

Please log any issues or problems here: <https://github.com/themusicman/Bundle/issues>
