<?

session_start();

require('JsHttpRequest.php');

if($up_sz > 1 && $uri_parts[1] == 'do-login')
{
    if($_POST['login'] == $ADM_LOGIN && $_POST['password'] == $ADM_PASSWORD)
    {
        $_SESSION['logined'] = true;
    }else
    {
        
    }
}

if(!isset($_SESSION['logined']))
{
    app_error('Доступ запрещен.<br><br>
              
              <form action="admin/do-login/" method="POST">
              
              <input name="login" style="width: 200px;" /><br>
              <input name="password" type="password" style="width: 200px;" /><br>
              
              <input type="submit" value="зайти"  style="width: 200px;">
              
              </form>');
}

array_shift($uri_parts);

define('IPBLOCK_REG', '/#ipblock(.*)#\\/ipblock/isU');

function get_ip_list()
{
    preg_match(IPBLOCK_REG, file_get_contents(ROOT_DIR.'/.htaccess'), $matches);
    
    return array_map('trim',str_replace('deny from', '', array_values(array_filter(explode("\n", $matches[1])))));
}

function add_new_ip($list, $ip)
{
    $ip = explode('.', $ip);
    $ip = array_map('intval', $ip);
    if(!sizeof($ip) || !$ip[0] || sizeof($ip) > 4)
    {
        echo 'В IP-адресе можно указывать как минимум 1 цифру и как максимум -- 4';
        return $list;
    }
    
    foreach($ip as $v) if($v < 0 || $v > 254)
    {
        echo 'В IP-адресе могут быть лишь цифры от 0 до 254 включительно.';
        return $list;
    }
    
    array_push($list, implode('.',$ip));
    
    return $list;
}

function remove_ip($list, $ip)
{
    foreach($list as $k=>$v) if($v == $ip) unset($list[$k]);
    
    return array_values($list);
}

function write_ip_list($list)
{
    $cont = file_get_contents(ROOT_DIR.'/.htaccess');
    
    foreach($list as $k=>$v) if($v) $list[$k] = 'deny from '.$v;
    
    $list = implode("\n", $list);
    
    $cont = preg_replace(IPBLOCK_REG, "#ipblock\n\n".$list."\n\n#/ipblock", $cont);
    
    $fp = fopen(ROOT_DIR.'/.htaccess', 'wb');
    fputs($fp, $cont);
    fclose($fp);
    
    return true;
}

if(@$uri_parts[0] == 'ajax')
{
    require('ajax-actions.php');
    exit();
}

define('ADM_TYPE_INPUT_TEXT',     0);
define('ADM_TYPE_INPUT_PASSWORD', 1);
define('ADM_TYPE_INPUT_SELECT',   2);
define('ADM_TYPE_TEXTAREA',       3);
define('ADM_TYPE_CHECKBOX',       4);

function echo_tr($fieldname, $value, $descr = '', $type = ADM_TYPE_INPUT_TEXT, $arg1 = null, $arg2 = null)
{
    echo '<tr><td class="edit_field">'.$descr.'</td><td class="edit_field" width="600">';
    
    switch($type)
    {
        case ADM_TYPE_INPUT_TEXT:
        case ADM_TYPE_INPUT_PASSWORD:
            
            echo "<input type='".($type == ADM_TYPE_INPUT_TEXT ? 'text' : 'password')."' name='$fieldname' value=\"".htmlspecialchars($value)."\" />";
            break;
        
        case ADM_TYPE_INPUT_SELECT:
            
            echo $arg1;
            break;
        
        case ADM_TYPE_TEXTAREA:
            
            echo "<textarea style='width: 100%;' name='$fieldname' rows=".($arg1 ? $arg1 : 10).">".htmlspecialchars($value).'</textarea>';
            break;
        
        case ADM_TYPE_CHECKBOX:
            
            echo "<input type='hidden' name='$fieldname' value=\"\">";
            echo "<input type='checkbox' name='$fieldname' value='1'".($value ? ' checked="checked"' : '')." style='width: auto;'>";
            break;
    }
    
    echo '</td></tr>';
}

function show_ad_edit_form($res, $action)
{
    global $UPL_IMG_ROOT;
    
    ?>
    
    <h3><a href="javascript:history.back(1);">&laquo; Вернуться назад</a></h3>
    
    <h3>Редактирование объявления</h3>
    
    <form action="<?=$action?>/" method="POST">
        <input type="hidden" name="id" value="<? echo intval($res['id']); ?>" />
        <input type="hidden" name="referer" value="<? echo $_SERVER['HTTP_REFERER'] ?>">
        
        <table>
            
            <?
            
            echo_tr('fio', $res['fio'], 'ФИО / Имя организации');
            echo_tr('email', $res['email'], 'E-mail');
            echo_tr('phone', $res['phone'], 'Контактный телефон');
            echo_tr('icq', $res['icq'], 'ICQ');
            echo_tr('url', $res['url'], 'Веб-сайт');
            
            echo_tr(null, null, 'Город', ADM_TYPE_INPUT_SELECT, cities_select($res['city']));
            echo_tr(null, null, 'Раздел', ADM_TYPE_INPUT_SELECT, category_select($res['scat_id']));
            
            echo_tr('header', $res['header'], 'Заголовок');
            echo_tr('body_short', $res['body_short'], 'Краткое описание', ADM_TYPE_TEXTAREA);
            echo_tr('body', $res['body'], 'Полное описание', ADM_TYPE_TEXTAREA, 40);
            
            ?>
            
            <tr>
                <td class="edit_field">
                    Иллюстрации:
                    <br><small>(отметьте для удаления)</small>
                </td>
                
                <td class="edit_field">
                    <?
                    
                    $r = '../'.$UPL_IMG_ROOT.'/';
                    
                    $i = 0;
                    
                    foreach(explode(' ','img add_img1 add_img2 add_img3') as $v)
                    {
                        if(empty($res[$v])) continue;
                        
                        $i++;
                        
                        $t = explode(';', $res[$v]);
                        
                        echo '<table><tr><td>';
                        
                        if(sizeof($t) == 2)
                        {
                            echo '<a href="'.$r.$t[1].'" target="_blank"><img src="'.$r.$t[0].'" /></a>';
                        }else
                        {
                            echo '<img src="'.$r.$t[0].'" />';
                        }
                        
                        echo '</td><td><input type="checkbox" name="delete_img[]" value="'.$v.'"></td></tr></table>'.(sizeof($t) == 1 ? '<hr>' : '');
                    }
                    
                    if(!$i) echo 'Нет иллюстраций';
                    
                    ?>
                </td>
            </tr>
            
        </table>
        
        <br><button type="submit"><b>внести изменения</b></button>
    </form>
    
    <?
}

$CONFIG_KEYS = array(
    'ADM_LOGIN' => 'Логин',
    'ADM_PASSWORD' => 'Пароль',
    'MAX_IMG_WIDTH' => 'Максимальная ширина прикрепляемого изображения',
    'MAX_IMG_HEIGHT' => 'Максимальная высота прикрепляемого изображения',
    'UPL_IMG_ROOT' => 'Папка с загруженными изображениями'.( !is_writable(ROOT_DIR.'/'.$UPL_IMG_ROOT) ? '<br>(<span class="warn">поставьте права на запись на эту папку!</span>)' : '' ),
    'ADS_PER_PAGE_INDEX' => 'Количество объявлений на страницу на главной',
    'ADS_PER_PAGE' => 'Количество объявлений на страницу при просмотре<br>списка объявления',
    'ADS_PER_PAGE_AD_VIEW' => 'Количество объявлений на страницу при просмотре<br>объявления',
    'ADS_PER_PAGE_AD_OLD' => 'Количество старых (старше текущего) объявлений<br>на страницу при просмотре объявления',
    'MAX_PAGES' => 'Максимальное количество страниц',
);

function connected_to_mysql()
{
    global $SQL;
    
    if(!$SQL->connected) echo 'Отсутствует подключение к MySQL. Зайдите в <a href="settings/">Настройки</a> и установите корректные значения.';
    
    return $SQL->connected;
}

if($PREMODERATE && $SQL->connected) extract( $SQL->res('SELECT COUNT(*) as `TOTAL_PENDING` FROM `#ads_pending` WHERE 1') );;

ob_start();

switch(@$uri_parts[0])
{
    case 'settings':

        ?>
        
        <div class="attention">
            <small>Значения не могут содержать кавычки, переводы строк и символ [\].</small>
            
            <? if(!is_writable(ROOT_DIR.'/config.php')) echo '<br><br><span class="warn">Файл config.php должен иметь права на запись, чтобы Вы могли редактировать настройки</span>'; ?>
        </div>
        
        <form action="apply-change-settings/" method="POST">
            
            <table>
            
            <tr>
                <td colspan="2" class="header">Настройки MySQL <? if(!$SQL->connected) echo ' (<span class="warn">необходимо поставить правильные значения!</span>)'; ?></td>
            </tr>
            
            <?
            
            echo_tr('mysql[host]', $mysql['host'], 'Хост');
            echo_tr('mysql[user]', $mysql['user'], 'Имя пользователя');
            echo_tr('mysql[pass]', $mysql['pass'], 'Пароль');
            echo_tr('mysql[db]', $mysql['db'], 'База данных');
            echo_tr('mysql[prefix]', $mysql['prefix'], 'Префикс для имён таблиц');
            
            ?>
            
            <tr>
                <td colspan="2" class="header">Настройки входа</td>
            </tr>
            
            <?
            
            echo_tr('ADM_LOGIN', $ADM_LOGIN, 'Логин');
            echo_tr('ADM_PASSWORD', $ADM_PASSWORD, 'Пароль', ADM_TYPE_INPUT_PASSWORD);
            
            ?>
            
            <tr>
                <td colspan="2" class="header">Другие настройки</td>
            </tr>
            
            <?
            foreach($CONFIG_KEYS as $k=>$v)
            {
                if($k == 'ADM_LOGIN' || $k == 'ADM_PASSWORD') continue;
                
                echo_tr($k, $$k, $v);
            }
            
            echo_tr('PREMODERATE', $PREMODERATE, 'Премодерация объявлений', ADM_TYPE_CHECKBOX);
            ?>
            
            </table>
            
            
            <br><button type="submit"><b>сохранить</b></button>
        </form>
        
        
        <?

        break;
    case 'apply-change-settings':
        
        // на самом деле, ограничение на вводимые символы можно и убрать, но,
        // для надёжности, символы, которые могут потенциально повредить файл config.php
        // вырезаются
        
        function clean_config_value($v)
        {
            return str_replace(array('"', "'", '\\', "\n", "\r"), '', $v);
        }
        
        foreach($_POST as $k=>$v)
        {
            if(is_array($v))
            {
                $_POST[$k] = array_map('clean_config_value', $v);
            }else
            {
                $_POST[$k] = clean_config_value($v);
            }
        }
        
        if(!is_writable(ROOT_DIR.'/config.php'))
        {
            die('Файл config.php недоступен на запись. Поставьте соответствующие права на этот файл и попробуйте заново.');
        }
        
        $fp = fopen(ROOT_DIR.'/config.php', 'wb');
        
        fputs($fp, '<'.'?
/* MySQL settings */

$mysql = array(
');
        
        foreach($_POST['mysql'] as $k=>$v) fputs($fp, "\t'$k' => '$v',\n");
        
        fputs($fp, ");

/* Настройки входа */

\$ADM_LOGIN = '{$_POST['ADM_LOGIN']}';
\$ADM_PASSWORD = '{$_POST['ADM_PASSWORD']}';

/* Разное */

");
        
        foreach($_POST as $k=>$v)
        {
            if($k == 'mysql' || $k == 'ADM_LOGIN' || $k == 'ADM_PASSWORD' || $k == 'PREMODERATE') continue;
            
            fputs($fp, "\$$k = '$v'; // {$CONFIG_KEYS[$k]}\n");
        }
        
        fputs($fp, '$PREMODERATE = '.($_POST['PREMODERATE'] ? 'true' : 'false')."; // Премодерация объявлений\n");
        
        fputs($fp, "\n?".">");
        
        fclose($fp);
        
        header('location: '.$CUR_DIR.'admin/settings/');
        
        die();
        
        break;
    case 'categories':
        if(!connected_to_mysql()) break;
        
        $cats = $scats = array();
        
        $sel = $SQL->qw('SELECT * FROM `#parts` ORDER BY `priority`');
        
        while($res = $SQL->fetch_assoc($sel))
        {
            $cats[$res['id']] = $res;
            $scats[$res['id']] = array();
        }
        
        $sel = $SQL->qw('SELECT * FROM `#subparts` ORDER BY `priority`');
        
        while($res = $SQL->fetch_assoc($sel)) $scats[$res['pid']][$res['id']] = $res;
        ?>
        
        <script src="../js/admin/categories.js"></script>
        
        <a href="#" onclick="add_new_cat(); return false;" class="header">Добавить новую категорию</a>
        
        <hr>
        
        <?
        $tmp = array_keys($cats);
        @$first_key = $tmp[0];
        $last_key  = end($tmp);
        
        foreach($cats as $k=>$v){
        ?>
        
        <span class="actions">
         
         <? if($k!=$first_key) { ?><a href="#" onclick="move_cat(<?echo $k;?>, 'up'); return false;"><b>&uarr;</b></a> <? }else
            { ?> <span><b>&uarr;</b></span> <? } ?>
         
         <? if($k!=$last_key) { ?><a href="#" onclick="move_cat(<?echo $k;?>, 'down'); return false;"><b>&darr;</b></a> <? }else
            { ?> <span><b>&darr;</b></span> <? } ?>
            
         <a href="#" onclick="edit_cat(<?echo $k;?>); return false;" class="edit">ред.</a>
            
         <a href="#" onclick="if(confirm('Вы уверены, что хотите удалить эту категорию? Если в этой категории было много объявлений, это может занять некоторое время.')) { delete_cat(<?echo $k;?>); } return false;" class="del">×</a>
         
         <a href="#" onclick="add_scat(<?echo $k;?>); return false;" class="new_scat">+</a>
        </span>
        
        <? echo '<b>'.$v['name'].'</b> <small>('.$v['description'].')</small>'; ?>
        <br>
        
            <?
            $tmp = array_keys($scats[$k]);
            @$sfirst_key = $tmp[0];
            $slast_key  = end($tmp);
            
            foreach($scats[$k] as $sk=>$sv){
            ?>
            
            <span class="actions" style="margin-right: 40px;">
             
             <? if($sk!=$sfirst_key) { ?> <a href="#" onclick="move_scat(<?echo $sk;?>, 'up'); return false;"><b>&uarr;</b></a> <? }else
                { ?> <span><b>&uarr;</b></span> <? } ?>
                
             <? if($sk!=$slast_key) { ?> <a href="#" onclick="move_scat(<?echo $sk;?>, 'down'); return false;"><b>&darr;</b></a> <? }else
                { ?> <span><b>&darr;</b></span> <? } ?>
            
            <a href="#" onclick="edit_scat(<?echo $sk;?>); return false;" class="edit">ред.</a>
                
             <a href="#" onclick="if(confirm('Вы уверены, что хотите удалить эту подкатегорию? Если там было много объявлений, удаление может занять много времени.')) { delete_scat(<?echo $sk;?>); } return false;" class="del">×</a>
                
            </span>
            
            <? echo ''.$sv['name'].' <small>('.$sv['description'].')</small>'; ?>
            
            <br>
            
            <? } ?>

        <? } ?>
        
        <?
        break;
    case 'ads':
        if(!connected_to_mysql()) break;
        
        if(empty($uri_parts[1]))
        {
            list($parts, $subparts, $rubricator) = rubricator();
            $rubricator = preg_replace('/\\<a href\\="[^\\/]+\\/"[^>]*\\>([^<]*)\\<\\/a>/isU', '\\1', $rubricator);
            $rubricator = str_replace('href="', 'href="ads/', $rubricator);
            echo '<h3>Выберите раздел:</h3>';
            echo $rubricator;
            break;
        }
        
        $cat_engname = $uri_parts[1];
        $scat_engname = $uri_parts[2];
        
        $cat = $SQL->res('SELECT * FROM `#parts` WHERE `engname` = ?', $cat_engname);
        $scat = $SQL->res('SELECT * FROM `#subparts` WHERE `engname` = ? AND `pid` = ?', $scat_engname, $cat['id']);
        
        ?>
        
        <script src="../js/admin/ads.js"></script>
        
        <h3><?echo $cat['name'];?> &raquo; <?echo $scat['name'];?> (<a href="ads/">сменить раздел</a>)</h3><hr>
        
        <?
        
        if(!empty($uri_parts[3])) // редактирование объявления
        {
            $res = $SQL->res('SELECT * FROM `#ads` WHERE `id` = '.intval($uri_parts[3]));
            
            show_ad_edit_form($res, 'apply-edit-ad');
            break;
        }
        
        $PERPAGE = $ADS_PER_PAGE;
        
        extract($SQL->res('SELECT COUNT(*) as `TOTAL_ADS` FROM `#ads` WHERE `scat_id` = ?', $scat['id']));
        
        $PAGES = ceil($TOTAL_ADS / $PERPAGE);
        
        $sel = $SQL->qw('SELECT * FROM `#ads` WHERE `scat_id` = ? ORDER BY `date` DESC LIMIT '.($PAGE_NUM*$PERPAGE-$PERPAGE).','.$PERPAGE, $scat['id']);
        
        
        $pages_html = generate_pages_list("ads/$cat_engname/$scat_engname/", $PAGES, $PAGE_NUM);
        ?>
        
        <div>страницы: <? echo $pages_html; ?></div>
        
        <table style="margin: 10px 0px 10px 0px;">
        
        <?
        while($res = $SQL->fetch_assoc($sel))
        {
            ?>
            
            <tr>
                
                <td style="padding: 10px; border: gray 1px solid;"><input type="checkbox" name="ids[]" value="<? echo $res['id']; ?>" /></td>
                
                <td class="actions" style="padding: 10px; border: gray 1px solid;" nowrap="nowrap">
                
                <a href="<? echo ($edit_lnk="ads/$cat_engname/$scat_engname/{$res['id']}.html"); ?>" class="edit">ред.</a>
                <a href="#" onclick="if(confirm('Действительно удалить объявление?')) delete_ad(<? echo $res['id']; ?>); return false;" class="del">×</a>
                
                </td>
                
                <td style="border: gray 1px solid;"><?=long2ip($res['ip'])?><br><br><img src="../<? if($res['img']) echo $UPL_IMG_ROOT.'/'.$res['img']; else echo 'i/unknown.png'; ?>"></td>
                <td style="border: gray 1px solid;" valign="top"><h3><a href="<? echo $edit_lnk; ?>"><? echo $res['header']; ?> <small>(<? echo human_date($res['date']); ?>)</small></a></h3>
            
                <p><? echo $res['body_short']; ?></p></td>
                
                
            </tr>
            
            <?
        }
        
        ?>
        
        </table>
        
        <div>страницы: <? echo $pages_html; ?></div>
        
        <br><button onclick="if(confirm('Действительно удалить выбранные элементы?')) delete_ads();"><b>удалить выделенные</b></button> &nbsp;&nbsp;&nbsp; <a href="#" onclick="check_all();return false;">Отметить все</a> / <a href="#" onclick="uncheck_all();return false;">Сбросить все</a>
        
        <?
        break;
    case 'apply-edit-ad':
    case 'apply-edit-pending-ad':
        
        $pending = $uri_parts[0] == 'apply-edit-pending-ad' ? '_pending' : '';
        
        $res = $SQL->res('SELECT * FROM `#ads'.$pending.'` WHERE `id` = ?', $_REQUEST['id']);
        
        $p = ROOT_DIR.'/'.$UPL_IMG_ROOT.'/';
        
        foreach($_POST['delete_img'] as $v)
        {
            if(!empty($res[$v]))
            {
                foreach(explode(';', $res[$v]) as $val) unlink($p.trim($val));
                
                $res[$v] = '';
            }
        }
        
        if($_POST['category'] != $res['scat_id'])
        {
            extract($SQL->res('SELECT `pid` `cat_id` FROM `#subparts` WHERE `id` = ?', $_POST['category']));
            
            $res['scat_id'] = $_POST['category'];
            $res['cat_id'] = $cat_id;
        }
        
        $SQL->qw('UPDATE `#ads'.$pending.'` SET
                `fio` = ?,
                `email` = ?,
                `phone` = ?,
                `icq` = ?,
                `url` = ?,
                `city` = ?,
                `scat_id` = ?,
                `cat_id` = ?,
                `header` = ?,
                `body_short` = ?,
                `body` = ?,
                `body_md5` = MD5(`body`),
                `img` = ?,
                `add_img1` = ?,
                `add_img2` = ?,
                `add_img3` = ?
            WHERE `id` = ?',
            
                $_POST['fio'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['icq'],
                $_POST['url'],
                $_POST['city'],
                $res['scat_id'],
                $res['cat_id'],
                $_POST['header'],
                $_POST['body_short'],
                $_POST['body'],
                $res['img'],
                $res['add_img1'],
                $res['add_img2'],
                $res['add_img3'],
            $res['id']);
        
        $r = !empty($_POST['referer']) ? $_POST['referer'] : $CUR_DIR.'admin/';
        
        header('location: '.$r);
        
        break;
    case 'cities':
        if(!connected_to_mysql()) break;
        
        $sel = $SQL->qw('SELECT * FROM `#regions` WHERE 1 ORDER BY `name`');
        
        // поместить несколько областей последними
        
        $regions = $cities = $other = array();
        
        while($res = $SQL->fetch_assoc($sel))
        {
            if(is_not_region($res['name'])) $other  [$res['id']] = $res['name'];
            else                            $regions[$res['id']] = $res['name'];
            
            $cities[$res['id']] = array();
        }
        
        $regions = $regions + $other;
        
        $sel = $SQL->qw('SELECT * FROM `#cities` WHERE 1 ORDER BY `name`');
        
        while($res = $SQL->fetch_assoc($sel)) $cities[$res['region']][$res['id']] = $res['name'];
        
        ?>
        
        <script src="../js/admin/cities.js"></script>
        
        <a href="#" onclick="add_region(); return false;" class="header">Добавить новую область</a><hr>
        
        <?
        foreach($regions as $rid=>$rname)
        {
            ?>
            
            <span class="actions">
                
                <a href="#" onclick="edit_region(<? echo $rid; ?>, <? echo htmlspecialchars(JsHttpRequest::php2js($rname)); ?>); return false;" class="edit">ред.</a>
                
                <a href="#" onclick="if(confirm('Действительно удалить эту область со всеми городами? Удаление может занять некоторое время.')) delete_region(<? echo $rid; ?>); return false;" class="del">×</a>
                
                <a href="#" onclick="add_city(<? echo $rid; ?>); return false;" class="add">+</a>
            
            </span>
            
            <b><? echo $rname; ?></b><br>
            
            <?
            foreach($cities[$rid] as $cid=>$cname)
            {
                ?>
                
                <span class="actions" style="margin-right: 40px;">
                
                    <a href="#" onclick="edit_city(<? echo $cid; ?>, <? echo htmlspecialchars(JsHttpRequest::php2js($cname)); ?>); return false;" class="edit">ред.</a>
                    
                    <a href="#" onclick="if(confirm('Действительно удалить этот город? В нём ведь люди живут... Если в нём жило много людей, то их удаление займёт некоторое время.')) delete_city(<? echo $cid; ?>); return false;" class="del">×</a>
                
                </span>
                
                <? echo $cname; ?><br>
                
                <?
            }
            ?>
            
            <?
        }
        ?>
        
        
        <?
        break;
    case 'pending':
        if(!connected_to_mysql()) break;
        
        list($parts, $subparts,) = rubricator();
        
        $PAGES = ceil( $TOTAL_PENDING / $ADS_PER_PAGE );
        
        $sel = $SQL->qw('SELECT * FROM `#ads_pending` WHERE 1 ORDER BY `date` LIMIT '.($PAGE_NUM*$ADS_PER_PAGE - $ADS_PER_PAGE).','.$ADS_PER_PAGE);
        
        $pages_html = generate_pages_list('pending/', $PAGES, $PAGE_NUM);
        
        ?>
        
        <script src="../js/admin/pending.js"></script>
        
        <h3>Объявления, ждущие проверки</h3>
        
        <hr>
        
        <div>Страницы: <?=$pages_html?></div>
        
        <table style="margin: 15px 0px 15px 0px;">
        
        <?
        $cities = $regions = $results = array();
        
        while($res = $SQL->fetch_assoc($sel))
        {
            $results[] = $res;
            
            $cities[$res['city']] = $res['city'];
            $regions[$res['region']] = $res['region'];
        }
        
        if(sizeof($cities))
        {
            $sel = $SQL->qw('SELECT * FROM `#regions` WHERE `id` IN(?)', $regions);
            while($res = $SQL->fetch_assoc($sel)) $regions[$res['id']] = $res['name'];
            
            $sel = $SQL->qw('SELECT * FROM `#cities` WHERE `id` IN(?)', $cities);
            while($res = $SQL->fetch_assoc($sel)) $cities[$res['id']] = $res['name'];
        }
        
        $p = '../'.$UPL_IMG_ROOT.'/';
        
        foreach($results as $res)
        {
            ?>
            <tr>
                
                <td style="border: gray 1px solid; padding: 10px;">
                    <input type="checkbox" name="ids[]" value="<?=$res['id']?>" style="width: auto;">
                </td>
                
                <td style="border: gray 1px solid; padding: 10px;" class="actions" nowrap="nowrap">
                    <a href="edit-pending-ad/<?=$res['id']?>.html" class="edit">ред.</a>
                    
                    <a href="#" onclick="if(confirm('Действительно удалить объявление?')) delete_pending_ad(<?=$res['id']?>); return false;" class="del">×</a>
                </td>
                
                <td style="border: gray 1px solid; padding: 10px;" valign="top">
                    <b>IP:</b> <?=long2ip($res['ip'])?><br><br>
                    
                    <b><?=$res['fio']?></b>
                    <br><small><?=$cities[$res['city']]?>, <?=$regions[$res['region']]?></small><br><br>
                    
                    <?
                    $r = $GLOBALS['res'];
                    
                    if(!empty($r['phone'])) echo '<b>Телефон:</b> '.$r['phone'].'<br><br>';
                    if(!empty($r['icq'])) echo '<b>ICQ:</b> '.$r['icq'].'<br><br>';
                    if(!empty($r['url'])) echo '<b>Сайт:</b> <a href="'.$r['url'].'">'.$r['url'].'</a><br><br>';
                    ?>
                    
                    <b>E-mail:</b> <a href="mailto:<?=$r['email']?>"><?=$r['email']?></a>
                    
                    <br><br>
                    
                    <?
                    
                    foreach(explode(' ', 'img add_img1 add_img2 add_img3') as $v)
                    {
                        $t = explode(';', $res[$v]);
                        if(!empty($t[0]))
                        {
                            if(!empty($t[1])) echo '<a href="'.$p.$t[1].'" target="_blank">';
                            echo '<img src="'.$p.$t[0].'" />';
                            if(!empty($t[1])) echo '</a>';
                            echo ' ';
                        }
                    }
                    
                    ?>
                    
                </td>
                <td style="border: gray 1px solid; padding: 10px;" valign="top">
                        <h3><?=$res['header']?> <small>(<?=human_date($res['date'])?>)</small></h3>
                    
                        <p><b>Краткий текст:</b> <?=$res['body_short']?>
                        
                        <br><br><hr><br><br>
                        <b>Полный текст:</b> <?=$res['body']?></p>
                    
                    <div style="margin-top: 5px; font-style: italic;"><?=$parts[$res['cat_id']]['name']?> / <?=$subparts[$res['cat_id']][$res['scat_id']]['name']?></div>
                </td>
            
            </tr>
            
            <?
        }
        ?>
        
        </table>
        
        <div>Страницы: <?=$pages_html?></div>
        
        <br><button onclick="confirm_selected();"><b>Одобрить выбранные</b></button>&nbsp;&nbsp;&nbsp;<button onclick="if(confirm('Вы уверены, что хотите отклонить выбранные объявления?')) delete_selected();">Удалить выбранные</button>
        
        <?
        break;
    case 'edit-pending-ad':
        $id = intval($uri_parts[1]);
        
        $res = $SQL->res('SELECT * FROM `#ads_pending` WHERE `id` = ?', $id);
        
        show_ad_edit_form($res, 'apply-edit-pending-ad');
        
        break;
    
    case 'ban':
        
        ?>
        
        <script>
        
        function ban_new_ip()
        {
            var new_ip = prompt('IP-адрес: ', '');
            if(!new_ip) return;
            
            Q('ban-new-ip', { ip: new_ip });
        }
        
        function unban_ip(ip)
        {
            Q('unban-ip', { ip: ip });
        }
        
        </script>
        
        <? if(!is_writable(ROOT_DIR.'/.htaccess')) echo '<span class="warn">Файл .htaccess недоступен на запись! Поставьте для него права на запись, иначе у Вас не получится банить пользователей!</span><br><br>'; ?>
        
        <div class="attention">
            <small>Можно указывать лишь начало IP-адреса -- первые 1, 2 или 3 цифры IP-адреса. Если IP-адрес имеет 3 цифры, то это будет означать, что первые 3 цифры IP-адреса должны совпадать с соответствующими цифрами IP-адреса пользователя.</small>
        </div>
        
        <a href="#" onclick="ban_new_ip();return false;" class="header">Добавить новый IP в базу</a><hr>
        
        <? foreach(get_ip_list() as $v){ ?>
        
            <span class="actions">
                <a href="#" onclick="if(confirm('Точно разбанить этот IP?')) unban_ip('<?=$v?>'); return false;" class="del">×</a>
            </span>
            
            <?=$v?><br>
        
        <? } ?>
        
        <?
        
        break;
    default:
        echo 'Выберите раздел';
        break;
}

$CONTENT = ob_get_clean();


$categories = array(
    'settings' => 'Настройки',
    'categories' => 'Разделы',
    'ads' => 'Объявления',
    'cities' => 'Города',
    'ban' => 'Бан',
);

if($PREMODERATE && $SQL->connected) $categories['pending'] = 'Премодерация'.( $TOTAL_PENDING ? ' (<b>'.$TOTAL_PENDING.'</b>)' : '' );
?>

<html>
<head>
    <title>Доска объявлений &raquo; Панель администратора <? if(@isset($categories[$uri_parts[0]])) echo ' &raquo; '.strip_tags($categories[$uri_parts[0]]); ?></title>
    
    <base href="<? echo get_base_href(); ?>admin/" />
    
    <script src="../js/JsHttpRequest.js"></script>
    <script>
    var BASE_HREF = '<? echo $CUR_DIR; ?>admin/';
    
    // произвести запрос
    // если func не указана, то использовать complete_operation
    
    function Q(act, data, func)
    {
        return JsHttpRequest.query(BASE_HREF+'ajax/'+act+'/', data, func || complete_operation, true);
    }
    
    function complete_operation(res,err)
    {
        if(err)
        {
            alert(err);
            return false;
        }else
        {
            window.location.reload();
            return true;
        }
    }
    </script>
    
    <link type="text/css" href="../i/admin.css" rel="stylesheet" />
    
</head>

<body>
    
    <noscript>
        <style>body{overflow: hidden;}</style>
        <div style="width: 100%; height: 100%; position: absolute; background: white; z-index: 100;"><div style="padding: 10px;">
            
            Даже не думайте, что это будет работать с выключенным JavaScript :).
        
        </div></div>
    </noscript>
    
    <table>
    
    <tr>
        
        <td valign="top" class="navigation" nowrap="nowrap">
            
            <a href="..">&laquo; На главную</a><hr>
            
            <? foreach($categories as $k=>$v) { ?>
            
            <a href="<? echo $k;?>/"><? echo $v; ?></a><br>
            
            <? } ?>
            
        </td>

        <td valign="top" class="cont">
            
            <? echo $CONTENT; ?>
            
        </td>
        
    </tr>
    
    </table>

</body>
</html>

<?

//for($i=0;$i<1000;$i++) echo '<br>';

?>


<?
exit();
?>