<?php
/**
 * HTML_SemiParser: selective fast-and-dirty tags processing via callbacks.
 * (C) 2005 Dmitry Koterov, http://forum.dklab.ru/users/DmitryKoterov/
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * See http://www.gnu.org/copyleft/lesser.html
 *
 * The main idea is to assign callbacks for specified tags and containers
 * (e.g. <a>, <input>, <img> etc.) and run them for various HTML text
 * to get result of substitutions. It could be used, for example, to
 * "on the fly" replacement in the following tasks:
 * - insert 'value="..."' attributes to <input> tags based on $_REQUEST.
 *   See HTML_FormPersister class.
 * - replace 'href='...'" in links to make "transparent" human-readable
 *   URLs for ready scripts.
 * - automatically insert "width=... height=..." into <img> tags.
 *
 * You may use this class in three different modes.
 *
 * 1. Create separate instance and then call addTag(), addContainer() etc.
 *    for it adding callback functions for each needed element:
 *
 *      $parser = new HTML_SemiParser();
 *      $parser->addTag('a', 'handleLingsCallback');
 *      echo $parser->process($text);
 *      ...
 *      function handleLingsCallback($parsedTagAttributes) { ... }
 *
 * 2. Create subclass of HTML_SemiParser and define methods with names
 *    tag_*(), container_*() and re_*() in it.
 *
 *      class HTML_ImageWidthHeightAutosetter extends HTML_SemiParser {
 *        function tag_img($parsedTagAttributes) { ... }
 *      }
 *      $parser = new HTML_ImageWidthHeightAutosetter();
 *      echo $parser->process($text);
 *
 * 3. Add all handlers from any HTML_SemiParser object to another object:
 *
 *      $parserA = new HTML_LinkParser();
 *      $parserB = new HTML_ImageWidthHeightAutosetter();
 *      $parserA->addObject($parserB);
 *
 * If two callback handlers want to use the same tag (for example, we may
 * have two callbacks for <img> tag: first - for automatically setting width
 * and height attributes, and second - to replace images by their icons),
 * handlers are called one by one, like in conveyer.
 *
 * Order of substitution:
 * - direct regular expressions;
 * - tags;
 * - containers.
 *
 * @author Dmitry Koterov
 * @version 1.108
 * @package HTML
 */
class HTML_SemiParser
{
    /**
     * Handled tags, containers and regiular expressions.
     */
    var $sp_tags = array(); // array(tagName => list( h1, h2, ...), ...)
    var $sp_cons = array();
    var $sp_res = array();
    var $sp_precachers = array();
    var $sp_postprocs = array();
    var $sp_preprocs = array();
    
    /**
     * Functions for quoting/dequoting.
     */
    var $sp_quoteHandler = null;
    var $sp_dequoteHandler = null;

    /**
     * Object-callback name prefixes.
     */
    var $sp_preTag = "tag_";        // for tag gandlers
    var $sp_preCon = "container_";  // for container handlers
    var $sp_preRe  = "re_";         // for REs

    /**
     * Characters inside tag RE (between < and >).
     */
    var $sp_reTagIn = '(?>(?xs) (?> [^>"\']+ | " [^"]* " | \' [^\']* \' )* )';
    
    /**
     * Containers, whose bodies are not parsed by the library.
     */
    var $sp_IGNORED = array('script', 'iframe', 'textarea', 'select', 'title');
    var $sp_SKIP_IGNORED = true; 

    /**
     * Local temp variables.
     */
    var $sp_replaceHash;     // unique hash to replace all the tags

    /**
     * HTML_SemiParser constructor.
     */
    function HTML_SemiParser()
    {
        // Add child handlers.
        $this->sp_selfAdd = true;
        $this->addObject($this);
        unset($this->sp_selfAdd);
        
        // Initialize quoters.
        $this->sp_quoteHandler = 'htmlspecialchars';
        $this->sp_dequoteHandler = array(get_class($this), '_unhtmlspecialchars');

        // Generate unique hash.
        static $num = 0;
        $uniq = md5(microtime() . ' ' . ++$num . ' ' . getmypid());
        $this->sp_replaceHash = $uniq;
    }

