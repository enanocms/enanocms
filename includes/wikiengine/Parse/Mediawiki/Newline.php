<?php

/**
* 
* Parses for implied line breaks indicated by newlines.
* 
* @category Text
* 
* @package Text_Wiki
* 
* @author Paul M. Jones <pmjones@php.net>
* 
* @license LGPL
* 
* @version $Id: Newline.php,v 1.3 2005/02/23 17:38:29 pmjones Exp $
* 
*/

/**
* 
* Parses for implied line breaks indicated by newlines.
* 
* This class implements a Text_Wiki_Parse to mark implied line breaks in the
* source text, usually a single carriage return in the middle of a paragraph
* or block-quoted text.
*
* @category Text
* 
* @package Text_Wiki
* 
* @author Paul M. Jones <pmjones@php.net>
* 
*/

class Text_Wiki_Parse_Newline extends Text_Wiki_Parse {
    
    
    /**
    * 
    * The regular expression used to parse the source text and find
    * matches conforming to this rule.  Used by the parse() method.
    * 
    * @access public
    * 
    * @var string
    * 
    * @see parse()
    * 
    */
    
    var $regex = '/([^\n])\n([^\n])/m';
    
    
    /**
    * 
    * Generates a replacement token for the matched text.
    * 
    * @access public
    *
    * @param array &$matches The array of matches from parse().
    *
    * @return string A delimited token to be used as a placeholder in
    * the source text.
    *
    */
    
    function process(&$matches)
    {    
        return $matches[1] .
            $this->wiki->addToken($this->rule) .
            $matches[2];
    }
    
    /**
    *
    * Abstrct method to parse source text for matches.
    *
    * Applies the rule's regular expression to the source text, passes
    * every match to the process() method, and replaces the matched text
    * with the results of the processing.
    *
    * @access public
    *
    * @see Text_Wiki_Parse::process()
    *
    */

    function parse()
    {
        $source =& $this->wiki->source;
        
        // This regex attempts to find HTML tags that can be safely compacted together without formatting loss
        // The idea is to make it easier for the HTML parser to find litewiki elements
        //$source = preg_replace('/<\/([a-z0-9:-]+?)>([\s]*[\n]+[\s]+|[\s]+[\n]+[\s]*|[\n]+)<([a-z0-9:-]+)(.*?)>/i', '</\\1><\\3\\4>', $source);
        $source = wikiformat_process_block($source);
        
        $rand_key = md5( str_rot13(strval(dechex(time()))) . microtime() . strval(mt_rand()) );
        preg_match_all('/<(litewiki|pre)([^>]*?)>(.*?)<\/\\1>/is', $this->wiki->source, $matches);
        
        $poslist = array();
        
        foreach ( $matches[0] as $i => $match )
        {
            $source = str_replace($match, "{LITEWIKI:$i:$rand_key}", $source);
        }
        
        $this->wiki->source = preg_replace_callback(
            $this->regex,
            array(&$this, 'process'),
            $this->wiki->source
        );
        
        foreach ( $matches[3] as $i => $match )
        {
            $source = str_replace("{LITEWIKI:$i:$rand_key}", $match, $source);
        }
        
        // die('<pre>'.htmlspecialchars($source).'</pre>');
        
        unset($matches, $source, $rand_key);
    }
}

?>