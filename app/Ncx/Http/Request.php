<?php
namespace Ncx\Http;

use Slim\Http\Request as SlimRequest;

class Request extends SlimRequest
{
  public function input($param,$default = null)
  {
    $r = $this->getParams([$param]);
    if (empty($r))
      return $default;

    return $r[$param];
  }

  public function all()
  {
    return $this->getParams();
  }

  public function form()
  {
    $form = $this->getParams(['form']);
    if (isset($form['form']))
      return $form['form'];
    return [];
  }

  public function formInput($param,$default = null)
  {
    $form = $this->input('form');

    if ($form && isset($form[$param]))
      return $form[$param];
    return $default;
  }

  public function ajax()
  {
    return $this->isXhr();
  }

  /**
  * Returns client IP or null
  * @return nullable IP address
  */
  public function ip()
  {
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
        if (array_key_exists($key, $_SERVER) === true){
            foreach (explode(',', $_SERVER[$key]) as $ip){
                $ip = trim($ip);
                //if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                //    return $ip;
                //}
                return $ip;
            }
        }
    }
  }

  /**
  * Returns useragent or null
  * @return nullable User Agent
  */
  public function useragent()
  {
    $serverparams = $this->getServerParams();
    return isset($serverparams['HTTP_USER_AGENT']) ? trim($serverparams['HTTP_USER_AGENT']):null;
  }

}
