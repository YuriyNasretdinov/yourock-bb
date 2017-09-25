<?php

// Version 1.02.
// Заменяет ссылки на их HTML-эквиваленты ("подчеркивает ссылки").
// Работает с УЖЕ ПРОКВОЧЕННЫМ HTML-кодом!
// функция взята с http://forum.dklab.ru/
function hrefActivate($text) {
	$text = preg_replace_callback(
		'{
			(?:
				# ВНИМАНИЕ: \w+ вместо (?:http|ftp) СИЛЬНО ТОРОМОЗИТ!!!
				((?:http|ftp)://)   # протокол с двумя слэшами
				| (?<!.)www\.       # или просто начинается на www
			)
			(?> [a-z0-9_-]+ (?>\.[a-z0-9_-]+)* )   # имя хоста
			(?: : \d+)?                            # порт
			(?:                                    # URI (но БЕЗ кавычек)
					&amp;                       # амперсанд
				| & (?![a-z]+;) (?!\#\d+;)    # если попался незаквоченный URI
				| [^[\]&\s\x00»«"<>]          # все остальное
			)*
			(?:                                    # последний символ должен быть...
					(?<! [[:punct:]] )          # НЕ пунктуацией
				| (?<= &amp; | [-/&+*]     )  # но допустимо окончание на -/&+*
			)
			(?= [^<>]* (?! </a) (?: < | $))        # НЕ внутри тэга
		}six',
		"hrefCallback",
		$text
	);
	return $text;
}   

// Функция обратного вызова для preg_replace_callback().
function hrefCallback($p) {
	$name = $p[0];
	$href = !empty($p[1])? $name : "http://$name";
	$realUri = null;
	if (function_exists($getter='getPageTitleByUri')) $name = $getter($href, $realUri);
	$href = str_replace('"', '&amp;', $href);
	if ($name === null) $name = $href;
	$html = 
		"<a href=\"$href\" " . 
		($realUri && $realUri != $href? "name=\"$realUri\" " : "") . 
		"target=\"_blank\">$name</a>";
	return $html;
}

/**
 * Shows size of $f file or folder like "1023.53 Kb". If $format=false, it returns just filesize in bytes, and fills $GLOBALS['show_size_files'] and $GLOBALS['show_size_dirs'] with values. If $size is not false, the function will just format $size value, but not count the size of directory again.
 *
 * @param string $f
 * @param bool $format
 * @return string/int
 */
function show_size($f,$format=true,$size=false,$skip_links=false)
{
	if($format || $size!==false)
	{
		if($size===false) $size=show_size($f,false,false,$skip_links);
		if(!empty($GLOBALS['TIMED_OUT'])) $p = '&gt;';
		else $p = '';
		if($size<=1024) return $p.$size.'&nbsp;байт';
		else if($size<=1048576) return $p.round($size/1024,2).'&nbsp;Кб';
		else if($size<=1073741824) return $p.round($size/1048576,2).'&nbsp;Мб';
		else if($size<=1099511627776) return $p.round($size/1073741824,2).'&nbsp;Гб';
		else if($size<=1125899906842624) return $p.round($size/1099511627776,2).'&nbsp;Тб';
		else return $p.round($size/1125899906842624,2).'&nbsp;Пб';
	}else
	{
		if($skip_links && is_link($f)) return 0;
		
		if(is_file($f))
		{
			$GLOBALS['show_size_files'] = 1;
			$GLOBALS['show_size_dirs'] = 0;
			
			return filesize($f);
		}else if(!is_dir($f))
		{
			return 0;
		}
		$size=0;

		$dh=opendir($f);
		$fs=$ds=0;
		
		while(($file=readdir($dh))!==false)
		{
			if($file=='.' || $file=='..') continue;
			// delete the next lines if you don't want any limits
			if($skip_links && is_link($f.'/'.$file)) continue;
			
			if(is_file($f.'/'.$file))
			{
				$size+=filesize($f.'/'.$file);
				$fs++;
			}else
			{
				$size+=show_size($f.'/'.$file,false,false,$skip_links);
				$ds+=$GLOBALS['show_size_dirs'];
				$fs+=$GLOBALS['show_size_files'];
			}
		}
		closedir($dh);
		
		$GLOBALS['show_size_files'] = $fs;
		$GLOBALS['show_size_dirs'] = 1+$ds;
		return $size+filesize($f); // +filesize($f) for *nix directories
	}
}

/**
 *
 * Functions, which have "mb_" in the beginning are fixed versions of standard PHP functions, that do not work correctly
 * with UTF-8 strings for some reason
 *
*/

if(!function_exists('mb_basename'))
{
	function mb_basename($path, $suffix = null)
	{
		/*$file = mb_substr($path, mb_strrpos($path, '/')+1);*/

		$file = end(explode('/', $path));
		
		if($suffix && mb_substr($file, -($l = mb_strlen($suffix))) == $suffix)
		{
			$file = mb_substr($file, 0, $l);
		}
		
		return $file;
	}
	
}

/*
 
 End of fixed functions
 
*/


function array_display($array)
{
	echo "<table border=1 cellpadding=2 cellspacing=2><tr><td colspan=2 style='text-align:center;'><b>array</b></td></tr>";
	
	foreach($array as $key=>$value)
	{
		if(!is_array($value))
		{
			echo "<tr><td width=100><i>".$key."</i></td><td>".$value."</td></tr>";
		}else
		{
			echo "<tr><td width=100><i><b style='color:red;'>".$key."</b></i></td><td>";
			array_display($value);
			echo "</td></tr>";
		}
	}

	echo "</table>";
}

function timestamp()
{
	return date('d.m.Y H-i-s');
}

// Функция предназначена для вывода численных результатов с учетом
// склонения слов, например: "1 ответ", "2 ответа", "13 ответов" и т.д.
//
// $digit — целое число
// можно вместе с форматированием, например "<b>6</b>"
//
// $expr — массив, например: array("ответ", "ответа", "ответов").
// можно указывать только первые 2 элемента, например для склонения английских слов
// (в таком случае первый элемент - единственное число, второй - множественное)
//
// $expr может быть задан также в виде строки: "ответ ответа ответов", причем слова разделены
// символом "пробел"
//
// $onlyword - если true, то выводит только слово, без числа;
// необязательный параметр

function declension($digit,$expr,$onlyword=false)
{
		if(!is_array($expr)) $expr = array_filter(explode(' ', $expr));
        if(empty($expr[2])) $expr[2]=$expr[1];
        $i=preg_replace('/[^0-9]+/s','',$digit)%100; //intval не всегда корректно работает
        if($onlyword) $digit='';
        if($i>=5 && $i<=20) $res=$digit.' '.$expr[2];
        else
        {
                $i%=10;
                if($i==1) $res=$digit.' '.$expr[0];
                elseif($i>=2 && $i<=4) $res=$digit.' '.$expr[1];
                else $res=$digit.' '.$expr[2];
        }
        return trim($res);
}

function declensor($word, $enc = 'UTF-8')
{
    $ret = array_fill(0,7,$word);
    
    $cont = file_get_contents('http://export.yandex.ru/inflect.xml?name='.rawurlencode($word));
    if($cont)
    {
        $cont = explode('<inflection case="', $cont);
        array_shift($cont);
        
        foreach($cont as $v)
        {
            list($num, $name) = explode('">', $v);
            list($name,) = explode('</',$name);
            $ret[$num] = iconv('windows-1251', $enc, $name);
        }
    }
    
    return $ret;
}

function get_declension($word, $enc = 'UTF-8', $num)
{
	$res = declensor($word, $enc);
	return $res[$num];
}

function human_date($time)
{
	static $mo = false, $rmo = false, $rms = false, $wkd = false;
	
	if(!$mo)
	{
		$mo  = explode(' ', 'Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec');
		$rmo = explode(' ', 'Января Февраля Марта Апреля Мая Июня Июля Августа Сентября Октября Ноября Декабря');
		$rms = explode(' ', 'Янв Фев Мар Апр Май Июн Июл Авг Сен Окт Ноя Дек');
		$wkd = explode(' ', 'Воскресенье Понедельник Вторник Среда Четверг Пятница Суббота');
	}
	
	$dmy = date('d.m.Y', $time);
	
	$dmyw = date('d.m.Y.w', $time);
	list($d,$m,$y,$w) = explode('.',$dmyw);
	$w = $wkd[$w];
	
	if($dmy == date('d.m.Y')) return 'Сегодня в '.date('H:i', $time);
	if($dmy == date('d.m.Y', time() - 24*3600)) return 'Вчера, '.$w.' в '.date('H:i', $time);
	if($dmy == date('d.m.Y', time() - 2*24*3600)) return 'Позавчера, '.$w.' '.$d.' числа в '.date('H:i', $time);
	
	if($y == date('Y')) return str_replace($mo, $rmo, date('j M', $time)).', '.$w;
	
	return str_replace($mo, $rmo, date('j M y', $time));
}

/**
 * @desc This function sends to the browser thumbnail of image $fullpath, and, if you want, you can point $width and $height for this thumbnail. default is 80x60. If $resample is set to TRUE, the quality resize algorithm will be used.
 *
 * @author Nasretdinov Yuriy <n a s r e t d i n o v [all-known-symbol] g m a i l . c o m>
 * @version 0.9.2
 * @param string $fullpath
 * @param int $width
 * @param int $height
 * @param bool $resample
 * @return bool
 */
function send_thumbnail($fullpath,$width=80,$height=60,$resample=-1)
{       
        @$w = getimagesize($fullpath);
        if (!$w) return d_error('Could not get image size');
        if ( (@$lim = return_bytes(ini_get('memory_limit'))) > 0)
        {
                $sz = ( $w[0]*$w[1] + $width*$height ) * 5 + (function_exists('memory_get_usage') ? memory_get_usage() : 0); // approximate size of image in memory (yes, 5 bytes per pixel!!)
                if ($sz >= $lim) return d_error('Memory limit exceeded');
        }
       
        if ($w[0] <= $width && $w[1] <= $height)
        {
                header('Content-type: '.$w['mime']);
                readfile($fullpath);
               
                return true;
        }
       
        if (!function_exists('imagecreate') || !function_exists('imagecopyresized')) return d_error('GD not found: either imagecreate() or imagecopyresized() do not exist.');

        $types = array('','gif','jpeg','png');
        @$ext = $types[$w[2]];
       
        if (function_exists($func = 'imagecreatefrom'.$ext)) $src = $func($fullpath);
        else return d_error('Unsupported image type (the function called is '.$func.'). Maybe, invalid extension?');
       
        header('Content-type: image/jpeg');
       
        //proportions
        $new_width = round(($height/$w[1])*$w[0]);
        $new_height = round(($width/$w[0])*$w[1]);
        if ($new_width>$width) $new_width = $width;
        if ($new_height>$height) $new_height = $height;
       
        if (!function_exists($cfunc = 'imagecreatetruecolor')) $cfunc='imagecreate';
        $thumb = $cfunc($new_width,$new_height);
       
        $func = (($resample===-1 && defined('USE_RESAMPLE') && USE_RESAMPLE) || $resample===true) && function_exists('imagecopyresampled') ? 'imagecopyresampled' : 'imagecopyresized';
       
        // optimisations for big images
        // (idea taken from http://xpoint.ru/know-how/PHP/TeoreticheskieStat'i/QuickThumbs?comments)
        $c = 2;
        if ($func != 'imagecopyresized' && ($w[0] > $c*$new_width || $w[1] > $c*$new_height))
        {
                /* TODO: add check for memory available for doing this operation */
                $thumb_c = $cfunc($c*$new_width,$c*$new_height);
                imagecopyresized($thumb_c,$src,0,0,0,0,$c*$new_width,$c*$new_height,$w[0],$w[1]);
                imagedestroy($src);
                $src = $thumb_c;
                list($w[0],$w[1]) = array($c*$new_width,$c*$new_height);
        }
       
        $func($thumb,$src,0,0,0,0,$new_width,$new_height,$w[0],$w[1]);

        imagedestroy($src);
        imagejpeg($thumb);
        imagedestroy($thumb);
       
        return true;
}
/**
 * The function from PHP manual that returns size in bytes of PHP.INI sizes: e.g. 4K , 5M , 10G
 *
 * @param string $val
 * @return int
 */
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val{strlen($val)-1});
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}

