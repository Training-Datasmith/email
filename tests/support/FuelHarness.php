<?php

class FuelException extends Exception {}

class Fuel
{
	const L_INFO = 200;

	public static $is_test = false;

	public static $encoding = 'UTF-8';

	public static function value($var)
	{
		return $var instanceof Closure ? $var() : $var;
	}
}

class TestLogger
{
	public static $entries = array();

	public static function clear()
	{
		self::$entries = array();
	}

	public static function write($level, $msg, $context = null)
	{
		self::$entries[] = array('level' => $level, 'msg' => $msg, 'context' => $context);
	}
}

if ( ! function_exists('logger'))
{
	function logger($level, $msg, $context = null)
	{
		return TestLogger::write($level, $msg, $context);
	}
}

class Log
{
	public static function write($level, $msg, $context = null)
	{
		return logger($level, $msg, $context);
	}
}

class Config
{
	protected static $items = array();

	public static function reset()
	{
		self::$items = array();
	}

	public static function load($file, $overwrite = false)
	{
		if ($file === 'email' && $overwrite)
		{
			self::$items['email'] = include DOCROOT.'config/email.php';
			return self::$items['email'];
		}

		if ($file === 'mimes')
		{
			return self::mimes();
		}

		return array();
	}

	public static function get($key, $default = null)
	{
		return Arr::get(self::$items, $key, $default);
	}

	public static function mimes()
	{
		return array(
			'png' => array('image/png', 'image/x-png'),
			'jpg' => array('image/jpeg', 'image/pjpeg'),
			'gif' => 'image/gif',
			'pdf' => array('application/pdf', 'application/x-download'),
			'txt' => 'text/plain',
		);
	}
}

class Arr
{
	public static function get($array, $key, $default = null)
	{
		if ( ! is_array($array) && ! ($array instanceof ArrayAccess))
		{
			throw new InvalidArgumentException('First parameter must be an array or ArrayAccess object.');
		}

		if (is_null($key))
		{
			return $array;
		}

		if (is_array($key))
		{
			$return = array();
			foreach ($key as $k)
			{
				$return[$k] = static::get($array, $k, $default);
			}
			return $return;
		}

		if (is_object($key))
		{
			$key = (string) $key;
		}

		if (is_object($array))
		{
			if (property_exists($array, $key))
			{
				return $array->$key;
			}
		}
		elseif (array_key_exists($key, $array))
		{
			return $array[$key];
		}

		foreach (explode('.', $key) as $key_part)
		{
			if (($array instanceof ArrayAccess && isset($array[$key_part])) === false)
			{
				if ( ! is_array($array) || ! array_key_exists($key_part, $array))
				{
					return Fuel::value($default);
				}
			}

			$array = $array[$key_part];
		}

		return $array;
	}

	public static function set(&$array, $key, $value = null)
	{
		if (is_null($key))
		{
			$array = $value;
			return;
		}

		if (is_array($key))
		{
			foreach ($key as $k => $v)
			{
				static::set($array, $k, $v);
			}
		}
		else
		{
			$keys = explode('.', $key);

			while (count($keys) > 1)
			{
				$key = array_shift($keys);

				if ( ! isset($array[$key]) || ! is_array($array[$key]))
				{
					$array[$key] = array();
				}

				$array =& $array[$key];
			}

			$array[array_shift($keys)] = $value;
		}
	}

	public static function delete(&$array, $key)
	{
		if (is_null($key))
		{
			return false;
		}

		if (is_array($key))
		{
			$return = array();
			foreach ($key as $k)
			{
				$return[$k] = static::delete($array, $k);
			}
			return $return;
		}

		$key_parts = explode('.', $key);

		if ( ! is_array($array) || ! array_key_exists($key_parts[0], $array))
		{
			return false;
		}

		$this_key = array_shift($key_parts);

		if ( ! empty($key_parts))
		{
			$key = implode('.', $key_parts);
			return static::delete($array[$this_key], $key);
		}

		unset($array[$this_key]);

		return true;
	}

	public static function keyval_to_assoc($array, $key_field, $val_field)
	{
		if ( ! is_array($array) && ! ($array instanceof Iterator))
		{
			throw new InvalidArgumentException('The first parameter must be an array.');
		}

		$output = array();
		foreach ($array as $key => $value)
		{
			$output[] = array(
				$key_field => $key,
				$val_field => $value,
			);
		}

		return $output;
	}

	public static function filter_keys($array, $keys, $remove = false)
	{
		$return = array();
		foreach ($keys as $key)
		{
			if (array_key_exists($key, $array))
			{
				if ( ! $remove)
				{
					$return[$key] = $array[$key];
				}
				if ($remove)
				{
					unset($array[$key]);
				}
			}
		}
		return $remove ? $array : $return;
	}