    /**
     * Add new tag handler for future processing.
     *
     * Handler is a callback which is will be for each tag found in the
     * parsed document. This callback could be used to replace tag. Here is
     * the prototype:
     *
     * mixed handler(array $attributes)
     *
     * Callback get 1 parameter - parset tag attribute array.
     * The following types instead of "mixed" is supported:
     *
     * - bool or NULL  If handler returns FALSE or NULL, source tag is
     *                 not modified.
     * - string        Returning value is used t replace original tag.
     * - array         Returning value is treated as associative array of
     *                 tag attributes. Array also contains two special
     *                 elements:
     *                 - "_tagName": name of tag;
     *                 - "_text":    string representation of tag body
     *                               (for containers only, see below).
     *                               String representation of tag will be
     *                               reconstructed automatically by that array.
     *
     * @param string   $tagName  Name of tag to handle. E.g., "a", "img" etc.
     * @param callback $handler  Callback which will be called on for found tag.
     * @return void
     */
    function addTag($tagName, $handler, $atFront=false)
    {
        $tagName = strtolower($tagName);
        if (!isSet($this->sp_tags[$tagName])) $this->sp_tags[$tagName] = array();
        if (!$atFront) array_push($this->sp_tags[$tagName], $handler);
        else array_unshift($this->sp_tags[$tagName], $handler);
        // echo "Tag added: $tagName<br>\n";
    }

    /**
     * Add the container handler.
     *
     * Containers are processed just like simple tags (see addTag()), but they also have
     * bodies saved in "_text" attribute.
     *
     * @param string    $contName   Name of container to search.
     * @param callback  $handler    Call this function to replace.
     * @return void
     */
    function addContainer($tagName, $handler, $atFront=false)
    {
        $tagName = strtolower($tagName);
        if (!isSet($this->sp_cons[$tagName])) $this->sp_cons[$tagName] = array();
        if (!$atFront) array_push($this->sp_cons[$tagName], $handler);
        else array_unshift($this->sp_cons[$tagName], $handler);
        // echo "Container added: $tagName\n";
    }

    /**
     * Add regular expression replacer.
     *
     * Use callback with one parameter: RE matched pockets.
     *
     * @param string    $re       Regular Expression to search for.
     * @param callback  $handler  Call this function to replace.
     * @return void
     */
    function addReplace($re, $handler, $atFront=false)
    {
        if (!isSet($this->sp_res[$re])) $this->sp_res[$re] = array();
        if (!$atFront) array_push($this->sp_res[$re], $handler);
        else array_unshift($this->sp_res[$re], $handler);
    }

    /**
     * Add all the callback methods from $obj.
     *
     * Types of handlers (tag, container or RE) depend on method name prefix:
     * see $sp_pre* properties above.
     *
     * @param object  $obj  Use this object methods as callbacks.
     * @return void
     */
    function addObject(&$obj, $noPrecache=false, $atFront=false)
    {
        // Search for all the derieved handlers.
        foreach (get_class_methods($obj) as $m) {
            if (strpos($m, $this->sp_preTag) === 0) {
                $this->addTag(substr($m, strlen($this->sp_preTag)), array(&$obj, $m), $atFront);
            }
            if (strpos($m, $this->sp_preCon) === 0) {
                $this->addContainer(substr($m, strlen($this->sp_preCon)), array(&$obj, $m), $atFront);
            }
            if (strpos($m, $this->sp_preRe) === 0) {
                $meth = substr($m, strlen($this->sp_preRe));
                $re = call_user_func(array(&$obj, $m));
                if ($re !== false && $re !== null) {
                    $this->addReplace($re, array(&$obj, $meth), $atFront);
                }
            }
        }
        // Add object precacher & post-processors if present.
        if (!isset($this->sp_selfAdd)) {
            foreach (array('precacheTags'=>'sp_precachers', 'postprocText'=>'sp_postprocs', 'preprocText'=>'sp_preprocs') as $pname=>$var) {
                if (method_exists($obj, $pname)) {
                    if (!$atFront) array_push($this->$var, array(&$obj, $pname));
                    else array_unshift($this->$var, array(&$obj, $pname));
                }
            }
        }
    }
    
    /**
     * Quote HTML entities. 
     * You may override this method or set $this->sp_quoteHandler property.
     * 
     * @param string $str String to quote.
     * @return string Quoted string.
     */
    function quoteHandler($value)
    {
        return call_user_func($this->sp_quoteHandler, $value);
    }
    
    /**
     * Dequote HTML entities. 
     * You may override this method or set $this->sp_dequoteHandler property.
     * 
     * @param string $str String to dequote.
     * @return string Dequoted string.
     */
    function dequoteHandler($value)
    {
        return call_user_func($this->sp_dequoteHandler, $value);
    }
    