// the function to log errors.
//
// If $error_text is set to 'all', the function returns an array of all error messages.
// If $error_text is not set, the function returns the last error message.
// Else it logs the error message and returns false.
//
// example of usage:
//
// if(false) return d_error('You have set "false" in "if" condition');

function d_error($error_text = 0)
{
        static $errors = array();
       
        if($error_text === 'all') return $errors;
        if($error_text === 0) return (sizeof($errors)>0 ? $errors[sizeof($errors)-1] : '');
        $errors[]=$error_text;
        return false;
}

// функция специально для доски объявлений

function get_thumbnail_content($main_img)
{
	global $MAX_IMG_HEIGHT, $MAX_IMG_WIDTH;
	
	ob_start();
	
	if(!send_thumbnail($main_img, $MAX_IMG_WIDTH, $MAX_IMG_HEIGHT))
	{
	    if(d_error() == 'Memory limit exceeded')
	    {
		show_error('Извините, изображение такого размера ('.$it[0].'x'.$it[1].') не могут быть уменьшено (слишком большое). Попробуйте закачать изображение размерами не более 1000x1000 пикселей.');
	    }else
	    {
		show_error('Произошла неизвестная ошибка при попытке уменьшить фотографию. Попробуйте уменьшить фотографию самостоятельно, чтобы она вмещалась в рамки '.$MAX_IMG_WIDTH.'x'.$MAX_IMG_HEIGHT);
	    }
	}
	
	header('Content-type: text/html; charset="UTF-8"');
	
	return ob_get_clean();
}

