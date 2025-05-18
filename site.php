<?php defined('DATALIFEENGINE') || die('Access denied!');

/*
=====================================================
 Олег Александрович a.k.a. Sander
-----------------------------------------------------
 http://nfhelp.ru/
-----------------------------------------------------
 Copyright (c) 2009-2012
=====================================================
*/

require DLEPlugins::Check(ENGINE_DIR . "/iCooLER/OrderDesc/init.php");

function getParserFilterInstance()
{
  include_once(DLEPlugins::Check(ENGINE_DIR . '/classes/htmlpurifier/HTMLPurifier.standalone.php'));
  include_once(DLEPlugins::Check(ENGINE_DIR . '/classes/parse.class.php'));

  return new \ParseFilter(array(), array(), 1, 1);
}

function orderDescRenderForm($action, $data = [])
{
  global $tpl, $order_cat_list;
  $parse = getParserFilterInstance();
  $tpl->load_template('modules/orderdesc/orderdesc_form.tpl');
  foreach ([
    "id", "title", "orig_title", "year", "descr",
    "autor", "date", "comment", "link", "status", "category"
  ] as $k) {
    if (!isset($data[$k])) {
      $data[$k] = "";
    }
  }
  $tpl->set("{id}", isset($data["id"]));
  $tpl->set("{title}", stripslashes($data['title']));
  $tpl->set("{orig_title}", stripslashes($data['orig_title']));
  $tpl->set("{year}", $data['year']);
  $tpl->set("{descr}", $parse->decodeBBCodes($data['descr'], false));
  $tpl->set("{autor}", stripslashes($data['autor']));
  $tpl->set("{date}", $data['date']);
  $tpl->set("{comment}", stripslashes($data['comment']));
  $tpl->set("{link}", stripslashes($data['link']));
  $status = array();
  $status[$data['status']] = " checked";
  $tpl->set("{status}", "
  <div class=\"checkbox form-check switch mt-2 mb-4 d-flex flex-wrap align-items-center gap-2\"><input type=\"checkbox\" name=\"status\" id=\"wait\" value=\"0\"{$status['wait']}><label for=\"wait\">Ожидает</label><input type=\"checkbox\" name=\"status\" id=\"work\" value=\"1\"{$status['work']}><label for=\"work\">В работе</label><input type=\"checkbox\" name=\"status\" id=\"done\" value=\"2\"{$status['done']}><label for=\"done\">Готово</label><input type=\"checkbox\" name=\"status\" id=\"deny\" value=\"3\"{$status['deny']}><label for=\"deny\">Отказано</label></div>");
  $catlist = "";
  foreach ($order_cat_list as $k => $v) {
    if ($data['category'] == $k) $catlist .= "<option value=\"{$k}\" selected>{$v["cat_name"]}</option>";
    else $catlist .= "<option value=\"{$k}\">{$v["cat_name"]}</option>";
  }
  $tpl->set("{catlist}", $catlist);

  $yearlist = "";
  foreach ([""] + range((int) date('Y') + 2, 1970) as $k) {
    if ($data['year'] == $k) $yearlist .= "<option value=\"{$k}\" selected>{$k}</option>";
    else $yearlist .= "<option value=\"{$k}\">{$k}</option>";
  }
  $tpl->set("{yearlist}", $yearlist);

  $tpl->set_block("'\\[add\\](.*?)\\[/add\\]'is", $action == "add" ? "\\1" : "");
  $tpl->set_block("'\\[edit\\](.*?)\\[/edit\\]'is", $action == "edit" ? "\\1" : "");
  $tpl->compile('content');
  $tpl->clear();
  $tpl->result["content"] = '<form method="post">' . $tpl->result["content"] . '<input type="hidden" name="orderdesc" value="do' . $action . '"/><input type="hidden" name="id" value="' . $data["id"] . '" /></form>';
}

if (stripos($_SERVER["REQUEST_URI"], "do=orderdesc")) {
  http_response_code(301);
  @header("Location: /{$orderDescUrl}");
  die("Redirect");
}

foreach (["title", "description", "keywords"] as $k) {
  $metatags[$k] = empty($order_config[$k]) ? "Стол заказов" : $order_config[$k];
}