    /**
     * Reverse function for htmlspecialchars(). 
     */
    function _unhtmlspecialchars($value)
    {
        // Generate entity translation table (only once!).
        static $sp_trans = null;
        if (!$sp_trans) {
            $sp_trans = array_flip(get_html_translation_table(HTML_SPECIALCHARS));
            $sp_trans['&#039;'] = "'"; // manually translate apostroph for FireFox
        }
        return strtr($value, $sp_trans);
    }

    /**
     * Process HTML string and call all the callbacks for it.
     *
     * @param string  $buf  HTML text.
     * @return Text after all the replaces.
     */
    function process($buf)
    {
        $reTagIn = $this->sp_reTagIn;
        
        // Preprocess the text.
        $new = $this->preprocText($buf);
        if ($new !== null) $buf = $new;
        
        // Remove ignored container bodies from the string.
        $this->sp_ignored = array();
        if ($this->sp_SKIP_IGNORED) {
            $reIgnoredNames = join("|", $this->sp_IGNORED);
            $reIgnored = "{(<($reIgnoredNames) (?> \s+ $reTagIn)? >) (.*?) (</\\2>)}six";
            // Note that we MUST increase backtrack_limit, else error
            // PREG_BACKTRACK_LIMIT_ERROR will be generated on large SELECTs
            // (see preg_last_error() in PHP5). 
            $oldLimit = ini_get('pcre.backtrack_limit');
            ini_set('pcre.backtrack_limit', 1024 * 1024 * 10);
            $buf = preg_replace_callback(
                $reIgnored,
                array(&$this, "_callbackIgnored2Hash"),
                $buf
            );
            ini_set('pcre.backtrack_limit', $oldLimit);
        }
        $sp_ignored = array($this->sp_ignored, array_keys($this->sp_ignored), array_values($this->sp_ignored));
        unset($this->sp_ignored);

        // Replace custom REs.
        if ($this->sp_res) {
            foreach ($this->sp_res as $re => $handlers) {
                foreach ($handlers as $h) {
                    $buf = preg_replace_callback($re, $h, $buf);
                }
            }
        }

        // Replace tags and containers.
        $hashlen = strlen($this->sp_replaceHash) + 10;
        $reTagNames = join("|", array_keys($this->sp_tags));
        $reConNames = join("|", array_keys($this->sp_cons));
        $infos = array();
        // (? >...) [without space] is much faster than (?:...) in this case.
        if ($this->sp_tags) 
            $infos["sp_tags"] = "/( <($reTagNames) (?> (\s+ $reTagIn) )? > () )/isx";
        if ($this->sp_cons) 
            $infos["sp_cons"] = "/( <($reConNames) (?> (\s+ $reTagIn) )? > (.*?) (?: <\\/ \\2 \\s* > | \$ ) )/isx";
        foreach ($infos as $src => $re) {
            // Split buffer into tags.
            $chunks = preg_split($re, $buf, 0, PREG_SPLIT_DELIM_CAPTURE);
            $textParts = array($chunks[0]); // unparsed text parts
            $foundTags = array();           // found tags
            for ($i=1, $n=count($chunks); $i<$n; $i+=5) {
                // $i points to sequential tag (or container) subchain.
                $tOrig    = $chunks[$i];     // - original tag text
                $tName    = $chunks[$i+1];   // - tag name
                $tAttr    = $chunks[$i+2];   // - tag attributes
                $tBody    = $chunks[$i+3];   // - container body
                $tFollow  = $chunks[$i+4];   // - following unparsed text block

                // Add tag to array for precaching.
                $tag = array(); 
                $this->parseAttrib($tAttr, $tag);
                $tag['_orig'] = $tOrig;
                $tag['_tagName'] = $tName;
                if ($src == "sp_cons") {
                    if (strlen($tBody) < $hashlen && isset($sp_ignored[0][$tBody])) {
                        // Maybe it is temporarily removed content - place back!
                        // Fast solution working in most cases (key-based hash lookup
                        // is much faster than str_replace() below).
                        $tBody = $sp_ignored[0][$tBody];
                    } else {
                        // We must pass unmangled content to container processors!
                        $tBody = str_replace($sp_ignored[1], $sp_ignored[2], $tBody);
                    }
                    $tag['_text'] = $tBody;
                } else if (substr($tAttr, -1) == '/') {
                    $tag['_text'] = null;
                }
                $foundTags[] = $tag;
                $textParts[] = $tFollow;
            }
            
            // Save original tags.
            $origTags = $foundTags;

            // Precache (possibly modifying) all the found tags (if needed).
            $this->precacheTags($foundTags);
            
            // Process all found tags and join the buffer.
            $buf = $textParts[0];
            for ($i=0, $n=count($foundTags); $i<$n; $i++) {
                $tag = $this->_runHandlersForTag($foundTags[$i]);
                if (!is_array($tag)) {
                    // String representation.
                    $buf .= $tag;
                } else {
                    $left  = isset($tag['_left'])?  $tag['_left']  : ""; unset($tag['_left']);
                    $right = isset($tag['_right'])? $tag['_right'] : ""; unset($tag['_right']);
                    if (!isset($tag['_orig']) || $tag !== $origTags[$i]) {
                        // Build the tag back if it is changed.
                        $text = $this->makeTag($tag);
                    } else {
                        // Else - use original tag string.
                        // We use this algorythm because of non-unicode tag parsing mode:
                        // e.g. entity &nbsp; in tag attributes is replaced by &amp;nbsp;
                        // in makeTag(), but if the tag is not modified at all, we do
                        // not care and do not call makeTag() at all saving original &nbsp;.
                        $text = $tag['_orig'];
                    }
                    $buf .= $left . $text . $right;
                }
                $buf .= $textParts[$i+1];
            }
        }

        // Return temporarily removed containers back.
        $buf = str_replace($sp_ignored[1], $sp_ignored[2], $buf);
        
        $new = $this->postprocText($buf);
        if ($new !== null) $buf = $new;
            
        return $buf;
    }