function check_image_type($it /* getimagesize($img) */, $image_types)
{	
	if(!in_array($it[2], $image_types)) show_error('Тип загруженного Вами изображения '.(isset($it['mime']) ? '('.$it['mime'].')' : '').' не поддерживается (поддерживается только '.implode(', ', array_map('strtoupper', array_keys($image_types))).')');
	
	return true;
}

// Ну да, прямо в код зашиты имена :). Ну и что! Не надо их менять, вот и всё.

function is_not_region($name)
{
	return ($name == 'Другие страны' || $name == 'Другое');
}

function cities_select($city = false)
{
	global $SQL;
	
	$regions = $cities = array();
	
	$sel = $SQL->qw('SELECT * FROM `#regions` ORDER BY `name`');
	
	$other = array();
	
	// place several entries to the end
	
	while($res = $SQL->fetch_assoc($sel))
	{
		if(is_not_region($res['name'])) $other[$res['id']] = $res['name'];
		else                            $regions[$res['id']] = $res['name'];
	}
	
	$regions = $regions + $other;
	
	$sel = $SQL->qw('SELECT * FROM `#cities` ORDER BY `name`');
	
	while($res = $SQL->fetch_assoc($sel)) $cities[$res['region']][$res['id']] = $res['name'];
	
	$html = '<select name="city">';
	
	foreach($regions as $k=>$v)
	{
		$html .= '<optgroup label="'.$v.'">';
		
		foreach($cities[$k] as $id=>$value)
		{
			$html .= '<option value="'.$id.'"'.(
				
				(!$GLOBALS['CITY'] && $city && $id == $city) ||
				(!$GLOBALS['CITY'] && !$city && $value == 'Москва') ||
				($GLOBALS['CITY'] && $id == $GLOBALS['CITY']) ? ' selected="selected"' : ''
			
			).'>'.$value.'</option>';
		}
		
		$html .= '</optgroup>';
	}
	
	$html .= '</select>';
	
	return $html;
}

