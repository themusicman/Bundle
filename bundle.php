<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Bundle provides a class and configuration loading system. 
 *
 * Most of the code comes from Kohana. Just reworked some stuff so that it could be used
 * outside of Kohana Web Application Framework.
 *
 * @package Bundle
 * @author Thomas Brewer
 * @link http://th3mus1cman.me
 *
 **/
class Bundle {
	
	/**
	 * The directory separator to use in file paths
	 *
	 * @const string 
	 **/
	const DIRECTORY_SEPARATOR 				= '/';
	
	/**
	 * The default file extension to use
	 *
	 * @const string 
	 **/
	const EXT									= '.php';
	
	/**
	 * Store whether of not the bundles have been initialized
	 * 
	 * @access protected
	 * @static
	 * @var bool $_initialized 
	 **/
	protected static $_initialized		= FALSE;
	
	/**
	 * Holds all the bundle instances
	 * 
	 * @access protected
	 * @static
	 * @var array $_bundles
	 **/
	protected static $_bundles				= array();
		
	/**
	 * Base path to the cache directory
	 * 
	 * @access protected
	 * @static
	 * @var string $cache_dir 
	 **/
	protected static $cache_dir			= '';
	
	/**
	 * To cache of not to cache
	 * 
	 * @access public
	 * @static
	 * @var bool $caching
	 **/
	public static $caching					= FALSE;
	
	/**
	 * The bundle's name
	 * 
	 * @access protected
	 * @static
	 * @var string $name
	 **/
	public $name 								= NULL;

	/**
	 * The base path to the bundle
	 * 
	 * @access public
	 * @var string $base_path  
	 **/
	public $base_path							= '';
	
	/**
	 * Stores the file paths found in a bundle
	 * 
	 * @access protected
	 * @var array $_files 
	 **/
	protected $_files 						= array();	

	/**
	 * Has the the $_files of a bundle been changed - used if caching is enabled
	 * 
	 * @access protected
	 * @static
	 * @var bool $_files_changed 
	 **/
	protected static $_files_changed		= FALSE;

	/**
	 * init
	 *
	 * @access public
	 * @param  bool $caching Whether or not to use caching
	 * @param  string $cache_dir The directory to use to store the cached data - must be writeable.	
	 * @return void
	 * 
	 **/
	public static function init($caching = FALSE, $cache_dir = '') 
	{
		Bundle::$caching = $caching;
		
		if (Bundle::$_initialized === FALSE)
		{
			if (Bundle::$caching === TRUE)
			{				
				Bundle::$cache_dir = $cache_dir;

				$bundles = Bundle::cache('Bundle::$_bundles');
				
				if ($bundles !== NULL)
				{
					Bundle::$_bundles = $bundles;
				}
			}
			
			spl_autoload_register(array('Bundle', 'auto_load'));

			register_shutdown_function(array('Bundle', 'shutdown_handler'));
		}
	}
	
	/**
	 * load
	 *
	 * @access public
	 * @param  string $name The name of the bundle	
	 * @param  string $base_dir The base path to the bundle
	 * @return void
	 * 
	 **/
	public static function factory($name, $base_dir)
	{
		$bundle = new Bundle($name, $base_dir);
		return $bundle;
	}
	
	/**
	 * load
	 *
	 * @access public
	 * @param  array $bundles An array of bundle paths	
	 * @return void
	 * 
	 **/
	public static function load($bundles = array()) 
	{			
		if (isset($bundles['main']))
		{
			$main_path = $bundles['main'];
			unset($bundles['main']);
		}
		
		$core_path = NULL;
		if (isset($bundles['core']))
		{
			$core_path = $bundles['core'];
			unset($bundles['core']);
		}
	
		if ($core_path !== NULL)
		{
			array_unshift(Bundle::$_bundles, Bundle::factory('core', $core_path));
		}
		
		foreach (array_reverse($bundles, TRUE) as $bundle_name => $base_path) 
		{
			array_unshift(Bundle::$_bundles, Bundle::factory($bundle_name, $base_path));
		}
		
		if ($main_path !== NULL)
		{
			array_unshift(Bundle::$_bundles, Bundle::factory('main', $main_path));
		}
		
		foreach (Bundle::$_bundles as $bundle) 
		{
			$init = $bundle->base_path.'init'.Bundle::EXT;
			if (is_file($init))
			{
				require_once $init;
			}
		}
		
	}
	
	/**
	 * find_file
	 *
	 * @access public
	 * @param  string $dir directory to look in for the file	
	 * @param  string $file the file to find in the directory	
	 * @param  string $ext the extension of the file to find
	 * @return bool|string If the file is not found it returns FALSE and if it is found returns the file path
	 * 
	 **/
	public static function find_file($dir, $file, $ext = NULL) 
	{
		foreach (Bundle::$_bundles as $bundle_name => $bundle) 
		{
			if ($path = $bundle->file($dir, $file, $ext))
			{
				return $path;
			}
		}
	}
	