    /**
     * Recreate the tag or container by its parsed attributes.
     *
     * If $attr[_text] is present, make container.
     *
     * @param array  $attr  Attributes of tag. These attributes could
     *                      include two special attributes:
     *                      '_text':    tag is a container with body.
     *                                  If null - <tag ... />.
     *                                  If not present - <tag ...>.
     *                      '_tagName': name of this tag.
     *                      '_orig':    ignored (internal usage).
     *
     * @return  HTML-strict representation of tag or container.
     */
    function makeTag($attr)
    {
        // Join & return tag.
        $s = "";
        foreach($attr as $k => $v) {
            if ($k == "_text" || $k == "_tagName" || $k == "_orig") continue;
            $s .= " " . $k;
            if ($v !== null) $s .= '="' . $this->quoteHandler($v) . '"';
        }
        if (!@$attr['_tagName']) $attr['_tagName'] = "???";

        if (!array_key_exists('_text', $attr)) { // do not use isset()! 
            $tag = "<{$attr['_tagName']}{$s}>";
        } else if ($attr['_text'] === null) { // null
            $tag = "<{$attr['_tagName']}{$s} />";
        } else {
            $tag = "<{$attr['_tagName']}{$s}>{$attr['_text']}</{$attr['_tagName']}>";
        }
        return $tag;
    }

    /**
     * Virtual user-defined client precache functions.
     *
     * This function is called after all tags and containers are
     * found in HTML text, but BEFORE any replaces. It could work with
     * $foundTags to process all found data at once (for
     * faster replacing later). E.g., if callbacks use MySQL, it is
     * much more faster to perform one SQL-query with big IN() clause
     * than a lot of simple SQL querise with their own get_result()
     * calls.
     *
     * @return void
     */
    function precacheTags(&$foundTags)
    {
        foreach ($this->sp_precachers as $pk) {
            // call_user_func() does not support &-parameters 
            // while allow_call_time_pass_reference=false
            call_user_func_array($pk, array(&$foundTags));
        }
    }

    /**
     * Called after all the tags ane containers are processed,
     * but before HTML is sent to caller context.
     */
    function preprocText($buf)
    {
        foreach ($this->sp_preprocs as $pk) {
            // call_user_func() does not support &-parameters 
            // while allow_call_time_pass_reference=false
            $new = call_user_func($pk, $buf);
            if ($new !== null) $buf = $new;
        }
        return $buf;
    }
    
    /**
     * Called after all the tags ane containers are processed,
     * but before HTML is sent to caller context.
     */
    function postprocText($buf)
    {
        foreach ($this->sp_postprocs as $pk) {
            // call_user_func() does not support &-parameters 
            // while allow_call_time_pass_reference=false
            $new = call_user_func($pk, $buf);
            if ($new !== null) $buf = $new;
        }
        return $buf;
    }
    