function category_select($selected_cat = -1)
{
	global $SQL;
	
	$cats = $scats = array();
	
	$sel = $SQL->qw('SELECT * FROM `#parts` ORDER BY `priority`');
	
	while($res = $SQL->fetch_assoc($sel))
	{
		$cats[$res['id']] = $res['name'];
		$scats[$res['id']] = array();
	}
	
	$sel = $SQL->qw('SELECT * FROM `#subparts` ORDER BY `priority`');
	
	while($res = $SQL->fetch_assoc($sel)) $scats[$res['pid']][$res['id']] = $res['name'];
	
	$html = '<select name="category">';
	
	foreach($cats as $k=>$v)
	{
		$html .= '<optgroup label="'.$v.'">';
		
		foreach($scats[$k] as $id=>$value)
		{
			$html .= '<option value="'.$id.'"'.($id == $selected_cat ? ' selected="selected"' : '').'>'.$value.'</option>';
		}
		
		$html .= '</optgroup>';
	}
	
	$html .= '</select>';
	
	return $html;
}

function rubricator()
{
	global $SQL, $TPL;
	
	$W_TPL = ROOT_DIR.'/templates/rubricator.html';
	
	$parts = $subparts = array();
	
	$sel = $SQL->qw('SELECT * FROM `#parts` ORDER BY `priority`');
	
	while($res = $SQL->fetch_assoc($sel))
	{
		$parts[$res['id']] = $res;
		$subparts[$res['id']] = array();
	}
	
	$sel = $SQL->qw('SELECT * FROM `#subparts` ORDER BY `priority`');
	
	while($res = $SQL->fetch_assoc($sel)) $subparts[$res['pid']][$res['id']] = $res;
	
	$content = '';
	
	foreach($parts as $id=>$v)
	{
	    $scontent = '';
	    
	    foreach($subparts[$id] as $sid=>$sv)
	    {
		$scontent .= $TPL->fastwparse($W_TPL,
		    array('HREF' => $v['engname'].'/'.$sv['engname'].'/',
			  'DESCRIPTION' => $sv['description'],
			  'NAME' => $sv['name'],
			  ),
		    2, false);
	    }
	    
	    $content .= $TPL->fastwparse($W_TPL,
		array('HREF' => $v['engname'].'/',
		      'DESCRIPTION' => $v['description'],
		      'NAME' => $v['name'],
		      'SUBPARTS' => $scontent,
		      ),
		1, false);
	}
	
	return array($parts, $subparts, $TPL->fastwparse($W_TPL, array('PARTS' => $content), 0, false));
}

