<?php
/**
 * Parse structured wiki text and render into arbitrary formats such as XHTML.
 *
 * PHP versions 4 and 5
 *
 * @category   Text
 * @package    Text_Wiki
 * @author     Paul M. Jones <pmjones@php.net>
 * @license    http://www.gnu.org/licenses/lgpl.html
 * @version    CVS: $Id: Wiki.php,v 1.44 2006/03/02 04:04:59 justinpatrin Exp $
 * @link       http://wiki.ciaweb.net/yawiki/index.php?area=Text_Wiki
 *
 * This code was modified for use in Enano. The Text_Wiki engine is licensed
 * under the GNU Lesser General Public License; see
 * http://www.gnu.org/licenses/lgpl.html for details.
 *
 */

require_once ENANO_ROOT.'/includes/wikiengine/Parse.php';
require_once ENANO_ROOT.'/includes/wikiengine/Render.php';

class Text_Wiki {

  var $rules = array(
        'Prefilter',
        'Delimiter',
        'Code',
        'Function',
        'Html',
        'Raw',
        'Include',
        'Embed',
        'Anchor',
        'Heading',
        'Toc',
        'Horiz',
        'Break',
        'Blockquote',
        'List',
        'Deflist',
        'Table',
        'Image',
        'Phplookup',
        'Center',
        'Newline',
        'Paragraph',
        'Url',
        'Freelink',
        'Interwiki',
        'Wikilink',
        'Colortext',
        'Strong',
        'Bold',
        'Emphasis',
        'Italic',
        'Underline',
        'Tt',
        'Superscript',
        'Subscript',
        'Revise',
        'Tighten'
    );

    var $disable = array(
        'Html',
        'Include',
        'Embed',
        'Tighten',
        'Image'
    );

    var $parseConf = array();

    var $renderConf = array(
        'Docbook' => array(),
        'Latex' => array(),
        'Pdf' => array(),
        'Plain' => array(),
        'Rtf' => array(),
        'Xhtml' => array()
    );

    var $formatConf = array(
        'Docbook' => array(),
        'Latex' => array(),
        'Pdf' => array(),
        'Plain' => array(),
        'Rtf' => array(),
        'Xhtml' => array()
    );
    var $delim = "\xFF";
    var $tokens = array();
    var $_countRulesTokens = array();
    var $source = '';
    var $parseObj = array();
    var $renderObj = array();
    var $formatObj = array();
    var $path = array(
        'parse' => array(),
        'render' => array()
    );
    var $_dirSep = DIRECTORY_SEPARATOR;
    function Text_Wiki($rules = null)
    {
        if (is_array($rules)) {
            $this->rules = $rules;
        }

        $this->addPath(
            'parse',
            $this->fixPath(ENANO_ROOT) . 'includes/wikiengine/Parse/Default/'
        );
        $this->addPath(
            'render',
            $this->fixPath(ENANO_ROOT) . 'includes/wikiengine/Render/'
        );

    }

    function &singleton($parser = 'Default', $rules = null)
    {
        static $only = array();
        if (!isset($only[$parser])) {
            $ret =& Text_Wiki::factory($parser, $rules);
            if (!$ret) {
                return $ret;
            }
            $only[$parser] =& $ret;
        }
        return $only[$parser];
    }

    function &factory($parser = 'Default', $rules = null)
    {
        $d=getcwd();
        chdir(ENANO_ROOT);
        
        $class = 'Text_Wiki_' . $parser;
        $c2 = '._includes_wikiengine_' . $parser;
        $file = str_replace('_', '/', $c2).'.php';
        if (!class_exists($class)) {
            $fp = @fopen($file, 'r', true);
            if ($fp === false) {
                die_semicritical('Wiki formatting engine error', '<p>Could not find file '.$file.' in include_path</p>');
            }
            fclose($fp);
            include_once($file);
            if (!class_exists($class)) {
                die_semicritical('Wiki formatting engine error', '<p>Class '.$class.' does not exist after including '.$file.'</p>');
            }
        }
        
        chdir($d);

        $obj =& new $class($rules);
        return $obj;
    }

    function setParseConf($rule, $arg1, $arg2 = null)
    {
        $rule = ucwords(strtolower($rule));

        if (! isset($this->parseConf[$rule])) {
            $this->parseConf[$rule] = array();
        }

                                if (is_array($arg1)) {
            $this->parseConf[$rule] = $arg1;
        } else {
            $this->parseConf[$rule][$arg1] = $arg2;
        }
    }

    function getParseConf($rule, $key = null)
    {
        $rule = ucwords(strtolower($rule));

                if (! isset($this->parseConf[$rule])) {
            return null;
        }

                if (is_null($key)) {
            return $this->parseConf[$rule];
        }

                if (isset($this->parseConf[$rule][$key])) {
                        return $this->parseConf[$rule][$key];
        } else {
                        return null;
        }
    }

