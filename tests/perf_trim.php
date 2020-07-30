<?php
  
  $list = [];
  
  for ($i = 0; $i < 10000; $i++) {
    $lastrandom = '';
    for ($a = 1; $a <= 8; $a++) {
      $lastrandom .= chr(ord('a') + mt_rand(0, 25));
    }
    $list[] = substr($lastrandom, 0, 4) . '=' . substr($lastrandom, 4);
  }
  
  $before = microtime(true);
  
  for ($i = 0; $i < 10000; $i++) {
    explode('=', $list[$i], 2);
  }
  
  $after = microtime(true);
  echo ($after - $before) . " sec\n";
  
  $before = microtime(true);
  
  for ($i = 0; $i < 10000; $i++) {
    $a = strpos($list[$i], '=');
    $v = substr($list[$i], 0, $a);
    $k = substr($list[$i], $a + 1);
  }
  
  $after = microtime(true);
  echo ($after - $before) . " sec\n";