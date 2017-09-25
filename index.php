<?
header('Content-type: text/html; charset="UTF-8"');

define('ROOT_DIR', dirname(__FILE__));

define('START_TIME', array_sum(explode(' ', microtime())));

session_name('BBSID');

$CITY = false;

if(!empty($_COOKIE['city'])) $CITY = intval($_COOKIE['city']);

ob_start();
$conf_is_ok = false;
$conf = '?>'.file_get_contents('config.php').'<? $conf_is_ok = true; return true;';

if(!eval($conf) || !$conf_is_ok || !isset($mysql))
{
    ob_end_clean(); // даже если было что-то нечаянно выведено на экран из-за ошибки в config.php, это не попадет на экран пользователя
    die('Извините, конфигурация системы испорчена. Попробуйте зайти позже.');
}
ob_end_clean();

error_reporting(E_ALL);
ini_set('display_errors', 'On');

mb_internal_encoding('UTF-8');

//$_GET['debug'] = true;

require('lib/tpl.php');
require('lib/std.php');
//require('lib/JsHttpRequest.php');

// следующий код вырезает текущую папку из пути $_SERVER['REQUEST_URI']
list($R_URI, $CUR_DIR) = remove_doc_root($_SERVER['REQUEST_URI']);

$uri_parts = array_values(array_filter(explode('/', $R_URI))); // reorder indexes to make it the list again

$IS_IN_ADMIN = (sizeof($uri_parts) > 0 && $uri_parts[0] == 'admin');

if(sizeof($uri_parts) && preg_match('/^page([0-9]+)\.html$/s', $uri_parts[sizeof($uri_parts) - 1], $matches))
{
    $PAGE_NUM = $matches[1];
    
    if(!$IS_IN_ADMIN) $PAGE_NUM = min($MAX_PAGES, $PAGE_NUM);
    
    array_pop($uri_parts); // удаляем последнюю часть из пути, которая говорит о странице -- чтобы не усложнять логику приложения
    
    $R_URI = implode('/', $uri_parts);
}else
{
    $PAGE_NUM = 1;
}

$up_sz = sizeof($uri_parts);

if($up_sz > 0 && $uri_parts[0] == 'captcha-img')
{
    include('lib/kcaptcha/index.php');
    die();
}

ob_start('ob_gzhandler');

require('lib/sql.php');

function postprocessor($str)
{
    global $R_URI, $IS_IN_ADMIN;
    
    $uri = $R_URI;
    
    // если мы находимся в админке, то base href стоит как {BASE_HREF}admin/, поэтому мы можем это учитывать
    if($IS_IN_ADMIN) $uri = substr($uri, strlen('admin/'));
    
    // убираем ссылки «на себя»
    $str = preg_replace('/\<a[^>]+href\="'.preg_quote($uri,'/').'\\/?"[^>]*\>([^\<]*)\<\/a\>/sU', '$1', $str);
    
    $str = str_replace('</body>', '<div align="center" style="margin-top: 10px;"><small>время генерации страницы: '.round( array_sum(explode(' ', microtime())) - START_TIME, 3 ).' сек, MySQL занял '.round($GLOBALS['SQLTIME'],3).' сек, '.declension(intval($GLOBALS['QUERIES']), 'запрос запроса запросов').'</small></div></body>', $str);
    
    return $str;
}

ob_start('postprocessor');

define('M_TPL', 'templates/index.html');