	/**
	 * The same file might exist in multiple bundles. This method finds all of the bundle paths for the file
	 * and not just the first one found like Bundle::find_file.
	 *
	 * @access public
	 * @param  string $dir directory to look in for the file	
	 * @param  string $file the file to find in the directory	
	 * @param  string $ext the extension of the file to find
	 * @return array It returns an array of paths for the file request 
	 * 
	 **/
	public function find_all_files($dir, $file, $ext = NULL) 
	{
		$paths = array();
		
		foreach (Bundle::$_bundles as $bundle_name => $bundle) 
		{
			if ($path = $bundle->file($dir, $file, $ext))
			{
				$paths[] = $path;
			}
		}
		
		return $paths;
	}
	
	/**
	 * config
	 *
	 * @access public
	 * @param  string $group The config group to the load.
	 * @return ArrayObject
	 * 
	 **/
	public static function config($group) 
	{
		static $config;

		if (strpos($group, '.') !== FALSE)
		{
			// Split the config group and path
			list ($group, $keypath) = explode('.', $group, 2);
		}

		if ( ! isset($config[$group]))
		{
			$path = Bundle::find_file('config', $group);
			
			if ($path === NULL)
			{
				return NULL;
			}
			
			$config[$group] = Bundle::load_file($path);
		}

		if (isset($keypath))
		{
			/*
				TODO Fix the dependency on Kohana
			*/
			$config_array = Arr::path($config[$group], $keypath);
		}
		else
		{
			$config_array = $config[$group];
		}
		
		if (is_array($config_array))
		{
			return new ArrayObject($config_array, ArrayObject::ARRAY_AS_PROPS);
		}
		
	}
	
	/**
	 * load_file
	 *
	 * @access public
	 * @param  string $path The path to the file to load
	 * @return mixed
	 * 
	 **/
	public static function load_file($path) 
	{
		return include $path;
	}
	
	/**
	 * cache
	 *
	 * @access public
	 * @param  string $name The cache key used to find the cache data	
	 * @param  mixed $data The data to be saved to the cache
	 * @param  int $lifetime The length of time the cache is valid
	 * @return bool|mixed
	 * 
	 **/
	public static function cache($name, $data = NULL, $lifetime = 60) 
	{
	
		// Cache file is a hash of the name
		$file = sha1($name).'.txt';

		// Cache directories are split by keys to prevent filesystem overload
		$dir = Bundle::$cache_dir.Bundle::DIRECTORY_SEPARATOR.$file[0].$file[1].Bundle::DIRECTORY_SEPARATOR;
		
		try
		{
			if ($data === NULL)
			{
				if (is_file($dir.$file))
				{
					if ((time() - filemtime($dir.$file)) < $lifetime)
					{
						// Return the cache
						return unserialize(file_get_contents($dir.$file));
					}
					else
					{
						// Cache has expired
						unlink($dir.$file);
					}
				}

				// Cache not found
				return NULL;
			}

			if ( ! is_dir($dir))
			{
				// Create the cache directory
				mkdir($dir, 0777, TRUE);

				// Set permissions (must be manually set to fix umask issues)
				chmod($dir, 0777);
			}

			// Write the cache
			return (bool) file_put_contents($dir.$file, serialize($data));
		}
		catch (Exception $e)
		{
			throw $e;
		}
	}
	
	/**
	 * __construct
	 *
	 * @access public
	 * @param  string $name Bundle name	
	 * @param  string $base_path The base path to the bundle
	 * @return Bundle
	 * 
	 **/
	public function __construct($name, $base_path) 
	{
		$this->name = $name;
		$this->base_path = $base_path;
	}
			
	/**
	 * file
	 *
	 * @access public
	 * @param  string $dir directory to look in for the file	
	 * @param  string $file the file to find in the directory	
	 * @param  string $ext the extension of the file to find
	 * @return bool|string If the file is not found it returns FALSE and if it is found returns the file path
	 * 
	 **/
	public function file($dir, $file, $ext) 
	{
		// Use the defined extension by default
		$ext = ($ext === NULL) ? self::EXT : '.'.$ext;

		// Create a partial path of the filename
		$path = $this->base_path.$dir.self::DIRECTORY_SEPARATOR.$file.$ext;
		
		if (Bundle::$caching === TRUE AND isset($this->_files[$path]))
		{
			return $this->_files[$path];
		}
		
		// The file has not been found yet
		$found = FALSE;
				
		if (is_file($path))
		{
				// A path has been found
				$found = $path;
		}

		if (Bundle::$caching === TRUE)
		{
			// Add the path to the cache
			$this->_files[$path] = $found;

			// Files have been changed
			Bundle::$_files_changed = TRUE;
		}

		return $found;
	}
	
	
	/**
	 * auto_load
	 *
	 * @access public
	 * @param  string	class name
	 * @return bool whether the class file was found or not
	 * 
	 **/
	public static function auto_load($class)
	{
		// Transform the class name into a path
		$file = str_replace('_', '/', strtolower($class));
		
		if ($path = Bundle::find_file('classes', $file))
		{
			// Load the class file
			require $path;

			// Class has been found
			return TRUE;
		}

		// Class is not on the filesystem
		return FALSE;
	}
	
	/**
	 * shutdown_handler
	 *
	 * @access public
	 * @param  void	
	 * @return void
	 * 
	 **/
	public static function shutdown_handler() 
	{
		if (Bundle::$caching === TRUE AND Bundle::$_files_changed === TRUE)
		{
			Bundle::cache('Bundle::$_bundles', Bundle::$_bundles);
		}
	}
	
}