function show_add_new_form($subpart, $city = false, $ERRORS = '')
{
	global $SQL, $TPL;
	
	@session_start();
	
	@extract( $SQL->res('SELECT `pid`, `name` `scat_name`, `engname` `scat_engname` FROM `#subparts` WHERE `id` = ?', ($subpart)) );
	@extract( $SQL->res('SELECT `name` `cat_name`, `engname` `cat_engname` FROM `#parts` WHERE `id` = ?', $pid) );
	
	if(!isset($cat_name))
	{
	    $cat_name = $cat_engname = $scat_name = $scat_engname = '';
	}
	
	$NAV_TITLE = 'Разместить новое объявление'.(isset($scat_name) ? ' &raquo; '.$cat_name.' &raquo; '.$scat_name : '');
	
	$GLOBALS['ERRORS'] = $ERRORS;
	
	$CONTENT = $TPL->fastparse('templates/add-new.html', array(
	    
	    'CITIES_SELECT' => cities_select($city),
	    'CATEGORY_SELECT' => category_select(($subpart)),
	    
	    'CAT_NAME' => $cat_name,
	    'SCAT_NAME' => $scat_name,
	    
	    'CAT_HREF' => $cat_engname.'/',
	    'SCAT_HREF' => $cat_engname.'/'.$scat_engname.'/',
	    
	    'ERRORS' => $ERRORS,
	    
	), false);
	
	return array($NAV_TITLE, $CONTENT);
}

function app_error($message, $code = 200)
{
	static $descr = array( 200 => 'OK', 404 => 'Not Found', 403 => 'Forbidden', 500 => 'Internal Server Error');
	
	if($code != 200)
	{
		header('HTTP/1.1 '.$code.' '.$descr[$code]);
	}
	
	global $TPL;
	
	//while(@ob_end_clean());
	
	$message = $TPL->fastparse('templates/index.html', array(
		'NAV_TITLE' => 'Ошибка :(',
		'CONTENT' => '<h3 class="error_header">Ошибочка вышла!</h3><div class="error_message">'.$message.'</div>',
		'SCAT_ID' => '',
		
	), false);
	
	die($message);
}

function _generate_pages_curr(&$result, $i)
{
	$result.='<span class="page_selected">'.$i.'</span> ';
}

