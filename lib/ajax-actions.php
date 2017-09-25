<?
$REQ = new JsHttpRequest('UTF-8');

switch(@$uri_parts[1])
{
    default:
        echo 'Undefined action';
        $_RESULT = false;
        break;
    
    /* Разделы */
    
    case 'get-cat':
        $_RESULT = $SQL->res('SELECT * FROM `#parts` WHERE `id` = ?', $_REQUEST['id']);
        break;
    case 'set-cat':
        $_RESULT = $SQL->qw('UPDATE `#parts` SET `name` = ?, `engname` = ?, `description` = ? WHERE `id` = ?', $_REQUEST['name'], $_REQUEST['engname'], $_REQUEST['description'], $_REQUEST['id']);
        break;
    case 'add-cat':
        $_RESULT = $SQL->qw('INSERT INTO `#parts` SET `name` = ?, `engname` = ?, `description` = ?', $_REQUEST['name'], $_REQUEST['engname'], $_REQUEST['description']);
        $id = $SQL->insert_id();
        extract( $SQL->res('SELECT (MAX(`priority`)+1) as `pr` FROM `#parts`') );
        
        $SQL->qw('UPDATE `#parts` SET `priority` = ? WHERE `id` = ?', $pr, $id);
        
        break;
    case 'delete-cat':
        $_RESULT = $SQL->qw('DELETE FROM `#parts` WHERE `id` = ?', $_REQUEST['id']);
        $SQL->qw('DELETE FROM `#subparts` WHERE `pid` = ?', $_REQUEST['id']);
        
        // результат может быть просто огромен, поэтому мы используем unbuffered_query
        $sel = mysql_unbuffered_query('SELECT `id`, `img`, `add_img1`, `add_img2`, `add_img3` FROM `'.$mysql['prefix'].'ads` WHERE `cat_id` = '.intval($_REQUEST['id']));
        $p = ROOT_DIR.'/'.$UPL_IMG_ROOT.'/';
        
        while($res = $SQL->fetch_assoc($sel))
        {
            foreach(explode(' ', 'img add_img1 add_img2 add_img3') as $v)
            {
                if(!empty($res[$v]))
                {
                    foreach(explode(';', $res[$v]) as $val) unlink($p.trim($val));
                }
            }
        }
        
        
        $SQL->qw('DELETE FROM `#ads` WHERE `cat_id` = ?', $_REQUEST['id']);
        break;
    case 'get-scat':
        $_RESULT = $SQL->res('SELECT * FROM `#subparts` WHERE `id` = ?', $_REQUEST['id']);
        break;
    case 'set-scat':
        $_RESULT = $SQL->qw('UPDATE `#subparts` SET `name` = ?, `engname` = ?, `description` = ? WHERE `id` = ?', $_REQUEST['name'], $_REQUEST['engname'], $_REQUEST['description'], $_REQUEST['id']);
        break;
    case 'add-scat':
        $_RESULT = $SQL->qw('INSERT INTO `#subparts` SET `pid` = ?,`name` = ?, `engname` = ?, `description` = ?', $_REQUEST['pid'], $_REQUEST['name'], $_REQUEST['engname'], $_REQUEST['description']);
        $id = $SQL->insert_id();
        extract( $SQL->res('SELECT (MAX(`priority`)+1) as `pr` FROM `#subparts` WHERE `pid` = ?', $_REQUEST['pid']) );
        
        $SQL->qw('UPDATE `#subparts` SET `priority` = ? WHERE `id` = ?', $pr, $id);
        break;
    case 'delete-scat':
        $_RESULT = $SQL->qw('DELETE FROM `#subparts` WHERE `id` = ?', $_REQUEST['id']);
        
        // результат может быть просто огромен, поэтому мы используем unbuffered_query
        $sel = mysql_unbuffered_query('SELECT `id`, `img`, `add_img1`, `add_img2`, `add_img3` FROM `'.$mysql['prefix'].'ads` WHERE `scat_id` = '.intval($_REQUEST['id']));
        $p = ROOT_DIR.'/'.$UPL_IMG_ROOT.'/';
        
        while($res = $SQL->fetch_assoc($sel))
        {
            foreach(explode(' ', 'img add_img1 add_img2 add_img3') as $v)
            {
                if(!empty($res[$v]))
                {
                    foreach(explode(';', $res[$v]) as $val) unlink($p.trim($val));
                }
            }
        }
        
        $SQL->qw('DELETE FROM `#ads` WHERE `scat_id` = ?', $_REQUEST['id']);
        break;
    case 'raise-cat':
    case 'raise-scat':
    case 'lower-cat':
    case 'lower-scat':
        $sub = $uri_parts[1] == 'raise-scat' || $uri_parts[1] == 'lower-scat' ? 'sub' : '';
        
        $operation = $uri_parts[1] == 'raise-scat' || $uri_parts[1] == 'raise-cat' ? '<' : '>';
        $order = $uri_parts[1] == 'raise-scat' || $uri_parts[1] == 'raise-cat' ? 'DESC' : 'ASC';
        
        $res = $SQL->res('SELECT * FROM `#'.$sub.'parts` WHERE `id` = ?', $_REQUEST['id']);
        
        $add_where = $sub ? ' AND `pid`='.intval($res['pid']) : '';
        
        $new_res = $SQL->res('SELECT * FROM `#'.$sub.'parts` WHERE `priority` '.$operation.' ?'.$add_where.' ORDER BY `priority` '.$order.' LIMIT 1', $res['priority']);
        
        $SQL->qw('UPDATE `#'.$sub.'parts` SET `priority` = ? WHERE `id` = ?', $res['priority'], $new_res['id']);
        $SQL->qw('UPDATE `#'.$sub.'parts` SET `priority` = ? WHERE `id` = ?', $new_res['priority'], $res['id']);
        
        $_RESULT = true;
        break;
    
    /* Объявления */
    
    case 'delete-ad':
    case 'delete-ads':
    case 'delete-pending-ad':
    case 'delete-pending-ads':
        if(empty($_REQUEST['ids']) && !empty($_REQUEST['id'])) $_REQUEST['ids'] = array($_REQUEST['id']);
        
        $pending = ($uri_parts[1] == 'delete-pending-ad' || $uri_parts[1] == 'delete-pending-ads') ? '_pending' : '';
        
        $sel = $SQL->qw('SELECT * FROM `#ads'.$pending.'` WHERE `id` IN(?)', $_REQUEST['ids']);
        
        $p = ROOT_DIR.'/'.$UPL_IMG_ROOT.'/';
        $t = explode(' ', 'img add_img1 add_img2 add_img3');
        
        while($res = $SQL->fetch_assoc($sel))
        {
            foreach($t as $v)
            {
                if(!empty($res[$v])) foreach(explode(';', $res[$v]) as $val) unlink($p.trim($val));
            }
        }
        
        $_RESULT = $SQL->qw('DELETE FROM `#ads'.$pending.'` WHERE `id` IN(?)', $_REQUEST['ids']);
        break;
    
    case 'confirm-pending-ads':
        $sel = $SQL->qw('SELECT * FROM `#ads_pending` WHERE `id` IN(?)', $_REQUEST['ids']);
        
        while($res = $SQL->fetch_assoc($sel))
        {
            $fields = array();
            
            foreach($res as $k=>$v)
            {
                if($k == 'id') continue;
                
                $fields[] = '`'.$k.'` = \''.mysql_real_escape_string($v).'\'';
            }
            
            $sql = 'INSERT INTO `'.$mysql['prefix'].'ads` SET '.implode(',',$fields);
            @$SQL->query($sql);
        }
        
        $SQL->qw('DELETE FROM `#ads_pending` WHERE `id` IN(?)', $_REQUEST['ids']);
        
        $_RESULT = true;
        break;
    
    /* Города */
    
    case 'add-region':
        $_RESULT = $SQL->qw('INSERT INTO `#regions` SET `name` = ?', $_REQUEST['name']);
        break;
    
    case 'edit-region':
        $_RESULT = $SQL->qw('UPDATE `#regions` SET `name` = ? WHERE `id` = ?', $_REQUEST['name'], $_REQUEST['id']);
        break;
    
    case 'edit-city':
        $_RESULT = $SQL->qw('UPDATE `#cities` SET `name` = ? WHERE `id` = ?', $_REQUEST['name'], $_REQUEST['id']);
        break;
    
    case 'add-city':
        $_RESULT = $SQL->qw('INSERT INTO `#cities` SET `name` = ?, `region` = ?', $_REQUEST['name'], $_REQUEST['region']);
        break;
    
    case 'delete-region':
        $id = $_REQUEST['id'];
        
        // выбираем случайный регион для объявлений, для которых была указана эта область
        
        $rres = $SQL->res('SELECT * FROM `#regions` WHERE `id` <> ? ORDER BY RAND() LIMIT 1', $id);
        $cres = $SQL->res('SELECT * FROM `#cities` WHERE `region` = ? ORDER BY RAND() LIMIT 1', $rres['id']);
        
        $new_region = $rres['id'];
        $new_city   = $cres['id'];
        
        $SQL->qw('UPDATE `#ads` SET `region` = ?, `city` = ? WHERE `region` = ?', $new_region, $new_city, $id);
        
        $SQL->qw('DELETE FROM `#regions` WHERE `id` = ?', $id);
        $SQL->qw('DELETE FROM `#cities` WHERE `region` = ?', $id);
        
        $SQL->qw('OPTIMIZE TABLE `#regions`');
        $SQL->qw('OPTIMIZE TABLE `#cities`');
        
        $_RESULT = true;
        
        break;
    
    case 'delete-city':
        
        $id = $_REQUEST['id'];
        
        // выбираем случайный город вместо того, который был
        
        $cres = $SQL->res('SELECT * FROM `#cities` WHERE `id` <> ? ORDER BY RAND() LIMIT 1', $id);
        
        $new_region = $cres['region'];
        $new_city   = $cres['id'];
        
        $SQL->qw('UPDATE `#ads` SET `region` = ?, `city` = ? WHERE `city` = ?', $new_region, $new_city, $id);
        
        $SQL->qw('DELETE FROM `#cities` WHERE `id` = ?', $id);
        $SQL->qw('OPTIMIZE TABLE `#cities`');
        
        $_RESULT = true;
        break;
    
    /* Бан */
    
    case 'ban-new-ip':
        $list = get_ip_list();
        $list = add_new_ip($list, $_REQUEST['ip']);
        
        $_RESULT = write_ip_list($list);
        
        break;
    
    case 'unban-ip':
        $list = get_ip_list();
        $list = remove_ip($list, $_REQUEST['ip']);
        
        $_RESULT = write_ip_list($list);
        break;
}
?>