    function setRenderConf($format, $rule, $arg1, $arg2 = null)
    {
        $format = ucwords(strtolower($format));
        $rule = ucwords(strtolower($rule));

        if (! isset($this->renderConf[$format])) {
            $this->renderConf[$format] = array();
        }

        if (! isset($this->renderConf[$format][$rule])) {
            $this->renderConf[$format][$rule] = array();
        }

                                if (is_array($arg1)) {
            $this->renderConf[$format][$rule] = $arg1;
        } else {
            $this->renderConf[$format][$rule][$arg1] = $arg2;
        }
    }

    function getRenderConf($format, $rule, $key = null)
    {
        $format = ucwords(strtolower($format));
        $rule = ucwords(strtolower($rule));

        if (! isset($this->renderConf[$format]) ||
            ! isset($this->renderConf[$format][$rule])) {
          return null;
        }

        if (is_null($key)) {
          return $this->renderConf[$format][$rule];
        }

        if (isset($this->renderConf[$format][$rule][$key])) {
          return $this->renderConf[$format][$rule][$key];
        } else {
          return null;
        }

    }

    function setFormatConf($format, $arg1, $arg2 = null)
    {
      if (! is_array($this->formatConf[$format])) {
        $this->formatConf[$format] = array();
      }

      if (is_array($arg1)) {
        $this->formatConf[$format] = $arg1;
      } else {
        $this->formatConf[$format][$arg1] = $arg2;
      }
    }

    function getFormatConf($format, $key = null)
    {
      if (! isset($this->formatConf[$format])) {
        return null;
      }

      if (is_null($key)) {
        return $this->formatConf[$format];
      }

      if (isset($this->formatConf[$format][$key])) {
        return $this->formatConf[$format][$key];
      } else {
        return null;
      }
    }

    function insertRule($name, $tgt = null)
    {
      $name = ucwords(strtolower($name));
      if (! is_null($tgt)) {
        $tgt = ucwords(strtolower($tgt));
      }
      if (in_array($name, $this->rules)) {
        return null;
      }

      if (! is_null($tgt) && $tgt != '' &&
        ! in_array($tgt, $this->rules)) {
        return false;
      }

      if (is_null($tgt)) {
        $this->rules[] = $name;
        return true;
      }

      if ($tgt == '') {
        array_unshift($this->rules, $name);
        return true;
      }

      $tmp = $this->rules;
      $this->rules = array();

      foreach ($tmp as $val) {
        $this->rules[] = $val;
        if ($val == $tgt) {
          $this->rules[] = $name;
        }
      }

      return true;
    }

    function deleteRule($name)
    {
      $name = ucwords(strtolower($name));
      $key = array_search($name, $this->rules);
      if ($key !== false) {
        unset($this->rules[$key]);
      }
    }

    function changeRule($old, $new)
    {
      $old = ucwords(strtolower($old));
      $new = ucwords(strtolower($new));
      $key = array_search($old, $this->rules);
      if ($key !== false) {
        $this->deleteRule($new);
        $this->rules[$key] = $new;
      }
    }

    function enableRule($name)
    {
      $name = ucwords(strtolower($name));
      $key = array_search($name, $this->disable);
      if ($key !== false) {
        unset($this->disable[$key]);
      }
    }

    function disableRule($name)
    {
      $name = ucwords(strtolower($name));
      $key = array_search($name, $this->disable);
      if ($key === false) {
        $this->disable[] = $name;
      }
    }

    function transform($text, $format = 'Xhtml')
    {
      $this->parse($text);
      return $this->render($format);
    }

    function parse($text)
    {
      $this->source = $text;

      $this->tokens = array();
      $this->_countRulesTokens = array();

      foreach ($this->rules as $name) {
        if (! in_array($name, $this->disable)) {
          $this->loadParseObj($name);

          if (is_object($this->parseObj[$name])) {
            $this->parseObj[$name]->parse();
          }
          // For debugging
          // echo('<p>' . $name . ':</p><pre>'.htmlspecialchars($this->source).'</pre>');
        }
      }
    }

    function render($format = 'Xhtml')
    {
      $format = ucwords(strtolower($format));

      $output = '';

      $in_delim = false;

      $key = '';

      $result = $this->loadFormatObj($format);
      if ($this->isError($result)) {
        return $result;
      }

      if (is_object($this->formatObj[$format])) {
        $output .= $this->formatObj[$format]->pre();
      }

      foreach (array_keys($this->_countRulesTokens) as $rule) {
        $this->loadRenderObj($format, $rule);
      }

      $k = strlen($this->source);
      for ($i = 0; $i < $k; $i++) {

        $char = $this->source{$i};

        if ($in_delim) {

          if ($char == $this->delim) {

            $key = (int)$key;
            $rule = $this->tokens[$key][0];
            $opts = $this->tokens[$key][1];
            $output .= $this->renderObj[$rule]->token($opts);
            $in_delim = false;

          } else {

            $key .= $char;

          }

        } else {

          if ($char == $this->delim) {
            $key = '';
            $in_delim = true;
          } else {
            $output .= $char;
          }
        }
      }

      if (is_object($this->formatObj[$format])) {
        $output .= $this->formatObj[$format]->post();
      }

      return $output;
    }