    /**
     * Replace found ignored container body by hash value.
     * 
     * Container's open and close tags are NOT modified!
     * Later hash value will be replaced back to original text.
     */
    function _callbackIgnored2Hash($m)
    {
        static $counter = 0;
        $hash = $this->sp_replaceHash . ++$counter . "|"; 
        // DO NOT use chr(0) here!!!
        $this->sp_ignored[$hash] = $m[3];
        return $m[1] . $hash . $m[4];
    }
    
    /**
     * Process the tag.
     *
     * @param array $attr     Parsed tag.
     * @return                Attributes of processed tag.
     */
    function _runHandlersForTag($tag)
    {
        // Processing tag or container?..
        $tagName = strtolower($tag['_tagName']);
        if (isset($tag['_text'])) {
            // If $tag['_text'] === null, it is NOT a container but self-closed tag!
            // And isset(null) returns false, as we need, and we do not get here.
            $handlers = $this->sp_cons[$tagName];
        } else {
            $handlers = $this->sp_tags[$tagName];
        }
        // Use all handlers right-to-left.
        for ($i = count($handlers)-1; $i >= 0; $i--) {
            $h = $handlers[$i];
            $result = call_user_func($h, $tag, $tagName);
            // If returned false, tag is not changed.
            if ($result !== false && $result !== null) {
                // If the string is returned, stop processing now.
                if (!is_array($result)) return $result;
                // Else continue.
                $tag = $result;
            }
        }
        return $tag;
    }

    /**
     * Parse the attribute string: "a1=v1 a2=v2 ..." of the tag.
     *
     * @param  $body     Tag body between < and >.
     * @param  &$attr    Resulting Array of tag attributes
     * @return void.
     */
    function parseAttrib($body, &$attr)
    {
        $preg = '/([-\w:]+) \s* ( = \s* (?> ("[^"]*" | \'[^\']*\' | \S*) ) )?/sx';
        $regs = null;
        preg_match_all($preg, $body, $regs);
        $names = $regs[1];
        $checks = $regs[2];
        $values = $regs[3];
        $attr = array();
        for ($i = 0, $c = count($names); $i < $c; $i++) {
            $name = strtolower($names[$i]);
            if (!@$checks[$i]) {
                $value = $name;
            } else {
                $value = $values[$i];
                if ($value[0] == '"' || $value[0] == "'") {
                    $value = substr($value, 1, -1);
                }
            }
            if (strpos($value, '&') !== false)
                $value = $this->dequoteHandler($value);
            $attr[$name] = $value;
        }
    }
}
?><?php
/**
 * HTML_FormPersister: in-place "human-expectable" form tags post-processing.
 * (C) 2005 Dmitry Koterov, http://forum.dklab.ru/users/DmitryKoterov/
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * See http://www.gnu.org/copyleft/lesser.html

 * Modify HTML-forms adding "value=..." fields to <input> tags according 
 * to STANDARD PHP $_GET and $_POST variable. Also supported <select> and 
 * <textarea>.
 *
 * The simplest example:
 *
 * <?
 *   require_once 'HTML/FormPersister.php'; 
 *   ob_start(array('HTML_FormPersister', 'ob_formpersisterhandler'));
 * ? >  <!-- please remove space after "?" while testing -->
 * <form>
 *   <input type="text" name="simple" default="Enter your name">
 *   <input type="text" name="second[a][b]" default="Something">
 *   <select name="sel">
 *     <option value="1">first</option>
 *     <option value="2">second</option>
 *   </select>
 *   <input type="submit">
 * </form>
 *
 * Clicking the submit button, you see that values of text fields and 
 * selected element in list remain unchanged - the same as you entered before 
 * submitting the form! 
 *
 * The same method also works with <select multiple>, checkboxes etc. You do 
 * not need anymore to write "value=..." or "if (...) echo "selected" 
 * manually in your scripts, nor use dynamic form-field generators confusing
 * your HTML designer. Everything is done automatically based on $_GET and 
 * $_POST arrays.
 *
 * Form fields parser is based on fast HTML_SemiParser library, which 
 * performes incomplete HTML parsing searching for only needed tags. On most 
 * sites (especially XHTML) it is fully acceptable. Parser is fast: if
 * there are no one form elements in the page, it returns immediately, don't
 * ever think about overhead costs of parsing.
 *
 * @author Dmitry Koterov 
 * @version 1.111
 * @package HTML 
 */
 
class HTML_FormPersister extends HTML_SemiParser 
{
    /**
     * Constructor. Create new FormPersister instance.
     */
    function HTML_FormPersister()
    {
        $this->HTML_SemiParser();
    }

