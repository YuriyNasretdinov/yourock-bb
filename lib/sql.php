<?php
/* BASE COMPLECT */

class Sql
{
	var $last;
	
	var $connected = false;
	
	function connect($n1=false,$n2=false,$n3=false,$n4=false)
	{
		global $mysql, $IS_IN_ADMIN;
		
		if($n1===false)
		{
			$n1=$mysql['host'];
			$n2=$mysql['user'];
			$n3=$mysql['pass'];
			$n4=$mysql['db'];
		}
		
		$t=explode(' ',microtime());
		$t1=$t[0]+$t[1];
		
		if(!mysql_pconnect($n1,$n2,$n3)) $return=false;
		else $return=mysql_select_db($n4);
		
		mysql_query('SET NAMES utf8');
		
		$t=explode(' ',microtime());
		@$GLOBALS['SQLTIME']+=($t[0]+$t[1])-$t1;
		
		if(!$return && !$IS_IN_ADMIN) die('Извините, подключение к базе данных в данный момент невозможно. Попробуйте зайти через несколько минут.');
		
		if($return) $this->connected = true;
		return $return;
	}
	
	function query($sql)
	{
		global $IS_IN_ADMIN;
		//error_log($sql);
		
		$t=explode(' ',microtime());
		$t1=$t[0]+$t[1];
		$res=mysql_query($sql);
		
		if($res===false && /* !empty($_GET['debug']) */ true && error_reporting()!=0)
		{
			$add="";
			if(function_exists("debug_backtrace"))
			{
				$trace=debug_backtrace();
				$add=" in <b>".$trace[1]['file']."</b> on line <b>".$trace[1]['line']."</b>";
			}
			error_log('Cannot query "'.$sql.'", MySQL said that '.mysql_error().$add);
			if(!$IS_IN_ADMIN) die('Извините, произошла ошибка базы данных. Попробуйте зайти позже. Если ошибка повторится, пожалуйста, сообщите нам об ошибке.');
		}else if($res===false && error_reporting()!=0)
		{
			error_log('Cannot query "'.$sql.'", MySQL said that '.mysql_error());
			if(!$IS_IN_ADMIN) die('Извините, произошла ошибка базы данных. Попробуйте зайти позже. Если ошибка повторится, пожалуйста, сообщите нам об ошибке.');
		}
		
		//error_log('Hello world');
		
		$t=explode(' ',microtime());
		$tmp=($t[0]+$t[1])-$t1;
		$GLOBALS['SQLTIME']+=$tmp;
		
		//error_log('Hello world 2');
		
		if(!empty($_GET['debug']))
		{
			//$trace=debug_backtrace();
			
			if(strtolower(substr($sql,0,strlen('SELECT'))) == 'select')
			{
				$explain_info = mysql_fetch_assoc( mysql_query('EXPLAIN '.$sql) );
			}else
			{
				$explain_info = 'not a select';
			}
			
			$GLOBALS['SQLSTATS'][]=array('query'=>$sql,'time'=>$tmp,'rows'=>@mysql_num_rows($res), 'explain' => $explain_info);
		}
		@$GLOBALS['QUERIES']++;
		
		
		
		//$this->last=$res;
		return $res;
	}
	
	function qw()
	{
		$args=func_get_args();
		$conn=null;
		if(is_resource($args[0])) $conn=array_shift($args);
		$query=call_user_func_array(array(&$this,'mk_qw'), $args);
		return $conn!==null? $this->query($query, $conn) : $this->query($query);
	}
	
	function mk_qw()
	{
		global $mysql;
		$args=func_get_args();
		$tmpl=&$args[0];
		$tmpl=str_replace('%', '%%', $tmpl);
		$tmpl=str_replace('?', '%s', $tmpl);
		if(isset($mysql['prefix'])) $tmpl=str_replace('#', $mysql['prefix'],$tmpl);
		
		foreach ($args as $i=>$v)
		{
			if(!$i) continue;
			if(is_array($v))
			{
				$tmp=array();
				foreach($v as $val) $tmp[]="'".mysql_real_escape_string($val)."'";
				$args[$i]=implode(',',$tmp);
				continue;
			}
			$args[$i] = "'".mysql_real_escape_string($v)."'";
		}
		return call_user_func_array('sprintf', $args);
	}
	
	function fetch_assoc($i)
	{
		return @mysql_fetch_assoc($i);
	}
	
	function assoc()
	{
		return @mysql_fetch_assoc($this->last);
	}
	
	function res()
	{
		$args=func_get_args();
		$select=call_user_func_array(array(&$this,'qw'),$args);
		return $this->fetch_assoc($select);
	}
	
	function getrow($arr,$key)
	{
		return $arr[$key];
	}
	
	function fetch_row($i)
	{
		return @mysql_fetch_row($i);
	}
	
	function insert_id()
	{
		return @mysql_insert_id();
	}
	
	function error()
	{
		return mysql_error();
	}
	
	function list_tables($i)
	{
		$t=explode(' ',microtime());
		$t1=$t[0]+$t[1];
		
		$return=@mysql_list_tables($i);
		
		$t=explode(' ',microtime());
		$GLOBALS['SQLTIME']+=($t[0]+$t[1])-$t1;
		return $return;
	}
	
	function num_rows($i)
	{
		return @mysql_num_rows($i);
	}
	
	function data_seek($i,$row)
	{
		return @mysql_data_seek($i,$row);
	}
	
	function free_result($i)
	{
		return mysql_free_result($i);
	}
}
$SQL=new Sql;
$CLASSES['SQL'] = &$SQL;
if(!$SQL->connect() && !$IS_IN_ADMIN) die('Could not connect database.'.(isset($_GET['debug']) ? ' MySQL said that '.$SQL->error().'.' : ''));
?>