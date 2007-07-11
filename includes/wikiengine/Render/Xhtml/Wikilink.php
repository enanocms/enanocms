<?php
// vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4:
/**
 * Wikilink rule end renderer for Xhtml
 *
 * PHP versions 4 and 5
 *
 * @category   Text
 * @package    Text_Wiki
 * @author     Paul M. Jones <pmjones@php.net>
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    CVS: $Id: Wikilink.php,v 1.17 2006/02/28 03:15:09 justinpatrin Exp $
 * @link       http://pear.php.net/package/Text_Wiki
 */

/**
 * This class renders wiki links in XHTML.
 *
 * @category   Text
 * @package    Text_Wiki
 * @author     Paul M. Jones <pmjones@php.net>
 * @license    http://www.gnu.org/copyleft/lesser.html  LGPL License 2.1
 * @version    Release: @package_version@
 * @link       http://pear.php.net/package/Text_Wiki
 */
class Text_Wiki_Render_Xhtml_Wikilink extends Text_Wiki_Render {

  var $conf;
  
  function Text_Wiki_Render_Xhtml_Wikilink() {
    $_utemp = contentPath.'%s';
    $this->conf = array(
        'pages' => array(), // set to null or false to turn off page checks
        'view_url' => $_utemp,
        'new_url'  => $_utemp,
        'new_text' => ' [x]',
        'new_text_pos' => false, // 'before', 'after', or null/false
        'css' => null,
        'css_new' => null,
        'exists_callback' => 'isPage' // call_user_func() callback
    );
  }

    /**
    *
    * Renders a token into XHTML.
    *
    * @access public
    *
    * @param array $options The "options" portion of the token (second
    * element).
    *
    * @return string The text rendered from the token options.
    *
    */

    function token($options)
    {
        global $session;
        if ( $session->sid_super )
        {
          $as = htmlspecialchars(urlSeparator) . 'auth='.$session->sid_super;
        }
        else
        {
          $as = '';
        }
        // make nice variable names (page, anchor, text)
        extract($options);

        // is there a "page existence" callback?
        // we need to access it directly instead of through
        // getConf() because we'll need a reference (for
        // object instance method callbacks).
        if (isset($this->conf['exists_callback'])) {
            $callback =& $this->conf['exists_callback'];
        } else {
        	$callback = false;
        }
        
        $page = sanitize_page_id( $page );

        if ($callback) {
            // use the callback function
            $exists = call_user_func($callback, $page);
        } else {
            // no callback, go to the naive page array.
            $list = $this->getConf('pages');
            if (is_array($list)) {
                // yes, check against the page list
                $exists = in_array($page, $list);
            } else {
                // no, assume it exists
                $exists = true;
            }
        }

        // convert *after* checking against page names so as not to mess
        // up what the user typed and what we're checking.
        //$page = $this->urlEncode($page);
        $anchor = $this->urlEncode($anchor);
        // $text = $this->textEncode($text);
        
        // hackish fix for the "external" image in Oxygen [added for Enano]
        if ( preg_match('/<(.+?)>/is', $text) )
        {
          $nobg = ' style="background-image: none; padding-right: 0;"';
        }
        else
        {
          $nobg = '';
        }
        
        // does the page exist?
        if ($exists) {

            // PAGE EXISTS.

            // link to the page view, but we have to build
            // the HREF.  we support both the old form where
            // the page always comes at the end, and the new
            // form that uses %s for sprintf()
            $href = $this->getConf('view_url');

            if (strpos($href, '%s') === false) {
                // use the old form (page-at-end)
                $href = $href . $page . $anchor;
            } else {
                // use the new form (sprintf format string)
                $href = sprintf($href, $page . $anchor);
            }

            // get the CSS class and generate output
            $css = $this->formatConf(' class="%s"', 'css');

            $start = '<a'.$css.' href="'.$href.$as.'"'.$nobg.'>';
            $end = '</a>';
        } else {

            // PAGE DOES NOT EXIST.

            // link to the page view, but we have to build
            // the HREF.  we support both the old form where
            // the page always comes at the end, and the new
            // form that uses %s for sprintf()
            $href = $this->getConf('view_url');

            if (strpos($href, '%s') === false) {
                // use the old form (page-at-end)
                $href = $href . $page . $anchor;
            } else {
                // use the new form (sprintf format string)
                $href = sprintf($href, $page . $anchor);
            }

            // get the CSS class and generate output
            $css = $this->formatConf(' class="%s"', 'css');

            $start = '<a'.$css.' href="'.$href.$as.'"'.$nobg.' class="wikilink-nonexistent">';
            $end = '</a>';
            
        }
        if (!strlen($text)) {
            $start .= $this->textEncode($options['page']);
        }
        if (isset($type)) {
            switch ($type) {
            case 'start':
                $output = $start;
                break;
            case 'end':
                $output = $end;
                break;
            }
        } else {
            $output = $start.$text.$end;
        }
        return $output;
    }
}
?>