$metatags["header_title"] = $metatags["title"];

$id = intval($_REQUEST['id']);
if ($id < 0) $id = 0;
$action = stripslashes($_REQUEST['action']);
unset($_GET['action'], $_GET['id']);

$orderdesc = isset($_POST["orderdesc"]) ? $_POST["orderdesc"] : "";

if ($action == "add" && $orderdesc == "") {
  orderDescRenderForm("add");
} elseif ($action == "add" && $orderdesc == "doadd") {
  $stop = "";
  $added_time = time();
  $thistime = date("Y-m-d H:i:s", $added_time);
  if ($is_logged and $order_config['add_limit'] and !$order_config['allow_guest']) {
    $todaycount = $db->super_query("SELECT count(*) as c FROM " . PREFIX . "_orderdesc WHERE autor='" . $db->safesql($member_id['name']) . "' AND date>'{$thistime}' - INTERVAL 1 DAY");
    if ($todaycount['c'] > $order_config['add_limit']) $stop .= "<li>Вы достигли лимита разрешенного количества добавленных заявок в сутки</li>";
  }
  $parse = getParserFilterInstance();
  if ($order_config['block_links']) $parse->allow_url = false;
  $descr = $db->safesql($parse->BB_Parse($parse->process($_POST['descr']), false));
  if ($order_config['block_links'] and stripos($descr, "http://") !== false) $stop .= "<li>В описании запрещено писать ссылки</li>";
  if (!$_POST['title'] and $_POST['orig_title']) {
    $_POST['title'] = $_POST['orig_title'];
    unset($_POST['orig_title']);
  }
  $title = $db->safesql($parse->process(trim(strip_tags($_POST['title']))));
  if ($order_config['block_links'] and stripos($title, "http://") !== false) $stop .= "<li>В заголовке запрещено писать ссылки</li>";
  if (dle_strlen($title, $config['charset']) < $order_config['min_title_length']) $stop .= "<li>Вы ввели слишком короткое название</li>";
  $category = intval($_POST['category']);
  if (!$order_cat_list[$category]) $stop .= "<li>Вы выбрали не существующую категорию</li>";
  if (!$is_logged and !$order_config['allow_guest']) $stop .= "<li>Оставлять заявки могут только авторизованные пользователи</li>";
  $orig_title = $db->safesql($parse->process(trim(strip_tags($_POST['orig_title']))));
  if ($order_config['block_links'] and stripos($orig_title, "http://") !== false) $stop .= "<li>В оригинальном названии запрещено писать ссылки</li>";
  if (!$stop) {
    $year = intval($_POST['year']);
    $autor = $db->safesql($member_id['name']);
    $db->query("INSERT INTO " . PREFIX . "_orderdesc (title,orig_title,descr,category,autor,date,year) VALUES ('{$title}','{$orig_title}','{$descr}','{$category}','{$autor}','{$thistime}','{$year}')");
    http_response_code(301);
    @header("Location: /{$orderDescUrl}");
    die("Redirect");
  } else msgbox("Проблемка", "<ul>{$stop}</ul><br /><a href=\"javascript:history.go(-1)\">Назад</a>");
} elseif ($action == 'del' and $id) {
  if ($is_logged and $user_group[$member_id['user_group']]['allow_all_edit']) {
    $db->query("DELETE FROM " . PREFIX . "_orderdesc WHERE id={$id}");
    msgbox("Готово!", "Выбранная вами заявка успешно удалена<br /><a href=\"/{$orderDescUrl}\">Назад</a>");
  } else msgbox("Запрещено", "Вам запрещен доступ на эту страницу<br /><a href=\"javascript:history.go(-1)\">Назад</a>");
} elseif ($action == "edit" && $orderdesc == 'doedit' and $id) {
  if ($is_logged and $user_group[$member_id['user_group']]['allow_all_edit']) {
    $row = $db->super_query("SELECT * FROM " . PREFIX . "_orderdesc WHERE id={$id}");
    if ($row['id']) {
      $stop = "";
      $parse = getParserFilterInstance();
      $descr = $db->safesql($parse->BB_Parse($parse->process($_POST['descr']), false));
      $title = $db->safesql($parse->process(trim(strip_tags($_POST['title']))));
      if (dle_strlen($title, $config['charset']) < $order_config['min_title_length']) $stop .= "<li>Вы ввели слишком короткое название</li>";
      $category = intval($_POST['category']);
      if (!$order_cat_list[$category]) $stop .= "<li>Вы выбрали не существующую категорию</li>";
      if ($_POST['status'] < 0 or $_POST['status'] > 4) $stop .= "<li>Вы выбрали не существующий статус</li>";
      if (!$stop) {
        $orig_title = $db->safesql($parse->process(trim(strip_tags($_POST['orig_title']))));
        $link = $db->safesql($parse->process(trim(strip_tags($_POST['link']))));
        $autor = $db->safesql($parse->process(trim(strip_tags($_POST['autor']))));
        $year = intval($_POST['year']);
        $starr = array(0 => 'wait', 1 => 'work', 2 => 'done', 3 => 'deny');
        $status = $starr[intval($_POST['status'])];
        if ($status != $row['status'] and $autor) {
          $user = $db->super_query("SELECT user_id,email FROM " . USERPREFIX . "_users WHERE name='{$autor}'");
          $subj = "Изменен статус вашей заявки";
          $titles = stripslashes($title);
          if ($status == 'work') $sts = "Мы занялись поиском вашей заявки, в ближашее время она будет выполнена";
          elseif ($status == 'done') $sts = "Ваша заявка выполнена, можете скачать по ссылке: <a href=\"{$link}\" target=\"_blank\">{$link}</a>";
          elseif ($status == 'deny') $sts = "Ваша заявка отклонена";
          else $sts = "Ваша заявка перенесена в ожидающие";
          if ($comment) $sts .= "\n\nКомментарий: {$comment}";
          if ($user['email'] and $order_config['inform_email']) {
            include_once ENGINE_DIR . '/classes/mail.class.php';
            $mail = new dle_mail($config, true);
            $text = <<<HTML
Здравствуйте, {$autor}!<br/>
<br/>
На сайте {$config['http_home_url']} вы оставляли заявку:<br/>
{$titles}<br/>
<br/>
{$sts}<br/>
<br/>
С уважением,<br/>
Администрация сайта
HTML;
            $mail->send($user['email'], $subj, $text);
          }
          if ($order_config['inform_pm']) {
            $text = <<<HTML
Информируем вас о статусе вашей заявки:<br/>
{$titles}<br/><br/>
{$sts}<br/><br/>
С уважением,<br/>
Администрация сайта
HTML;
            $db->query("INSERT INTO " . USERPREFIX . "_pm (subj, text, user, user_from, date, pm_read, folder) values ('{$subj}', '$text', '{$user['user_id']}', 'admin', '{$_TIME}', '0', 'inbox')");
            $db->query("UPDATE " . USERPREFIX . "_users SET pm_all=pm_all+1, pm_unread=pm_unread+1 WHERE user_id='{$user['user_id']}'");
          }
        }
        $date = date("Y-m-d H:i:s", strtotime($_POST['date']));
        $comment = $db->safesql($parse->process(trim(strip_tags($_POST['comment']))));
        $db->query("UPDATE " . PREFIX . "_orderdesc set title='{$title}',orig_title='{$orig_title}',link='{$link}',descr='{$descr}',category='{$category}',autor='{$autor}',date='{$date}',year='{$year}',status='{$status}',comment='{$comment}' WHERE id={$id}");
        msgbox("Готово!", "Изменения в заказе успешно сохранены<br /><a href=\"/{$orderDescUrl}/edit/{$id}\">Продолжить редактирование</a><br /><a href=\"/{$orderDescUrl}\">На главную</a>");
      } else msgbox("Проблемка", "<ul>{$stop}</ul><br /><a href=\"javascript:history.go(-1)\">Назад</a>");
    } else msgbox("Ошибка", "Выбранный заказ не найден или удален<br /><a href=\"javascript:history.go(-1)\">Назад</a>");
  } else msgbox("Запрещено", "Вам запрещен доступ на эту страницу<br /><a href=\"javascript:history.go(-1)\">Назад</a>");
} elseif ($action == 'edit' and $id) {
  if ($is_logged and $user_group[$member_id['user_group']]['allow_all_edit']) {
    $row = $db->super_query("SELECT * FROM " . PREFIX . "_orderdesc WHERE id={$id}");
    if ($row['id']) {
      orderDescRenderForm("edit", $row);
    } else msgbox("Ошибка", "Выбранный заказ не найден или удален<br /><a href=\"javascript:history.go(-1)\">Назад</a>");
  } else msgbox("Запрещено", "Вам запрещен доступ на эту страницу<br /><a href=\"javascript:history.go(-1)\">Назад</a>");
} else {
  $orderby = $filters = $searchqueries = "";
  $where = $url = array();
  $cstart = intval($_GET['page']);
  if (isset($_GET['search'])) {
    function strip_data($text)
    {
      $quotes = array("\x60", "\t", "\n", "\r", ",", ";", ":", "[", "]", "{", "}", "=", "*", "^", "%", "$", "<", ">");
      $goodquotes = array("-", "+", "#", "'", '"');
      $repquotes = array("\-", "\+", "\#", "\'", '\"');
      $text = stripslashes($text);
      $text = trim(strip_tags($text));
      $text = str_replace($quotes, '', $text);
      $text = str_replace($goodquotes, $repquotes, $text);
      return $text;
    }
    $story = dle_substr(strip_data(rawurldecode($_GET['search'])), 0, 50, $config['charset']);
    if (dle_strlen($story, $config['charset']) > 2) $where[] = "(title LIKE '%{$story}%' OR orig_title LIKE '%{$story}%')";
    else unset($_GET['search']);
  } else $story = "";
  unset($_GET['do'], $_GET['page']);
  if (count($_GET)) {
    foreach ($_GET as $k => $v) $url[$k] = "{$k}={$v}";
    foreach ($url as $k => $v) {
      if ($k == 'do') continue;
      $turl = $url;
      unset($turl[$k]);
      $v = explode("=", $v);
      $v = end($v);
      $tsort = "<i class=\"orderdesc-filter\"></i>";
      if ($k == 'search') {
        $tsort = "<i class=\"orderdesc-find\"></i>";
      } elseif ($k == 'sort') {
        if ($v == 'title') $orderby = $v . " ASC, ";
        elseif ($v == 'year' or $v == 'rating') $orderby = $v . " DESC, ";
        if ($v == 'title') $v = "Название";
        elseif ($v == 'year') $v = "Год релиза";
        elseif ($v == 'rating') $v = "Рейтинг";
        else continue;
        $tsort = "<i class=\"orderdesc-sort\"></i>";
      } elseif ($k == 'stats') {
        if (in_array($v, array('wait', 'done', 'deny', 'work'))) $where[] = "status='{$v}'";
        if ($v == 'wait') $v = "Заявки: Ожидают";
        elseif ($v == 'done') $v = "Заявки: Выполненные";
        elseif ($v == 'work') $v = "Заявки: В работе";
        elseif ($v == 'deny') $v = "Заявки: Отклоненные";
        else continue;
      } elseif ($k == 'autor') {
        if (!$v) continue;
        $where[] = "autor='" . $db->safesql($v) . "'";
        $v = "Заказчик: {$v}";
      } elseif ($k == 'year') {
        $where[] = "year='" . intval($v) . "'";
        $v = "Год релиза: {$v}";
      } elseif ($k == 'cat') {
        if ($order_cat_list[$v]) {
          $where[] = "category='" . intval($v) . "'";
          $v = "Категория: {$order_cat_list[$v]["cat_name"]}";
        } else continue;
      }
      if (count($turl)) $filters .= "<a href=\"" . $config['http_home_url'] . "{$orderDescUrl}?" . implode("&amp;", $turl) . "\">{$tsort}<u></u>{$v}</a>";
      else $filters .= "<a href=\"" . $config['http_home_url'] . "{$orderDescUrl}\">{$tsort}<u></u>{$v}</a>";
    }
  }
  $orderby .= "date DESC";
  $where = implode(" AND ", $where);
  if ($where) $where = "WHERE " . $where;
  if ($cstart < 1) $cstart = 1;
  $limit = intval($order_config['limit']);
  if ($limit < 1) $limit = 10;
  $dbstart = ($cstart - 1) * $limit;
  $sql = $db->query("SELECT * FROM " . PREFIX . "_orderdesc {$where} ORDER BY {$orderby} LIMIT {$dbstart},{$limit}");
  $tpl->load_template('modules/orderdesc/orderdesc_list.tpl');
  $i = 0;
  while ($row = $db->get_row($sql)) {
    $i++;
    $tpl->set("{id}", $row['id']);
    $tpl->set("{edit-url}", "/{$orderDescUrl}/edit/{$row['id']}");
    $tpl->set("{del-url}", "/{$orderDescUrl}/delete/{$row['id']}");
    if ($row['rating'] > 0) $rateclass = "plus";
    else $rateclass = "null";
    if (in_array($member_id['user_group'], $order_config['allow2vote']))
      $tpl->set("{rating}", "<a href=\"#\" onclick=\"orderdecs_rate({$row['id']});return false;\" id=\"orderdesc-rating-{$row['id']}\" class=\"orderdesc-rating-{$rateclass}\" title=\"Поддержать\">{$row['rating']}</a>");
    else
      $tpl->set("{rating}", "<span class=\"orderdesc-rating-{$rateclass}\">{$row['rating']}</span>");
    $row['title'] = stripslashes($row['title']);
    if ($row['link']) $row['title'] = "<a href=\"{$row['link']}\">{$row['title']}</a>";
    $tpl->set("{stats}", $row['status']);
    switch ($row['status']) {
      case "done":
        $stats_title = "Заявка выполнена";
        break;
      case "deny":
        $stats_title = "Заявка отклонена";
        break;
      case "work":
        $stats_title = "Заявка в работе";
        break;
      default:
        $stats_title = "Заявка ожидает";
    }
    if ($row['comment']) $stats_title .= ". " . stripslashes($row['comment']);
    $tpl->set("{stats_title}", $stats_title);
    $tpl->set("{title}", $row['title']);
    if ($row['descr']) {
      $tpl->set("{descr}", stripslashes($row['descr']));
      $tpl->set("[descr]", "");
      $tpl->set("[/descr]", "");
    } else $tpl->set_block("#\\[descr\\].+?\\[/descr\\]#is", "");
    if ($row['orig_title']) {
      $tpl->set("{alt-title}", stripslashes($row['orig_title']));
      $tpl->set("[alt-title]", "");
      $tpl->set("[/alt-title]", "");
    } else $tpl->set_block("#\\[alt-title\\].+?\\[/alt-title\\]#is", "");
    $row['category'] = intval($row['category']);
    $row['category'] = "<a href=\"/{$orderDescUrl}?cat={$row['category']}\">{$order_cat_list[$row['category']]["cat_name"]}</a>";
    $tpl->set("{category}", $row['category']);
    if ($row['autor']) {
      $tpl->set("{autor}", "<a href=\"/{$orderDescUrl}?autor=" . urlencode($row['autor']) . "\">{$row['autor']}</a>");
      if ($config['allow_alt_url'] and $config['allow_alt_url'] != "no") $tpl->set("{autor-link}", $config['http_home_url'] . "user/" . urlencode($row['autor']) . "/");
      else $tpl->set("{autor-link}", "{$PHP_SELF}?subaction=userinfo&amp;user=" . urlencode($row['autor']));
      $tpl->set('[autor]', '');
      $tpl->set('[/autor]', '');
    } else {
      $tpl->set("{autor}", $order_config['guest']);
      $tpl->set_block("#\\[autor\\].+?\\[/autor\\]#is", "");
    }
    $row['date'] = strtotime($row['date']);
    $tpl->set("{date}", date("d.m.Y", $row['date']));
    $row['year'] = intval($row['year']);
    if ($row['year'] > 0) $tpl->set("{year}", "<a href=\"/{$orderDescUrl}?year={$row['year']}\">{$row['year']}</a>");
    else $tpl->set("{year}", "");
    if ($is_logged and $user_group[$member_id['user_group']]['allow_all_edit']) {
      $tpl->set('[edit]', '');
      $tpl->set('[/edit]', '');
    } else $tpl->set_block("#\\[edit\\].+?\\[/edit\\]#is", "");
    $tpl->compile('list');
  }
  $db->free();
  $tpl->clear();
  foreach ($url as $v) {
    $v = explode("=", $v);
    if ($v[0] != 'search') $searchqueries .= "<input type=\"hidden\" name=\"{$v[0]}\" value=\"{$v[1]}\" />";
  }
  $url = $config['http_home_url'] . "index.php?" . implode("&amp;", $url);
  $tpl->load_template('modules/orderdesc/orderdesc.tpl');
  $tpl->set("{catlist}", $catlist);
  if (!$tpl->result['list']) $tpl->result['list'] = "<tr><td colspan=\"10\" style=\"text-align:center;\">Ничего не найдено</td></tr>";
  $tpl->set('{list}', $tpl->result['list']);
  $tpl->set('{searchqueries}', $searchqueries);
  if ($filters) {
    $tpl->set('{filters}', $filters);
    $tpl->set('[filters]', '');
    $tpl->set('[/filters]', '');
  } else $tpl->set_block("#\\[filters\\](.+?)\\[/filters\\]#is", "");

  if ($i >= $limit or $dbstart > 0) {
    $count = $db->super_query("SELECT count(*) as c FROM " . PREFIX . "_orderdesc {$where}");
    $count = $count['c'];
  } else $count = 0;
  if ($count > $limit) {
    $spread = 4;
    $end_page = ceil($count / $limit);
    if ($cstart > $end_page) $cstart = $end_page;
    if ($cstart < 2) $navigation = "<span><<</span><span><</span>";
    else {
      $navigation = "<a href=\"{$url}\" title=\"В начало\"><<</a>";
      $prev = $cstart - 1;
      if ($prev > 1) $navigation .= "<a href=\"{$url}?page={$prev}\" title=\"Назад\"><</a>";
      else $navigation .= "<a href=\"{$url}\" title=\"Назад\"><</a>";
    }
    $start = $cstart - $spread;
    $end = min($cstart + $spread, $end_page);
    if ($end == $end_page - 1) $end = $end_page;
    if ($end_page <= ($spread * 2 + 1)) {
      $start = 1;
      $end = $end_page;
    } elseif ($start < 3) $start = 1;
    else $navigation .= "<b>...</b>";
    for ($i = $start; $i <= $end; $i++) {
      if ($cstart == $i) $navigation .= "<span>{$i}</span>";
      else {
        if ($i > 1) $navigation .= "<a href=\"{$url}?page={$i}\">{$i}</a>";
        else $navigation .= "<a href=\"{$url}\">{$i}</a>";
      }
    }
    if ($end_page > $cstart + $spread and $end != $end_page) $navigation .= "<b>...</b>";
    if ($cstart == $end_page) $navigation .= "<span>></span><span>>></span>";
    elseif ($cstart < $end_page) {
      $next = min($cstart + 1, $end_page);
      $navigation .= "<a href=\"{$url}?page={$next}\" title=\"Вперед\">></a><a href=\"{$url}?page={$end_page}\" title=\"В конец\">>></a>";
    }
    $tpl->set('{navigation}', $navigation);
    $tpl->set('[navigation]', '');
    $tpl->set('[/navigation]', '');
  } else $tpl->set_block("#\\[navigation\\](.+?)\\[/navigation\\]#is", "");

  if ($order_config['allow_guest'] or $is_logged) {
    $tpl->set('[guest]', '');
    $tpl->set('[/guest]', '');
    $catlist = "";
    foreach ($order_cat_list as $k => $v) $catlist .= "<option value=\"{$k}\">{$v["cat_name"]}</option>";
    $tpl->set('{catlist}', $catlist);
  } else $tpl->set_block("#\\[guest\\](.+?)\\[/guest\\]#is", "");

  $tpl->set("{add-url}", "/{$orderDescUrl}/add");

  $tpl->compile('content');
  $tpl->clear();
}
