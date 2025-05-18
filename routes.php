<?php defined('DATALIFEENGINE') || die('Access denied!');

$orderDescUrl = empty($order_config["url"]) ? "ordedesc" : $order_config["url"];

$request = substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 1);
$routes = [
  '^' . $orderDescUrl . '/delete/([0-9]+)(/?)+$' => 'do=orderdesc&action=del&id=$1',
  '^' . $orderDescUrl . '/edit/([0-9]+)(/?)+$' => 'do=orderdesc&action=edit&id=$1',
  '^' . $orderDescUrl . '/add(/?)+$' => 'do=orderdesc&action=add',
  '^' . $orderDescUrl . '(/?)+$' => 'do=orderdesc',
];

foreach ($routes as $path => $query) {
  if (preg_match("'{$path}'is", $request, $match)) {
    foreach ($match as $index => $value) {
      $query = str_replace('$' . $index, $value, $query);
    }
    parse_str($query, $current);
    if (is_array($current)) {
      $_REQUEST = $_GET = $current;
    }
    break;
  }
}