if($up_sz > 0 && $uri_parts[0] == 'add-new')
{
    if(isset($uri_parts[1]))
    {
        // мы стартуем сессию только в паре мест, чтобы не засорять временную папку и не вводить поисковых роботов в заблуждение
        
        list($NAV_TITLE, $CONTENT) = show_add_new_form(intval($uri_parts[1]));
        
    }else
    {
        // выбор раздела
        
        define('CAT_TPL', 'templates/add-new-choose-category.html');
        
        $sel = $SQL->qw('SELECT * FROM `#parts` ORDER BY `priority`');
        while($res = $SQL->fetch_assoc($sel)) $parts[$res['id']] = $res;
        
        $sel = $SQL->qw('SELECT * FROM `#subparts` ORDER BY `priority`');
        while($res = $SQL->fetch_assoc($sel)) $subparts[$res['pid']][$res['id']] = $res;
        
        $PARTS = '';
        
        foreach($parts as $id=>$v)
        {
            $SUBPARTS = '';
            
            foreach($subparts[$id] as $sid=>$sv)
            {
                $SUBPARTS .= $TPL->fastwparse(CAT_TPL, array( 'DESCRIPTION' => $sv['description'], 'NAME' => $sv['name'], 'HREF' => 'add-new/'.$sid.'.html', ), 2, false);
            }
            
            $PARTS .= $TPL->fastwparse(CAT_TPL,
                array(
                      'DESCRIPTION' => $v['description'],
                      'NAME' => $v['name'],
                      'SUBPARTS' => $SUBPARTS,
                      ),
                1, false);
        }
        
        $NAV_TITLE = 'Выберите раздел';
        
        $CONTENT = $TPL->fastwparse(CAT_TPL, array('PARTS' => $PARTS), 0, false);
    }
    
}else if($up_sz > 0 && $uri_parts[0] == 'apply-add-new')
{
    // мы стартуем сессию только в паре мест, чтобы не засорять временную папку и не вводить поисковых роботов в заблуждение
    session_start();
    
    function show_error($text)
    {
        global $uri_parts, $TPL;
        
        unset($_POST['captcha_keystring']);
        
        $_FILES = array();
        
        include_once('lib/FormPersister.php');
        
        ob_start(array('HTML_FormPersister', 'ob_formPersisterHandler'));
        
        list($NAV_TITLE, $CONTENT) = @show_add_new_form(-1, -1, $text); // всё сделает formpersister
        
        $TPL->fastparse(M_TPL,
        array('NAV_TITLE' => '&raquo; '.$NAV_TITLE,
              'CONTENT' => $CONTENT,
              'SCAT_ID' => @$GLOBALS['SCAT_ID'] = intval($_POST['category']),
            )
        );
        
        ob_end_flush();
        
        exit();
    }
    
    // первым делом проверим, не робот ли посылает объявления
    
    if(!isset($_SESSION['captcha_keystring']))
    {
        show_error('Вероятно, Вы слишком долго заполняли текст объявления :(. Попробуйте ещё раз, с новым проверочным кодом.<br><br>P.S. Также, возможно, у Вас отключены cookies, или они не поддерживаются Вашим браузером. Если это так, Вы знаете, что нужно сделать.');
    }
    
    $check_code = $_SESSION['captcha_keystring'];
    unset($_SESSION['captcha_keystring']);
    
    if(!isset($_POST['captcha_keystring']) || $check_code != $_POST['captcha_keystring'])
    {
        show_error('Вы неправильно ввели проверочный код!');
    }
    
    // вроде бы, всё хорошо, это человек или хорошо обученный робот :))
    
    $fio = htmlspecialchars($_POST['fio']) or show_error('Введите ФИО / Имя организации');
    $email = htmlspecialchars($_POST['email']) or show_error('Email не может быть пустым!');
    
    $phone = htmlspecialchars($_POST['phone']);
    $icq = preg_replace('/[^0-9]+/s','',$_POST['icq']);
    
    $url = htmlspecialchars($_POST['url']);
    
    if($url && substr($url,0,strlen('http')) != 'http') $url = 'http://'.$url; // ну мало ли, не знает человек, что сайты надо с http:// или https:// писать :)
    
    if(!EmailIsValid($email)) show_error('Введите правильный e-mail адрес.');
    
    $city = intval($_POST['city']);
    
    extract($SQL->res('SELECT `region` FROM `#cities` WHERE `id`=?', $city))
        or
    show_error('Выбранного города не существует (Если Вы хотите разместить объявление, рекомедуем воспользоваться БРАУЗЕРОМ!).');
    
    $category = intval($_POST['category']);
    
    extract($SQL->res('SELECT `pid` `super_category`, `engname` `category_engname` FROM `#subparts` WHERE `id` = ?', $category))
        or
    show_error('Выбранного раздела не существует.');
    
    $header = htmlspecialchars($_POST['header'])
        or
    show_error('Заголовок объявления не должен быть пустым!');
    
    $body = nl2br(htmlspecialchars($_POST['body']))
        or
    show_error('Текст объявления не может быть пустым!');
    
    $body_short = $_POST['body_short'];
    
    if(mb_strlen($body_short) > 200)
    {
        $body_short = mb_substr($body_short, 0, 200);
    }else if(empty($body_short))
    {
        $body_short = mb_substr($_POST['body'], 0, 200);
    }
    
    $body_short = nl2br(htmlspecialchars($body_short));
    
    //echo '<pre>',!print_r($_FILES),'</pre>';
    
    $image_types = array( 'gif' => IMAGETYPE_GIF, 'png' => IMAGETYPE_PNG, 'jpg' => IMAGETYPE_JPEG );
    
    $main_img = '';
    
    $add_img1 = $add_img2 = $add_img3 = '';
    
    if(!empty($_FILES['main_img']['tmp_name']))
    {
        $main_img = $_FILES['main_img']['tmp_name'];
        
        $it = getimagesize($main_img);
        
        $main_img_type = $it[2];
        
        check_image_type($it, $image_types); // вызовет глобальную ошибку в случае чего
        
        if($it[0] > $MAX_IMG_WIDTH || $it[1] > $MAX_IMG_HEIGHT)
        {
            $main_img_content = get_thumbnail_content($main_img); // вызовет глобальную ошибку в случае чего
            $main_img_type = IMAGETYPE_JPEG;
        }else
        {
            $main_img_content = file_get_contents($main_img);
        }
    }
    
    for($i = 1; $i <= 3; $i++)
    {
        $fname = 'add_img'.$i; // field name
        
        if(!empty($_FILES[$fname]['tmp_name']))
        {
            $$fname = $_FILES[$fname]['tmp_name'];
            
            if(filesize($$fname) > 500*1024) show_error('Превышен максимальный объём дополнительной иллюстрации в 500Кб для файла '.htmlspecialchars($_FILES[$fname]['name']).'. Пожалуйста, выберите другую иллюстрацию или уменьшите ту, что у Вас есть до приемлемого размера самостоятельно.');
            
            $it = getimagesize($$fname);
            ${$fname.'_img_type'} = $it[2];
            
            check_image_type($it, $image_types);
            
            if($it[0] > $MAX_IMG_WIDTH || $it[1] > $MAX_IMG_HEIGHT) ${$fname.'_img_thumbnail'} = get_thumbnail_content($$fname);
        }
    }
    
    $pending = $PREMODERATE ? '_pending' : '';
    
    if(!@$SQL->qw('INSERT INTO `#ads'.$pending.'` SET `header` = ?, `body_short` = ?, `body` = ?, `body_md5` = MD5(`body`), `date` = ?, `scat_id` = ?, `cat_id` = ?, `fio` = ?, `email` = ?, `city` = ?, `region` = ?, `icq` = ?, `phone` = ?, `url` = ?, `ip` = ?', $header, $body_short, $body, time(), $category, $super_category, $fio, $email, $city, $region, $icq, $phone, $url, ip2long($_SERVER['REMOTE_ADDR'])))
    {
        if(!preg_match('/Duplicate/is', $SQL->error()))
        {
            show_error('Извините, произошла внутренняя ошибка сервера, попробуйте повторить запрос через некоторое время. Ваше объявление <i>не размещено</i>.');
        }else
        {
            show_error('Объявление с точно таким же текстом, как Вы ввели, уже существует. Если Вы отправили объявление несколько раз и видите это сообщение, значит Ваше сообщение добавлено успешно (и больше его отправлять не нужно).');
        }
    }
    
    
    $id = $SQL->insert_id();
    
    // добавляем префикс ко всем загружаемым файлам,
    // если стоит премодерация, чтобы избежать
    // конфликтов имен файлов
    
    $fname_prefix = $PREMODERATE ? 'p' : '';
    
    if($main_img)
    {
        $main_img = $fname_prefix.$id.'.'.array_search($main_img_type, $image_types);
        
        $fp = fopen($UPL_IMG_ROOT.'/'.$main_img, 'wb');
        fputs($fp, $main_img_content);
        fclose($fp);
    }
    
    for($i = 1; $i <= 3; $i++)
    {
        $fname = 'add_img'.$i; // field name
        
        if(!$$fname) continue;
        
        $full = $fname_prefix.$id.'.'.$i.'.'.array_search(${$fname.'_img_type'}, $image_types);
        
        if(!move_uploaded_file($from = $$fname, $to = $UPL_IMG_ROOT.'/'.$full)) error_log('Could not move uploaded file from '.$from.' to '.$to.' because '.$php_errormsg);
        
        if(isset(${$fname.'_img_thumbnail'}))
        {
            $th = $fname_prefix.$id.'.'.$i.'.th.jpg';
            
            $$fname = $th.';'.$full;
            
            $fp = fopen($UPL_IMG_ROOT.'/'.$th,'wb');
            fputs($fp, ${$fname.'_img_thumbnail'});
            fclose($fp);
        }else
        {
            $$fname = $full;
        }
    }
    
    if($main_img || $add_img1 || $add_img2 || $add_img3)
    {
        $SQL->qw('UPDATE `#ads'.$pending.'` SET `img` = ?, `add_img1` = ?, `add_img2` = ?, `add_img3` = ? WHERE `id` = ?', $main_img, $add_img1, $add_img2, $add_img3, $id);
    }
    
    //extract($SQL->res('SELECT `engname` `super_category_engname` FROM `#parts` WHERE `id` = ?', $super_category));
    
    //header('location: '.$CUR_DIR.$super_category_engname.'/'.$category_engname.'/'.$id.'.html');
    
    if($PREMODERATE)
    {
        header('location: '.$CUR_DIR.'awaiting-confirmation/');
    }else
    {
        header('location: '.$CUR_DIR);
    }
    
    exit();
}else if($up_sz > 0 && $uri_parts[0] == 'change-city')
{
    define('C_TPL', 'templates/city-select.html');
    
    $regions = $cities = array();
    
    $regions[0] = 'Все области';
    $cities[0][0] = 'Все города';
    
    $sel = $SQL->qw('SELECT * FROM `#regions` ORDER BY `name`');
    
    $other = array();
    
    while($res = $SQL->fetch_assoc($sel))
    {
            if(is_not_region($res['name'])) $other[$res['id']] = $res['name'];
            else                            $regions[$res['id']] = $res['name'];
    }
    
    $regions = $regions + $other;
    
    $sel = $SQL->qw('SELECT * FROM `#cities` ORDER BY `name`');
    
    while($res = $SQL->fetch_assoc($sel)) $cities[$res['region']][$res['id']] = $res['name'];
    
    $REGIONS = '';
    
    foreach($regions as $k=>$v)
    {
            $CITIES = '';
            
            foreach($cities[$k] as $id=>$value)
            {
                $CITIES .= $TPL->fastwparse(C_TPL, array('HREF' => 'apply-change-city/'.$id.'.html', 'NAME' => $value), 2, false);
            }
            
            $REGIONS .= $TPL->fastwparse(C_TPL, array('NAME' => $v, 'CITIES' => $CITIES), 1, false);
    }
    
    $NAV_TITLE = 'Выберите соответствующую страну';
    
    $CONTENT = $TPL->fastwparse(C_TPL, array('REGIONS' => $REGIONS), 0, false);
    
    
}else if($up_sz > 0 && $uri_parts[0] == 'apply-change-city')
{
    setcookie('city', intval($uri_parts[1]), time() + 3*365*3600*24 /* 3 года :)) */, $CUR_DIR);
    
    header('location: '.$CUR_DIR);
    
    exit();
}else if($up_sz > 0 && $uri_parts[0] == 'fill-db')
{
    if($uri_parts[1] != 'GfadjkhfrAFqqr41145511') exit();
    
    while(@ob_end_clean());
    
    header("Content-Encoding: none");
    
    $_GET['debug'] = false;
    
    set_time_limit(0);
    
    $outer = 1000;
    $inner = 30;
    
    $max_date = round(time()/2);
    
    for($i = 0; $i < $outer; $i++)
    {
        $values = array();
        
        for($j = 0; $j < $inner; $j++)
        {
            $body = str_repeat(($i*$inner + $j).' ', 500);
            $body_short = substr($body, 0, 200);
            $body_md5 = md5($body);
            
            $date = mt_rand(0,$max_date);
            
            $cat_id = mt_rand(1,2);
            $scat_id = $cat_id == 1 ? mt_rand(1,2) : mt_rand(4,5);
            
            $city = $i % 2 == 0 ? mt_rand(1, 1245) : 1; // половину городов делаем Адыгейском, половину -- как получится :)
            
            $values[] = "('Тестовый юзверь', 'Тестовый заголовок', '$body', '$body_short', '$body_md5', $date, $cat_id, $scat_id, $city, 1, 'support@microsoft.com', 111111, '+7 (910) 482-..-70')";
        }
        
        $SQL->qw('INSERT INTO `#ads`(`fio`, `header`, `body`, `body_short`, `body_md5`, `date`, `cat_id`, `scat_id`, `city`, `region`,`email`,`icq`,`phone`) VALUES '.implode(',',$values));
        
        echo '.&shy;<wbr>';
        flush();
    }
    
    echo '<h3>'.round( $inner*$outer / (array_sum(explode(' ', microtime())) - START_TIME) ).' ins/sec</h3>';
}else if($up_sz > 0 && $uri_parts[0] == 'admin')
{
    include('lib/admin.php');
}else if($up_sz > 0 && $uri_parts[0] == 'awaiting-confirmation')
{
    $NAV_TITLE = 'Объявление проходит проверку';
    
    $CONTENT = $TPL->fastparse('templates/awaiting-confirmation.html', array(), false);
}

