<?php

if (!function_exists('jwt_data'))
{
  function jwt_data($request)
  {
    $sent_jwt_token = $request->getHeaderLine('Authorization');
    try{
      $decoded = Firebase\JWT\JWT::decode($sent_jwt_token, config('jwt')['secret'], array('HS256'));
      return (array)$decoded;
    } catch (\Exception $e) {
      return null;
    }
    return null;
  }
}

if (!function_exists('cache')) {
  function cache()
  {
    global $container;
    return $container->get('cache');
  }
}


if (!function_exists('format_bytes')) {
  function format_bytes($size)
  {
      $unit=array('B','KB','MB','GB','TB','PB');
      return @round($size/pow(1024,($i=floor(log($size,1024)))),2).$unit[$i];
  }
}


if (!function_exists('to09')) {
  function to09($str)
  {
      $r = preg_replace("/[^0-9]+/", "", $str);
      return ($r == "") ? '' : (int)preg_replace("/[^0-9]+/", "", $str);
  }
}

if (!function_exists('toaz')) {
  function toaz($str)
  {
  	return preg_replace("/[^a-zA-Z]+/", "", $str);
  }
}
if (!function_exists('toaz09')) {
  function toaz09($str)
  {
  	return preg_replace("/[^a-zA-Z0-9]+/", "", $str);
  }
}

if (!function_exists('config'))
{
  function config($namespace)
  {
    global $container;
    return $container->get('settings')[$namespace];
  }
}

if (!function_exists('glob_recursive')) {
    /**
     * Find pathnames matching a pattern recursively
     *
     * @param $pattern
     * @param int $flags
     * @return array
     */
    function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, glob_recursive($dir . '/' . basename($pattern), $flags));
        }
        return $files;
    }
}