function _generate_pages_lnk(&$result, $base_href, $i, $text = false, $add_attr = '')
{
	$result.='<a href="'.$base_href.'page'.$i.'.html" class="page_select" '.$add_attr.'>'.($text ? $text : $i).'</a> ';
}

function generate_pages_list($base_href, $pages, $page, $add_attr = '')
{
	if($base_href[strlen($base_href)-1] != '/') $base_href .= '/';
	
	$result = '';
	
	if($pages == 0)
	{
		_generate_pages_curr($result,$i = 1);
	}else if($pages < 9)
	{
		for($l = 1; $l <= $pages; $l++)
		{
			if($l == $page) _generate_pages_curr($result,$l);
			else            _generate_pages_lnk($result,$base_href,$l,false,$add_attr);
		}
	}else
	{
		if($page < 4)
		{
			for($l = 1; $l <= $page + 2; $l++)
			{
				if($l == $page) _generate_pages_curr($result,$l,$add_attr);
				else            _generate_pages_lnk($result,$base_href,$l,false,$add_attr);
			}
			
			_generate_pages_lnk($result,$base_href,$i = $page + 4, '...',false,$add_attr);
			
			_generate_pages_lnk($result,$base_href,$i = $pages-2,false,$add_attr);
			_generate_pages_lnk($result,$base_href,$i = $pages-1,false,$add_attr);
			_generate_pages_lnk($result,$base_href,$i = $pages,false,$add_attr);
		}else if( ($pages - $page) < 4)
		{
			_generate_pages_lnk($result,$base_href,$i = 1,false,$add_attr);
			_generate_pages_lnk($result,$base_href,$i = 2,false,$add_attr);
			_generate_pages_lnk($result,$base_href,$i = 3,false,$add_attr);
			_generate_pages_lnk($result,$base_href,$i = $page - 4, '...',$add_attr);
			
			for($l = $page - 2; $l <= $pages; $l++)
			{
				if($l == $page) _generate_pages_curr($result,$l);
				else            _generate_pages_lnk($result,$base_href,$l,false,$add_attr);
			}
		}else
		{
			_generate_pages_lnk($result,$base_href,$i = 1,false,$add_attr);
			_generate_pages_lnk($result,$base_href,$page - 4, '...',$add_attr);
			
			for($l = $page - 2; $l <= $page + 2; $l++)
			{
				if($l == $page) _generate_pages_curr($result,$l,$add_attr);
				else            _generate_pages_lnk($result,$base_href,$l,false,$add_attr);
			}
			
			_generate_pages_lnk($result,$base_href,$i = $page + 4, '...',$add_attr);
			_generate_pages_lnk($result,$base_href,$i = $pages, false, $add_attr);
		}
	}
	
	return $result;
}

function remove_doc_root($uri)
{
	$result = substr( $uri, $cur_dir_length = strlen( str_replace( realpath($_SERVER['DOCUMENT_ROOT']), '',realpath(ROOT_DIR)) ) + 1 );
	$cur_dir = substr( $uri, 0, $cur_dir_length );
	
	return array($result, $cur_dir);
}

function get_base_href()
{
	return 'http://'.$_SERVER['HTTP_HOST'].str_replace( realpath($_SERVER['DOCUMENT_ROOT']), '',realpath(ROOT_DIR)).'/';
}

function array_get_first_key($arr)
{
	foreach($arr as $k=>$v) return $k;
}

function EmailIsValid($email)
{
	return (ereg('^[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+'.'@'.'[-!#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~]+\.'.'[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+$',$email));
}

// даже не думайте использовать функции get_* в циклах или вообще больше, чем один раз!
function get_city_name_by_id($id)
{
	$res = $GLOBALS['SQL']->res('SELECT `name` FROM `#cities` WHERE `id` = ?', $id);
	
	return $res ? $res['name'] : '';
}

function get_total_ads()
{
	$r = ($GLOBALS['SQL']->res('SELECT COUNT(*) AS `TOTAL` FROM `#ads` WHERE 1'));
	
	return $r ? $r['TOTAL'] : 0;
}

function get_total_ads_for_city($id)
{
	$r = ($GLOBALS['SQL']->res('SELECT COUNT(*) AS `TOTAL` FROM `#ads` WHERE `city` = ?', $id));
	
	return $r ? $r['TOTAL'] : 0;
}
?>