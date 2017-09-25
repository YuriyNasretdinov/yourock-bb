<?

class Tpl
{
    var $tpls=array();
    var $ptpls=array(); //parsed templates
    var $wtpls=array(); //while templates (blocks, used many times)
    var $pwtpls=array(); //parsed while templates
    
    function _evaller($data)
    {
        ob_start();
        eval($data[1]);
        return ob_get_clean();
    }
    
    function file2string($file)
    {
        if(file_exists($file) && is_readable($file))
        {
            $f=fopen($file,"rb");
            $filecontent=fread($f,filesize($file));
            fclose($f);
            return $filecontent;
        }else
        {
            return false;
        }
    }
    
    function loadtpl($tpl)
    {
        if($result=$this->file2string($tpl))
        {
            $this->tpls[$tpl]=trim(preg_replace('/\<\!\-\-.*\-\-\>/sU','',preg_replace_callback('/\<\?(.*)\?\>/sU',array(&$this,'_evaller'),$result)));
            return true;
        }
        return false;
    }
    
    function parse($rarray,$tpl) //replace array ( array('title' => 'Welcome', 'body' => 'This is an example',) )
    {
        if(!isset($this->tpls[$tpl]) or !is_array($rarray)) return false;
        
        $tpltxt/*template text*/=$this->tpls[$tpl];
        $new_rarray = array();
        foreach($rarray as $k=>$v) $new_rarray['{'.$k.'}'] = $v;
        
        $tpltxt = strtr($tpltxt, $new_rarray);
        
        $this->ptpls[$tpl]=$tpltxt;
        return true;
    }
    
    function getparsedtext($tpl)
    {
        if(!isset($this->ptpls[$tpl])) return false;
        
        return $this->ptpls[$tpl];
    }
    
    function loadwtpl($tpl,$delimiter="<!--parts-separator-->")
    {
        if($result=$this->file2string($tpl))
        {
            $this->wtpls[$tpl]=array_map('trim',preg_replace('/\<\!\-\-.*\-\-\>/sU','',explode($delimiter, preg_replace_callback('/\<\?(.*)\?\>/isU',array(&$this,'_evaller'),$result))));
            return true;
        }
        return false;
    }
    
    function wparse($rarray,$tpl,$n) //replace array ( array('title' => 'Welcome', 'body' => 'This is an example',) )
    {
        if(!isset($this->wtpls[$tpl][$n]) or !is_array($rarray)) return false;
        
        $tpltxt/*template text*/=$this->wtpls[$tpl][$n];
        $new_rarray = array();
        foreach($rarray as $k=>$v) $new_rarray['{'.$k.'}'] = $v;
        
        $tpltxt = strtr($tpltxt, $new_rarray);
        
        $this->pwtpls[$tpl][$n]=$tpltxt;
        return true;
    }
    
    function wparseall($rarray,$tpl)
    {
        if(!isset($this->wtpls[$tpl]) or !is_array($rarray)) return false;
        
        foreach($rarray as $key=>$value)
        {
            $this->wtpls[$tpl]=str_replace('{'.$key.'}', $value, $this->wtpls[$tpl]);
        }
        
        return true;
    }
    
    function getwparsedtext($tpl,$n)
    {
        if(!isset($this->pwtpls[$tpl][$n])) return false;
        
        return $this->pwtpls[$tpl][$n];
    }
    
    function fastparse($tpl,$rarray=array(),$echo=true)
    {
        if(!isset($this->tpls[$tpl])) $this->loadtpl($tpl);
        $this->parse($rarray,$tpl);
        if($echo) echo $this->getparsedtext($tpl);
        else return $this->getparsedtext($tpl);
    }
    
    function fastwparse($tpl,$rarray=array(),$num=0,$echo=true)
    {
        if(!isset($this->wtpls[$tpl])) $this->loadwtpl($tpl);
        $this->wparse($rarray,$tpl,$num);
        if($echo) echo $this->getwparsedtext($tpl,$num);
        else return $this->getwparsedtext($tpl,$num);
    }
}

$TPL = new Tpl;
$CLASSES['TPL'] = &$TPL;
?>