else switch($up_sz)
{
    default:
    case 0:
        
        define('W_TPL', 'templates/index-body.html');
        
        list($parts,$subparts,$rubricator) = rubricator();
        
        $where = ($CITY ? '`city` = '.$CITY : '1');
        
        extract( $SQL->res('SELECT COUNT(*) as `TOTAL_ADS` FROM `#ads` WHERE '.$where) );
        
        $PAGES = max(1, ceil($TOTAL_ADS / $ADS_PER_PAGE_INDEX));
        
        if($PAGES > $MAX_PAGES) $PAGES = $MAX_PAGES; // в общем, тормозит мускул, если листать слишком далеко
        
        // да и какой из индексов использовать, ему тоже приходится подсказывать... :) (впрочем, такое бывает редко)
        
        $sel = $SQL->qw('SELECT * FROM `#ads` USE INDEX(`'.($CITY ? 'city_date' : 'date').'`) WHERE '.$where.' ORDER BY `date` DESC LIMIT '.intval(($PAGE_NUM-1)*$ADS_PER_PAGE_INDEX).','.intval($ADS_PER_PAGE_INDEX));
        
        $ADS = '';
        
        while($res = $SQL->fetch_assoc($sel))
        {
            $cat_engname  = $parts[$res['cat_id']]['engname'];
            $cat_name     = $parts[$res['cat_id']]['name'];
            
            $scat_engname = $subparts[$res['cat_id']][$res['scat_id']]['engname'];
            $scat_name    = $subparts[$res['cat_id']][$res['scat_id']]['name'];
            
            $ADS .= $TPL->fastwparse(W_TPL,
                    array('IMG_SRC' => ($res['img'] ? $UPL_IMG_ROOT.'/'.$res['img'] : 'i/unknown.png'),
                          'HREF' => $cat_engname.'/'.$scat_engname.'/'.$res['id'].'.html',
                          'HEADER' => $res['header'],
                          'BODY_SHORT' => $res['body_short'],
                          'DATE' => human_date($res['date']),
                          
                          'CAT_NAME' => $cat_name,
                          'CAT_HREF' => $cat_engname.'/',
                          'SCAT_NAME' => $scat_name,
                          'SCAT_HREF' => $cat_engname.'/'.$scat_engname.'/',
                          ),
                    1, false);
        }
        
        $CONTENT = $TPL->fastwparse(W_TPL, array(
                'RUBRICATOR' => $rubricator,
                'ADS' => $ADS,
                'PAGES' => generate_pages_list('./', $PAGES, $PAGE_NUM),
                )
            , 0, false);
        
        $NAV_TITLE = 'Главная страница';
        
        break;
    case 1:
    case 2:
        
        $is_subcat = ($up_sz > 1);
        
        extract( $SQL->res('SELECT `id` `cat_id`, `name` `cat_name`, `engname` `cat_engname` FROM `#parts` WHERE `engname` = ?', $uri_parts[0]) )
            or
        app_error('Извините, такой категории не существует.', 404);
           
        if($is_subcat)
            extract( $SQL->res('SELECT `id` `scat_id`, `name` `scat_name`, `engname` `scat_engname` FROM `#subparts` WHERE `pid` = ? AND `engname` = ?', $cat_id, $uri_parts[1]) )
                or
            app_error('Извините, такой подкатегории не существует.', 404);
        
        $NAV_TITLE = $cat_name.($is_subcat ? ' &raquo; '.$scat_name : '');
        
        define('CAT_TPL', 'templates/category.html');
        
        $CONTENT = '';
        
        $CONTENT .= $TPL->fastwparse(CAT_TPL,
            array('CAT_NAME' => $cat_name,
                  'CAT_HREF' => $cat_engname.'/',
                  '/' => $is_subcat ? '/' : '',
                  'SCAT_NAME' => $is_subcat ? $scat_name : '',
                  ),
            0, false);
        
        if(true)//!$is_subcat)
        {
            $sel = $SQL->qw('SELECT * FROM `#subparts` WHERE `pid` = ?', $cat_id);
            
            $PARTS = '';
            
            while($res = $SQL->fetch_assoc($sel))
            {
                $PARTS .= $TPL->fastwparse(CAT_TPL,
                    array('HREF' => $cat_engname.'/'.$res['engname'].'/',
                          'DESCRIPTION' => $res['description'],
                          'NAME' => $res['name'],
                          ),
                    2, false);
            }
            
            $CONTENT .= $TPL->fastwparse(CAT_TPL, array('PARTS' => $PARTS), 1, false);
        }
        
        $where_and = ($CITY ? '`city` = '.$CITY.' AND ' : '');
        
        extract( $SQL->res('SELECT COUNT(*) as `TOTAL_ADS` FROM `#ads` WHERE '.$where_and.' `'.($is_subcat ? 's' : '').'cat_id` = ?', $is_subcat ? $scat_id : $cat_id) );
        
        $PAGES = max(1,ceil($TOTAL_ADS / $ADS_PER_PAGE));
        
        if($PAGES > $MAX_PAGES) $PAGES = $MAX_PAGES; // в общем, тормозит мускул, если листать слишком далеко
        
        $sel = $SQL->qw('SELECT * FROM `#ads`
                        WHERE '.$where_and.' `'.($is_subcat ? 's' : '').'cat_id` = ?
                        ORDER BY `date` DESC
                        LIMIT '.sprintf('%d',($PAGE_NUM-1)*$ADS_PER_PAGE).','.intval($ADS_PER_PAGE),
            $is_subcat ? $scat_id : $cat_id);
        
        $ADS = '';
        
        $results = $scats = array();
        
        while($res = $SQL->fetch_assoc($sel))
        {
            $results[] = $res;
            $scats[$res['scat_id']] = $res['scat_id'];
            
        }
        
        if(!$is_subcat)
        {
            if(sizeof($scats))
            {
                $sel = $SQL->qw('SELECT `id`,`engname`,`name` FROM `#subparts` WHERE `id` IN (?)', $scats);
                while($res = $SQL->fetch_assoc($sel)) $scats[$res['id']] = $res;
            }
        }else
        {
            $scats[$scat_id] = array('engname' => $scat_engname, 'name' => $scat_name);
        }
        
        foreach($results as $res)
        {
            $ADS .= $TPL->fastwparse(CAT_TPL,
                    array('IMG_SRC' => ($res['img'] ? $UPL_IMG_ROOT.'/'.$res['img'] : 'i/unknown.png'),
                          'HREF' => $cat_engname.'/'.$scats[$res['scat_id']]['engname'].'/'.$res['id'].'.html',
                          'HEADER' => $res['header'],
                          'BODY_SHORT' => $res['body_short'],
                          'DATE' => human_date($res['date']),
                          
                          'CAT_NAME' => $cat_name,
                          'CAT_HREF' => $cat_engname.'/',
                          'SCAT_NAME' => $scats[$res['scat_id']]['name'],
                          'SCAT_HREF' => $cat_engname.'/'.$scats[$res['scat_id']]['engname'].'/',
                          ),
                    4, false);
        }
        
        list($parts,$subparts,$rubricator) = rubricator();
        
        $CONTENT .= $TPL->fastwparse(CAT_TPL, array(
                'ADS' => $ADS,
                'PAGES' => generate_pages_list($cat_engname.($is_subcat ? '/'.$scat_engname : '').'/', $PAGES, $PAGE_NUM),
                'RUBRICATOR' => $rubricator,
            ), 3, false);
        
        break;
    case 3:
        
        $id = intval($uri_parts[2]);
        
        extract( $SQL->res('SELECT `id` `cat_id`, `name` `cat_name`, `engname` `cat_engname` FROM `#parts` WHERE `engname` = ?', $uri_parts[0]) )
            or
        app_error('Извините, такой категории не существует', 404);
        
        
        extract( $SQL->res('SELECT `id` `scat_id`, `name` `scat_name`, `engname` `scat_engname` FROM `#subparts` WHERE `pid` = ? AND `engname` = ?', $cat_id, $uri_parts[1]) )
            or
        app_error('Извините, такой подкатегории не существует', 404);
        
        $res = $SQL->res('SELECT * FROM `#ads` WHERE `id` = ?', $id);
        
        if(!$res) app_error('Извините, объявления с таким номером не существует', 404);
        
        extract( $SQL->res('SELECT `name` `city_name` FROM `#cities` WHERE `id` = ?', $res['city']) );
        extract( $SQL->res('SELECT `name` `region_name` FROM `#regions` WHERE `id` = ?', $res['region']) );
        
        define('AD_TPL', 'templates/ad.html');
        
        $NAV_TITLE = $cat_name.' &raquo; '.$scat_name.' &raquo; '.$res['header'];
        
        list($parts,$subparts,$rubricator) = rubricator();
        
        $where_and = ($CITY ? '`city` = '.$CITY.' AND ' : '');
        
        // PREV_AD / NEXT_AD
        
        $res_next = $SQL->res('SELECT * FROM `#ads` WHERE '.$where_and.'`scat_id` = ? AND `date` < ? ORDER BY `date` DESC LIMIT 1', $scat_id, $res['date']);
        $res_prev = $SQL->res('SELECT * FROM `#ads` WHERE '.$where_and.'`scat_id` = ? AND `date` > ? ORDER BY `date` ASC  LIMIT 1', $scat_id, $res['date']);
        
        $PREV_AD = $NEXT_AD = '';
        
        foreach(array('res_prev' => 'PREV_AD', 'res_next' => 'NEXT_AD') as $k=>$v)
        {
            if(!$$k) continue;
            
            $r = $$k;
            
            $src = ($r['img'] ? $UPL_IMG_ROOT.'/'.$r['img'] : 'i/unknown.png');
            
            $imgsz = getimagesize(ROOT_DIR.'/'.$src);
            
            $img_width = round($imgsz[0]/2);
            $img_height = round($imgsz[1]/2);
            
            $$v = $TPL->fastwparse(AD_TPL,
                    array('IMG_SRC' => $src,
                          'IMG_WIDTH' => $img_width,
                          'IMG_HEIGHT' => $img_height,
                          'HREF' => $cat_engname.'/'.$scat_engname.'/'.$r['id'].'.html',
                          'HEADER' => $r['header'],
                          'BODY_SHORT' => $r['body_short'],
                          'DATE' => human_date($r['date']),
                          
                          'CAT_NAME' => $cat_name,
                          'CAT_HREF' => $cat_engname.'/',
                          'SCAT_NAME' => $subparts[$r['cat_id']][$r['scat_id']]['name'],
                          'SCAT_HREF' => $cat_engname.'/'.$scat_engname.'/',
                          ),
                    4, false);
        }
        
        // / PREV_AD / NEXT_AD
        
        // RUBRICATOR
        $sel = $SQL->qw('SELECT * FROM `#subparts` WHERE `pid` = ?', $cat_id);
        
        $PARTS = '';
        
        while($r = $SQL->fetch_assoc($sel))
        {
            $PARTS .= $TPL->fastwparse(AD_TPL,
                array('HREF' => $cat_engname.'/'.$r['engname'].'/',
                      'DESCRIPTION' => $r['description'],
                      'NAME' => $r['name'],
                      ),
                2, false);
        }
        
        $RUBRICATOR = $TPL->fastwparse(AD_TPL, array('PARTS' => $PARTS), 1, false);
        // / RUBRICATOR
        
        // ADS
        
        $excl_ids = array($id);
        
        if($res_prev) $excl_ids[] = $res_prev['id'];
        if($res_next) $excl_ids[] = $res_next['id'];
        
        $sel = $SQL->qw('SELECT * FROM `#ads`
                        WHERE '.$where_and.'`scat_id` = ? AND `id` NOT IN(?)
                        ORDER BY `date` DESC
                        LIMIT '.intval($ADS_PER_PAGE_AD_VIEW),
            $scat_id, $excl_ids);
        
        $ADS = '';
        
        $results = $scats = array();
        
        while($r = $SQL->fetch_assoc($sel)) $results[$r['id']] = $r;
        
        /*
        // получить объявления, старше указанного
        
        $sel = $SQL->qw('SELECT * FROM `#ads`
                        WHERE '.$where_and.'`scat_id` = ? AND `date` < ? AND `id` NOT IN(?)
                        ORDER BY `date` DESC
                        LIMIT '.intval($ADS_PER_PAGE_AD_VIEW),
            $scat_id, $res['date'], array_merge(array(-1), array_keys($results)));
        
        
        while($r = $SQL->fetch_assoc($sel)) $results[$r['id']] = $r;
   */
        
        $scats[$scat_id] = array('engname' => $scat_engname, 'name' => $scat_name);
        
        foreach($results as $r)
        {
            $ADS .= $TPL->fastwparse(AD_TPL,
                    array('IMG_SRC' => ($r['img'] ? $UPL_IMG_ROOT.'/'.$r['img'] : 'i/unknown.png'),
                          'HREF' => $cat_engname.'/'.$scat_engname.'/'.$r['id'].'.html',
                          'HEADER' => $r['header'],
                          'BODY_SHORT' => $r['body_short'],
                          'DATE' => human_date($r['date']),
                          
                          'CAT_NAME' => $cat_name,
                          'CAT_HREF' => $cat_engname.'/',
                          'SCAT_NAME' => $scats[$r['scat_id']]['name'],
                          'SCAT_HREF' => $cat_engname.'/'.$scat_engname.'/',
                          ),
                    3, false);
        }
        
        // /ADS
        
        
        
        $CONTENT = $TPL->fastwparse(AD_TPL, array(
                'IMG_SRC' => ($res['img'] ? $UPL_IMG_ROOT.'/'.$res['img'] : 'i/unknown.png'),
                'HREF' => $cat_engname.'/'.$scat_engname.'/'.$res['id'].'.html',
                'HEADER' => $res['header'],
                'BODY' => $res['body'],
                'DATE' => human_date($res['date']),
                
                'CAT_NAME' => $cat_name,
                'CAT_HREF' => $cat_engname.'/',
                'SCAT_NAME' => $scat_name,
                'SCAT_HREF' => $cat_engname.'/'.$scat_engname.'/',
                
                'CITY' => $city_name,
                'REGION' => $region_name,
                'FIO' => $res['fio'],
                
                'CONTACT_HREF' => 'mailto:'.$res['email'],
                
                'URL' => $res['url'],
                'PHONE' => $res['phone'],
                'ICQ' => $res['icq'],
                'E-MAIL' => $res['email'],
                
                'RUBRICATOR_YN' => $RUBRICATOR,
                
                'RUBRICATOR' => $rubricator,
                
                'ADS' => $ADS,
                
                'PREV_AD' => $PREV_AD,
                'NEXT_AD' => $NEXT_AD,
                ),
            0, false);
        
        break;
}

$TPL->fastparse(M_TPL,
    array('NAV_TITLE' => '&raquo; '.$NAV_TITLE,
          'CONTENT' => $CONTENT,
          'SCAT_ID' => $SCAT_ID = (isset($scats) ? array_get_first_key($scats).'.html' : (isset($scat_id) ? $scat_id.'.html' : '')),
        )
    );

//echo ' <!-- '.print_r($GLOBALS['SQLSTATS'], true).' -->';

?>