	public static function merge()
	{
		$array = func_get_arg(0);
		$arrays = array_slice(func_get_args(), 1);

		if ( ! is_array($array))
		{
			throw new InvalidArgumentException('Arr::merge() - all arguments must be arrays.');
		}

		foreach ($arrays as $arr)
		{
			if ( ! is_array($arr))
			{
				throw new InvalidArgumentException('Arr::merge() - all arguments must be arrays.');
			}

			foreach ($arr as $k => $v)
			{
				if (is_int($k))
				{
					array_key_exists($k, $array) ? $array[] = $v : $array[$k] = $v;
				}
				elseif (is_array($v) && array_key_exists($k, $array) && is_array($array[$k]))
				{
					$array[$k] = static::merge($array[$k], $v);
				}
				else
				{
					$array[$k] = $v;
				}
			}
		}

		return $array;
	}
}

class Str
{
	public static function starts_with($str, $start, $ignore_case = false)
	{
		if (PHP_VERSION_ID >= 80000)
		{
			return $ignore_case ? str_starts_with(strtolower($str), strtolower($start)) : str_starts_with($str, $start);
		}

		return (bool) preg_match('/^'.preg_quote($start, '/').'/m'.($ignore_case ? 'i' : ''), (string) $str);
	}
}

class Input
{
	public static function server($index, $default = null)
	{
		return isset($_SERVER[$index]) ? $_SERVER[$index] : $default;
	}
}

class Autoloader
{
	protected static $classes = array();

	public static function add_core_namespace($namespace)
	{
	}

	public static function add_classes(array $classes)
	{
		self::$classes = array_merge(self::$classes, $classes);
		spl_autoload_register(array(__CLASS__, 'load'));
	}

	public static function load($class)
	{
		$class = ltrim($class, '\\');

		if ( ! isset(self::$classes[$class]) && (strpos($class, 'Email_') === 0 || $class === 'Email'))
		{
			$namespaced = 'Email\\'.$class;
			if (isset(self::$classes[$namespaced]))
			{
				$class = $namespaced;
			}
		}

		if (isset(self::$classes[$class]))
		{
			$before = get_declared_classes();
			require self::$classes[$class];
			$after = get_declared_classes();
			foreach (array_diff($after, $before) as $declared)
			{
				if (strpos($declared, 'Email\\') === 0)
				{
					$alias = substr($declared, 6);
					if ($alias !== false && $alias !== '' && ! class_exists($alias, false))
					{
						class_alias($declared, $alias);
					}
				}
			}

			return true;
		}

		return false;
	}
}

if ( ! function_exists('call_fuel_func_array'))
{
	function call_fuel_func_array($callback, array $args)
	{
		if (is_string($callback) && strpos($callback, '::') !== false)
		{
			$callback = explode('::', $callback);
		}

		if (is_array($callback) && isset($callback[1]) && is_object($callback[0]))
		{
			$count = count($args);
			$args = array_values($args);
			list($instance, $method) = $callback;

			switch ($count)
			{
				case 0:
					return $instance->$method();
				case 1:
					return $instance->$method($args[0]);
				case 2:
					return $instance->$method($args[0], $args[1]);
				case 3:
					return $instance->$method($args[0], $args[1], $args[2]);
				case 4:
					return $instance->$method($args[0], $args[1], $args[2], $args[3]);
				default:
					return call_user_func_array(array($instance, $method), $args);
			}
		}

		return call_user_func_array($callback, $args);
	}
}

class MailgunMessages
{
	public $last_domain;
	public $last_post;

	public function send($domain, $post_data)
	{
		$this->last_domain = $domain;
		$this->last_post = $post_data;
		return array('id' => 'test');
	}
}

class MailgunMailgun
{
	public static $create_args = array();
	public static $instance;
	public $messages;

	public function __construct()
	{
		$this->messages = new MailgunMessages();
	}

	public function messages()
	{
		return $this->messages;
	}
}

class MandrillMessages
{
	public static $last_instance;

	public $last_payload;
	public $last_async;
	public $last_ip_pool;
	public $last_send_at;

	public function __construct($mandrill = null)
	{
		self::$last_instance = $this;
	}

	public function send($message_data, $async = false, $ip_pool = null, $send_at = null)
	{
		$this->last_payload = $message_data;
		$this->last_async = $async;
		$this->last_ip_pool = $ip_pool;
		$this->last_send_at = $send_at;
		return array(array('status' => 'sent'));
	}
}

class Mandrill
{
	public static $last_key;

	public function __construct($key)
	{
		self::$last_key = $key;
	}
}

class Mandrill_Messages extends MandrillMessages
{
}

class FuelHarness
{
	public static function resetSinks()
	{
		MailgunMailgun::$create_args = array();
		Mandrill::$last_key = null;
		TestLogger::clear();
	}

	public static function bootstrap()
	{
		require dirname(dirname(__DIR__)).'/bootstrap.php';
		Email\Email::_init();
	}
}