    function getSource()
    {
      return $this->source;
    }

    function getTokens($rules = null)
    {
        if (is_null($rules)) {
            return $this->tokens;
        } else {
            settype($rules, 'array');
            $result = array();
            foreach ($this->tokens as $key => $val) {
                if (in_array($val[0], $rules)) {
                    $result[$key] = $val;
                }
            }
            return $result;
        }
    }

    function addToken($rule, $options = array(), $id_only = false)
    {
                                static $id;
        if (! isset($id)) {
            $id = 0;
        } else {
            $id ++;
        }

                settype($options, 'array');

                $this->tokens[$id] = array(
            0 => $rule,
            1 => $options
        );
        if (!isset($this->_countRulesTokens[$rule])) {
            $this->_countRulesTokens[$rule] = 1;
        } else {
            ++$this->_countRulesTokens[$rule];
        }

                if ($id_only) {
                        return $id;
        } else {
                        return $this->delim . $id . $this->delim;
        }
    }

    function setToken($id, $rule, $options = array())
    {
        $oldRule = $this->tokens[$id][0];
                $this->tokens[$id] = array(
            0 => $rule,
            1 => $options
        );
        if ($rule != $oldRule) {
            if (!($this->_countRulesTokens[$oldRule]--)) {
                unset($this->_countRulesTokens[$oldRule]);
            }
            if (!isset($this->_countRulesTokens[$rule])) {
                $this->_countRulesTokens[$rule] = 1;
            } else {
                ++$this->_countRulesTokens[$rule];
            }
        }
    }

    function loadParseObj($rule)
    {
        $rule = ucwords(strtolower($rule));
        $file = $rule . '.php';
        $class = "Text_Wiki_Parse_$rule";

        if (! class_exists($class)) {
            $loc = $this->findFile('parse', $file);
            if ($loc) {
                                include_once $loc;
            } else {
                                $this->parseObj[$rule] = null;
                                return $this->error(
                    "Parse rule '$rule' not found"
                );
            }
        }

        $this->parseObj[$rule] =& new $class($this);

    }

    function loadRenderObj($format, $rule)
    {
        $format = ucwords(strtolower($format));
        $rule = ucwords(strtolower($rule));
        $file = "$format/$rule.php";
        $class = "Text_Wiki_Render_$format" . "_$rule";

        if (! class_exists($class)) {
                        $loc = $this->findFile('render', $file);
            if ($loc) {
                                include_once $loc;
            } else {
                return $this->error(
                    "Render rule '$rule' in format '$format' not found"
                );
            }
        }

        $this->renderObj[$rule] =& new $class($this);
    }

    function loadFormatObj($format)
    {
        $format = ucwords(strtolower($format));
        $file = $format . '.php';
        $class = "Text_Wiki_Render_$format";

        if (! class_exists($class)) {
            $loc = $this->findFile('render', $file);
            if ($loc) {
                include_once $loc;
            } else {
                return $this->error(
                    "Rendering format class '$class' not found"
                );
            }
        }

        $this->formatObj[$format] =& new $class($this);
    }

    function addPath($type, $dir)
    {
        $dir = $this->fixPath($dir);
        if (! isset($this->path[$type])) {
            $this->path[$type] = array($dir);
        } else {
            array_unshift($this->path[$type], $dir);
        }
    }

    function getPath($type = null)
    {
        if (is_null($type)) {
            return $this->path;
        } elseif (! isset($this->path[$type])) {
            return array();
        } else {
            return $this->path[$type];
        }
    }

    function findFile($type, $file)
    {
      $set = $this->getPath($type);

      foreach ($set as $path) {
            $fullname = $path . $file;
            if (file_exists($fullname) && is_readable($fullname)) {
                return $fullname;
            }
        }

      return false;
    }

    function fixPath($path)
    {
        $len = strlen($this->_dirSep);

        if (! empty($path) &&
            substr($path, -1 * $len, $len) != $this->_dirSep)    {
            return $path . $this->_dirSep;
        } else {
            return $path;
        }
    }

    function &error($message)
    {
        die($message);
    }

    function isError(&$obj)
    {
        return is_a($obj, 'PEAR_Error');
    }
}

?>