    /**
     * Process HTML text.
     *
     * @param string $st  Input HTML text.
     * @return HTML text with all substitutions.
     */
    function process($st)
    {
        $this->fp_autoindexes = array();
        return HTML_SemiParser::process($st);
    } 

    /**
     * Static handler for ob_start().
     *
     * Usage:
     *   ob_start(array('HTML_FormPersister', 'ob_formpersisterhandler'));
     *
     * Of course you may not use OB handling but call process() manually
     * in your scripts.
     *
     * @param string $html  Input HTML text.
     * @return processed output with all form fields modified.
     */
    function ob_formPersisterHandler($st)
    {
        $fp =& new HTML_FormPersister();
        $r = $fp->process($st);
        return $r;
    } 


    /**
     * Tag and container callback handlers.
     * See usage of HTML_SemiParser.
     */

    /**
     * <FORM> tag handler (add default action attribute).
     * See HTML_SemiParser.
     */
    function tag_form($attr)
    {
        if (isset($attr['action'])) return;
        if (strtolower(@$attr['method']) == 'get') {
            $attr['action'] = preg_replace('/\?.*/s', '', $_SERVER['REQUEST_URI']);
        } else { 
            $attr['action'] = $_SERVER['REQUEST_URI'];
        }
        return $attr;
    }
    
    /**
     * <INPUT> tag handler.
     * See HTML_SemiParser.
     */
    function tag_input($attr)
    {
        static $uid = 0;
        switch (@strtolower($attr['type'])) {
            case 'radio':
                if (!isset($attr['name'])) return;
                if (isset($attr['checked']) || !isset($attr['value'])) return;
                if ($attr['value'] == $this->getCurValue($attr)) $attr['checked'] = 'checked';
                else unSet($attr['checked']);
                break;
            case 'checkbox':
                if (!isset($attr['name'])) return;
                if (isset($attr['checked'])) return;
                if (!isset($attr['value'])) $attr['value'] = 'on';
                if ($this->getCurValue($attr, true)) $attr['checked'] = 'checked';
                break;
            case 'image':
            case 'submit':
                if (isset($attr['confirm'])) {
                    $attr['onclick'] = 'return confirm("' . $attr['confirm'] . '")';
                    unSet($attr['confirm']);
                } 
                break;
            case 'text': case 'password': case 'hidden': case '':
            default:
                if (!isset($attr['name'])) return;
                if (!isset($attr['value']))
                    $attr['value'] = $this->getCurValue($attr);
                break;
        }
        // Handle label pseudo-attribute. Button is placed RIGHTER
        // than the text if label text ends with "^". Example:
        // <input type=checkbox label="hello">   ==>  [x]hello
        // <input type=checkbox label="hello^">  ==>  hello[x]
        if (isset($attr['label'])) {
            $text = $attr['label'];
            if (!isset($attr['id'])) $attr['id'] = 'FPlab' . ($uid++);
            $right = 1;
            if ($text[strlen($text)-1] == '^') {
                $right = 0;
                $text = substr($text, 0, -1);
            } 
            unSet($attr['label']);
            $attr[$right? '_right' : '_left'] = '<label for="'.$this->quoteHandler($attr['id']).'">' . $text . '</label>';
        }
        // We CANNOT return $orig_attr['_orig'] if attributes are not modified,
        // because we know nothing about following handlers. They may need
        // the parsed attributes, not a plain text.
        unset($attr['default']);
        return $attr;
    } 

    /**
     * <TEXTAREA> tag handler.
     * See HTML_SemiParser.
     */
    function container_textarea($attr)
    {
        if (trim($attr['_text']) == '') {
            $attr['_text'] = $this->quoteHandler($this->getCurValue($attr));
        }
        unset($attr['default']);
        return $attr;
    } 

    /**
     * <SELECT> tag handler.
     * See HTML_SemiParser.
     */
    function container_select($attr)
    { 
        if (!isset($attr['name'])) return;
        
        // Multiple lists MUST contain [] in the name.
        if (isset($attr['multiple']) && strpos($attr['name'], '[]') === false) {
            $attr['name'] .= '[]';
        }

        $curVal = $this->getCurValue($attr);
        $body = "";

        // Get some options from variable?
        // All the text outside <option>...</option> container are treated as variable name.
        // E.g.: <select...> <option>...</option> ... some[global][options] ... <option>...</option> ... </select>
        $attr['_text'] = preg_replace_callback('{
                (
                    (?:^ | </option> | </optgroup> | <optgroup[^>]*>) 
                    \s*
                )
                \$?
                ( [^<>\s]+ ) # variable name
                (?=
                    \s*
                    (?:$ | <option[\s>] | <optgroup[\s>] | </optgroup>) 
                )
            }six', 
            array(&$this, '_optionsFromVar_callback'), 
            $attr['_text']
        );
        
        // Parse options, fetch its values and save them to array.
        // Also determine if we have at least one selected option.
        $body = $attr['_text'];
        $parts = preg_split("/<option\s*({$this->sp_reTagIn})>/si", $body, -1, PREG_SPLIT_DELIM_CAPTURE); 
        $hasSelected = 0;
        for ($i = 1, $n = count($parts); $i < $n; $i += 2) {
            $opt = array();
            $this->parseAttrib($parts[$i], $opt);
            if (isset($opt['value'])) {
                $value = $opt['value'];
            } else {
                // Option without value: spaces are shrinked (experimented on IE).
                $text = preg_replace('{</?(option|optgroup)[^>]*>.*}si', '', $parts[$i + 1]);
                $value = trim($text);
                $value = preg_replace('/\s\s+/', ' ', $value);
                if (strpos($value, '&') !== false) {
                    $value = $this->_unhtmlspecialchars($value);
                }
            }
            if (isset($opt['selected'])) $hasSelected++;
            $parts[$i] = array($opt, $value);
        }

        // Modify options list - add selected attribute if needed, but ONLY
        // if we do not already have selected options!
        if (!$hasSelected) {
            foreach ($parts as $i=>$parsed) {
                if (!is_array($parsed)) continue;
                list ($opt, $value) = $parsed;
                if (isset($attr['multiple'])) {
                    // Inherit some <select> attributes.
                    if ($this->getCurValue($opt + $attr + array('value'=>$value), true)) { // merge
                        $opt['selected'] = 'selected';
                    }
                } else {
                    if ($curVal == $value) {
                        $opt['selected'] = 'selected';
                    }
                }
                $opt['_tagName'] = 'option';
                $parts[$i] = $this->makeTag($opt);
            }
            $body = join('', $parts);
        }
 
        $attr['_text'] = $body;
        unset($attr['default']);
        return $attr;
    }

    /**
     * Other methods.
     */

    /**
     * Create set of <option> tags from array.
     */
    function makeOptions($options, $curId = false)
    {
        $body = '';
        foreach ($options as $k=>$text) {
            if (is_array($text)) {
                // option group
                $options = '';
                foreach ($text as $ko=>$v) {
                    $opt = array('_tagName'=>'option', 'value'=>$ko, '_text'=>$this->quoteHandler(strval($v)));
                    if ($curId !== false && strval($curId) === strval($ko)) {
                        $opt['selected'] = "selected";
                    }
                    $options .= HTML_SemiParser::makeTag($opt);
                }
                $grp = array('_tagName'=>'optgroup', 'label'=>$k, '_text'=>$options);
                $body .= HTML_SemiParser::makeTag($grp);
            } else {
                // single option
                $opt = array('_tagName'=>'option', 'value'=>$k, '_text'=>$this->quoteHandler($text));
                if ($curId !== false && strval($curId) === strval($k)) {
                    $opt['selected'] = "selected";
                }
                $body .= HTML_SemiParser::makeTag($opt);
            } 
        }
        return $body;
    }

    /**
     * Value extractor.
     *
     * Try to find corresponding entry in $_POST, $_GET etc. for tag 
     * with name attribute $attr['name']. Support complex form names
     * like 'fiels[one][two]', 'field[]' etc.
     *
     * If $isBoolean is set, always return true or false. Used for 
     * checkboxes and multiple selects (names usually trailed with "[]",
     * but may not be trailed too).
     *
     * @return Current "value" of specified tag.
     */
    function getCurValue($attr, $isBoolean = false)
    {
        $name = @$attr['name'];
        if ($name === null) return null; 
        $isArrayLike = false; // boolean AND contain [] in the name
        // Handle boolean fields.
        if ($isBoolean && false !== ($p = strpos($name, '[]'))) {
            $isArrayLike = true;
            $name = substr($name, 0, $p) . substr($name, $p + 2);
        } 
        // Search for value in ALL arrays,
        // EXCEPT $_REQUEST, because it also holds Cookies!
        $fromForm = true;
        if (false !== ($v = $this->_deepFetch($_POST, $name, $this->fp_autoindexes[$name]))) $value = $v;
        elseif (false !== ($v = $this->_deepFetch($_GET, $name, $this->fp_autoindexes[$name]))) $value = $v;
        elseif (isset($attr['default'])) {
            $value = $attr['default'];
            if ($isBoolean) return $value !== '' && $value !== "0";
            // For array fields it is possible to enumerate all the
            // values in SCALAR using ';'.
            if ($isArrayLike && !is_array($value)) $value = explode(';', $value);
            $fromForm = false;
        } else {
           $value = '';
        }
        if ($fromForm) {
            // Remove slashes on stupid magic_quotes_gpc mode.
            // TODO: handle nested arrays too!
            if (is_scalar($value) && get_magic_quotes_gpc() && !@constant('MAGIC_QUOTES_GPC_DISABLED')) { 
                $value = stripslashes($value);
            }
        }
        // Return value depending on field type.
        $attrValue = strval(isset($attr['value'])? $attr['value'] : 'on');
        if ($isArrayLike) {
            // Array-like field? If present, return true.
            if (!is_array($value)) return false;
            // Unfortunately we MUST use strict mode in in_array()
            // and cast array values to string before checking.
            // This is because in_array(0, array('one')) === true.
            return in_array(strval($attrValue), array_map('strval', $value), true);
        } else {
            if ($isBoolean) {
                // Non-array boolean elements must be equal to values to match.
                return (bool)@strval($value) === (bool)$attrValue;
            } else {
                // This is not boolean nor array field. Return it now.
                return @strval($value);
            }
        } 
    } 

    /**
     * Fetch an element of $arr array using "complex" key $name.
     *
     * $name can be in form of "zzz[aaa][bbb]", 
     * it means $arr[zzz][aaa][bbb].
     *
     * If $name contain auto-indexed parts (e.g. a[b][]), replace
     * it by corresponding indexes.
     * 
     * $name may be scalar name or array (already splitted name,
     * see _splitMultiArray() method).
     * 
     * @param array &$arr          Array to fetch from.
     * @param mixed &$name         Complex form-field name.
     * @param array &$autoindexes  Container to hold auto-indexes
     * @return found value, or false if $name is not found.
     */
    function _deepFetch(&$arr, &$name, &$autoindexes) // static
    {
        if (is_scalar($name) && strpos($name, '[') === false) {
            // Fast fetch.            
            return isset($arr[$name])? $arr[$name] : false;
        }
        // Else search into deep.
        $parts = HTML_FormPersister::_splitMultiArray($name);
        $leftPrefix = '';
        foreach ($parts as $i=>$k) {
            if (!strlen($k)) {
                // Perform auto-indexing.
                if (!isset($autoindexes[$leftPrefix])) $autoindexes[$leftPrefix] = 0;
                $parts[$i] = $k = $autoindexes[$leftPrefix]++;
            }
            if (!is_array($arr)) {
                // Current container is not array.
                return false;
            }
            if (!array_key_exists($k, $arr)) {
                // No such element.
                return false;
            }
            $arr = &$arr[$k];
            $leftPrefix = strlen($leftPrefix)? $leftPrefix . "[$k]" : $k;
        }
        if (!is_scalar($name)) {
            $name = $parts;
        } else {
            $name = $leftPrefix;
        }
        return $arr;
    } 

    /**
     * Highly internal function. Must be re-written if some new
     * version of would support syntax like "zzz['aaa']['b\'b']" etc.
     * For "zzz[aaa][bbb]" returns array(zzz, aaa, bbb).
     */
    function _splitMultiArray($name) // static
    {
        if (is_array($name)) return $name;
        if (strpos($name, '[') === false) return array($name);
        $regs = null;
        preg_match_all('/ ( ^[^[]+ | \[ .*? \] ) (?= \[ | $) /xs', $name, $regs);
        $arr = array();
        foreach ($regs[0] as $s) {
            if ($s[0] == '[') $arr[] = substr($s, 1, -1);
            else $arr[] = $s;
        } 
        return $arr;
    }

    /**
     * Callback function to replace variables in <select> body by set of options.
     */
    function _optionsFromVar_callback($p)
    {
        $dummy = array();
        $name = trim($p[2]);
        $options = $this->_deepFetch($GLOBALS, $name, $dummy);
        if ($options === null || $options === false) return $p[1] . "<option>???</option>";
        return $p[1] . $this->makeOptions($options);
    }
}
