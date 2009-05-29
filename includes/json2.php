<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file licenses/bsdlic.html.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Json
 * @copyright  Copyright (c) 2005-2007 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */


/**
 * Encode PHP constructs to JSON
 *
 * @category   Zend
 * @package    Zend_Json
 * @copyright  Copyright (c) 2005-2007 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Json_Encoder
{
    /**
     * Whether or not to check for possible cycling
     *
     * @var boolean
     */
    protected $_cycleCheck;

    /**
     * Array of visited objects; used to prevent cycling.
     *
     * @var array
     */
    protected $_visited = array();

    /**
     * Constructor
     *
     * @param boolean $cycleCheck Whether or not to check for recursion when encoding
     * @return void
     */
    protected function __construct($cycleCheck = false)
    {
        $this->_cycleCheck = $cycleCheck;
    }

    /**
     * Use the JSON encoding scheme for the value specified
     *
     * @param mixed $value The value to be encoded
     * @param boolean $cycleCheck Whether or not to check for possible object recursion when encoding
     * @return string  The encoded value
     */
    public static function encode($value, $cycleCheck = false)
    {
        $encoder = new Zend_Json_Encoder(($cycleCheck) ? true : false);

        return $encoder->_encodeValue($value);
    }

    /**
     * Recursive driver which determines the type of value to be encoded
     * and then dispatches to the appropriate method. $values are either
     *    - objects (returns from {@link _encodeObject()})
     *    - arrays (returns from {@link _encodeArray()})
     *    - basic datums (e.g. numbers or strings) (returns from {@link _encodeDatum()})
     *
     * @param $value mixed The value to be encoded
     * @return string Encoded value
     */
    protected function _encodeValue(&$value)
    {
        if (is_object($value)) {
            return $this->_encodeObject($value);
        } else if (is_array($value)) {
            return $this->_encodeArray($value);
        }

        return $this->_encodeDatum($value);
    }



    /**
     * Encode an object to JSON by encoding each of the public properties
     *
     * A special property is added to the JSON object called '__className'
     * that contains the name of the class of $value. This is used to decode
     * the object on the client into a specific class.
     *
     * @param $value object
     * @return string
     * @throws Zend_Json_Exception If recursive checks are enabled and the object has been serialized previously
     */
    protected function _encodeObject(&$value)
    {
        if ($this->_cycleCheck) {
            if ($this->_wasVisited($value)) {
                throw new Zend_Json_Exception(
                    'Cycles not supported in JSON encoding, cycle introduced by '
                    . 'class "' . get_class($value) . '"'
                );
            }

            $this->_visited[] = $value;
        }

        $props = '';
        foreach (get_object_vars($value) as $name => $propValue) {
            if (isset($propValue)) {
                $props .= ','
                        . $this->_encodeValue($name)
                        . ':'
                        . $this->_encodeValue($propValue);
            }
        }

        return '{"__className":"' . get_class($value) . '"'
                . $props . '}';
    }


    /**
     * Determine if an object has been serialized already
     *
     * @param mixed $value
     * @return boolean
     */
    protected function _wasVisited(&$value)
    {
        if (in_array($value, $this->_visited, true)) {
            return true;
        }

        return false;
    }


    /**
     * JSON encode an array value
     *
     * Recursively encodes each value of an array and returns a JSON encoded
     * array string.
     *
     * Arrays are defined as integer-indexed arrays starting at index 0, where
     * the last index is (count($array) -1); any deviation from that is
     * considered an associative array, and will be encoded as such.
     *
     * @param $array array
     * @return string
     */
    protected function _encodeArray(&$array)
    {
        $tmpArray = array();

        // Check for associative array
        if (!empty($array) && (array_keys($array) !== range(0, count($array) - 1))) {
            // Associative array
            $result = '{';
            foreach ($array as $key => $value) {
                $key = (string) $key;
                $tmpArray[] = $this->_encodeString($key)
                            . ':'
                            . $this->_encodeValue($value);
            }
            $result .= implode(',', $tmpArray);
            $result .= '}';
        } else {
            // Indexed array
            $result = '[';
            $length = count($array);
            for ($i = 0; $i < $length; $i++) {
                $tmpArray[] = $this->_encodeValue($array[$i]);
            }
            $result .= implode(',', $tmpArray);
            $result .= ']';
        }

        return $result;
    }


    /**
     * JSON encode a basic data type (string, number, boolean, null)
     *
     * If value type is not a string, number, boolean, or null, the string
     * 'null' is returned.
     *
     * @param $value mixed
     * @return string
     */
    protected function _encodeDatum(&$value)
    {
        $result = 'null';

        if (is_int($value) || is_float($value)) {
            $result = (string)$value;
        } elseif (is_string($value)) {
            $result = $this->_encodeString($value);
        } elseif (is_bool($value)) {
            $result = $value ? 'true' : 'false';
        }

        return $result;
    }


    /**
     * JSON encode a string value by escaping characters as necessary
     *
     * @param $value string
     * @return string
     */
    protected function _encodeString(&$string)
    {
        // Escape these characters with a backslash:
        // " \ / \n \r \t \b \f
        $search  = array('\\', "\n", "\t", "\r", "\b", "\f", '"');
        $replace = array('\\\\', '\\n', '\\t', '\\r', '\\b', '\\f', '\"');
        $string  = str_replace($search, $replace, $string);

        // Escape certain ASCII characters:
        // 0x08 => \b
        // 0x0c => \f
        $string = str_replace(array(chr(0x08), chr(0x0C)), array('\b', '\f'), $string);

        return '"' . $string . '"';
    }


    /**
     * Encode the constants associated with the ReflectionClass
     * parameter. The encoding format is based on the class2 format
     *
     * @param $cls ReflectionClass
     * @return string Encoded constant block in class2 format
     */
    private static function _encodeConstants(ReflectionClass $cls)
    {
        $result    = "constants : {";
        $constants = $cls->getConstants();

        $tmpArray = array();
        if (!empty($constants)) {
            foreach ($constants as $key => $value) {
                $tmpArray[] = "$key: " . self::encode($value);
            }

            $result .= implode(', ', $tmpArray);
        }

        return $result . "}";
    }


    /**
     * Encode the public methods of the ReflectionClass in the
     * class2 format
     *
     * @param $cls ReflectionClass
     * @return string Encoded method fragment
     *
     */
    private static function _encodeMethods(ReflectionClass $cls)
    {
        $methods = $cls->getMethods();
        $result = 'methods:{';

        $started = false;
        foreach ($methods as $method) {
            if (! $method->isPublic() || !$method->isUserDefined()) {
                continue;
            }

            if ($started) {
                $result .= ',';
            }
            $started = true;

            $result .= '' . $method->getName(). ':function(';

            if ('__construct' != $method->getName()) {
                $parameters  = $method->getParameters();
                $paramCount  = count($parameters);
                $argsStarted = false;

                $argNames = "var argNames=[";
                foreach ($parameters as $param) {
                    if ($argsStarted) {
                        $result .= ',';
                    }

                    $result .= $param->getName();

                    if ($argsStarted) {
                        $argNames .= ',';
                    }

                    $argNames .= '"' . $param->getName() . '"';

                    $argsStarted = true;
                }
                $argNames .= "];";

                $result .= "){"
                         . $argNames
                         . 'var result = ZAjaxEngine.invokeRemoteMethod('
                         . "this, '" . $method->getName()
                         . "',argNames,arguments);"
                         . 'return(result);}';
            } else {
                $result .= "){}";
            }
        }

        return $result . "}";
    }


    /**
     * Encode the public properties of the ReflectionClass in the class2
     * format.
     *
     * @param $cls ReflectionClass
     * @return string Encode properties list
     *
     */
    private static function _encodeVariables(ReflectionClass $cls)
    {
        $properties = $cls->getProperties();
        $propValues = get_class_vars($cls->getName());
        $result = "variables:{";
        $cnt = 0;

        $tmpArray = array();
        foreach ($properties as $prop) {
            if (! $prop->isPublic()) {
                continue;
            }

            $tmpArray[] = $prop->getName()
                        . ':'
                        . self::encode($propValues[$prop->getName()]);
        }
        $result .= implode(',', $tmpArray);

        return $result . "}";
    }

    /**
     * Encodes the given $className into the class2 model of encoding PHP
     * classes into JavaScript class2 classes.
     * NOTE: Currently only public methods and variables are proxied onto
     * the client machine
     *
     * @param $className string The name of the class, the class must be
     * instantiable using a null constructor
     * @param $package string Optional package name appended to JavaScript
     * proxy class name
     * @return string The class2 (JavaScript) encoding of the class
     * @throws Zend_Json_Exception
     */
    public static function encodeClass($className, $package = '')
    {
        $cls = new ReflectionClass($className);
        if (! $cls->isInstantiable()) {
            throw new Zend_Json_Exception("$className must be instantiable");
        }

        return "Class.create('$package$className',{"
                . self::_encodeConstants($cls)    .","
                . self::_encodeMethods($cls)      .","
                . self::_encodeVariables($cls)    .'});';
    }


    /**
     * Encode several classes at once
     *
     * Returns JSON encoded classes, using {@link encodeClass()}.
     *
     * @param array $classNames
     * @param string $package
     * @return string
     */
    public static function encodeClasses(array $classNames, $package = '')
    {
        $result = '';
        foreach ($classNames as $className) {
            $result .= self::encodeClass($className, $package);
        }

        return $result;
    }

}

/**
 * Decode JSON encoded string to PHP variable constructs
 *
 * @category   Zend
 * @package    Zend_Json
 * @copyright  Copyright (c) 2005-2007 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Json_Decoder
{
    /**
     * Parse tokens used to decode the JSON object. These are not
     * for public consumption, they are just used internally to the
     * class.
     */
    const EOF          = 0;
    const DATUM        = 1;
    const LBRACE       = 2;
    const LBRACKET     = 3;
    const RBRACE       = 4;
    const RBRACKET     = 5;
    const COMMA        = 6;
    const COLON        = 7;

    /**
     * Use to maintain a "pointer" to the source being decoded
     *
     * @var string
     */
    protected $_source;

    /**
     * Caches the source length
     *
     * @var int
     */
    protected $_sourceLength;

    /**
     * The offset within the souce being decoded
     *
     * @var int
     *
     */
    protected $_offset;

    /**
     * The current token being considered in the parser cycle
     *
     * @var int
     */
    protected $_token;

    /**
     * Flag indicating how objects should be decoded
     *
     * @var int
     * @access protected
     */
    protected $_decodeType;

    /**
     * Constructor
     *
     * @param string $source String source to decode
     * @param int $decodeType How objects should be decoded -- see
     * {@link Zend_Json::TYPE_ARRAY} and {@link Zend_Json::TYPE_OBJECT} for
     * valid values
     * @return void
     */
    protected function __construct($source, $decodeType)
    {
        
        // eliminate comments
        $source = preg_replace(array(

                  // eliminate single line comments in '// ...' form
                  '#^\s*//(.+)$#m',
    
                  // eliminate multi-line comments in '/* ... */' form, at start of string
                  '#^\s*/\*(.+)\*/#Us',
    
                  // eliminate multi-line comments in '/* ... */' form, at end of string
                  '#/\*(.+)\*/\s*$#Us'
    
              ), '', $source);
        
        // Set defaults
        $this->_source       = $source;
        $this->_sourceLength = strlen($source);
        $this->_token        = self::EOF;
        $this->_offset       = 0;

        // Normalize and set $decodeType
        if (!in_array($decodeType, array(Zend_Json::TYPE_ARRAY, Zend_Json::TYPE_OBJECT)))
        {
            $decodeType = Zend_Json::TYPE_ARRAY;
        }
        $this->_decodeType   = $decodeType;

        // Set pointer at first token
        $this->_getNextToken();
    }

    /**
     * Decode a JSON source string
     *
     * Decodes a JSON encoded string. The value returned will be one of the
     * following:
     *        - integer
     *        - float
     *        - boolean
     *        - null
     *      - StdClass
     *      - array
     *         - array of one or more of the above types
     *
     * By default, decoded objects will be returned as associative arrays; to
     * return a StdClass object instead, pass {@link Zend_Json::TYPE_OBJECT} to
     * the $objectDecodeType parameter.
     *
     * Throws a Zend_Json_Exception if the source string is null.
     *
     * @static
     * @access public
     * @param string $source String to be decoded
     * @param int $objectDecodeType How objects should be decoded; should be
     * either or {@link Zend_Json::TYPE_ARRAY} or
     * {@link Zend_Json::TYPE_OBJECT}; defaults to TYPE_ARRAY
     * @return mixed
     * @throws Zend_Json_Exception
     */
    public static function decode($source = null, $objectDecodeType = Zend_Json::TYPE_ARRAY)
    {
        if (null === $source) {
            throw new Zend_Json_Exception('Must specify JSON encoded source for decoding');
        } elseif (!is_string($source)) {
            throw new Zend_Json_Exception('Can only decode JSON encoded strings');
        }

        $decoder = new self($source, $objectDecodeType);

        return $decoder->_decodeValue();
    }


    /**
     * Recursive driving rountine for supported toplevel tops
     *
     * @return mixed
     */
    protected function _decodeValue()
    {
        switch ($this->_token) {
            case self::DATUM:
                $result  = $this->_tokenValue;
                $this->_getNextToken();
                return($result);
                break;
            case self::LBRACE:
                return($this->_decodeObject());
                break;
            case self::LBRACKET:
                return($this->_decodeArray());
                break;
            default:
                return null;
                break;
        }
    }

    /**
     * Decodes an object of the form:
     *  { "attribute: value, "attribute2" : value,...}
     *
     * If ZJsonEnoder or ZJAjax was used to encode the original object
     * then a special attribute called __className which specifies a class
     * name that should wrap the data contained within the encoded source.
     *
     * Decodes to either an array or StdClass object, based on the value of
     * {@link $_decodeType}. If invalid $_decodeType present, returns as an
     * array.
     *
     * @return array|StdClass
     */
    protected function _decodeObject()
    {
        $members = array();
        $tok = $this->_getNextToken();

        while ($tok && $tok != self::RBRACE) {
            if ($tok != self::DATUM || ! is_string($this->_tokenValue)) {
                throw new Zend_Json_Exception('Missing key in object encoding: ' . $this->_source);
            }

            $key = $this->_tokenValue;
            $tok = $this->_getNextToken();

            if ($tok != self::COLON) {
                throw new Zend_Json_Exception('Missing ":" in object encoding: ' . $this->_source);
            }

            $tok = $this->_getNextToken();
            $members[$key] = $this->_decodeValue();
            $tok = $this->_token;

            if ($tok == self::RBRACE) {
                break;
            }

            if ($tok != self::COMMA) {
                throw new Zend_Json_Exception('Missing "," in object encoding: ' . $this->_source);
            }

            $tok = $this->_getNextToken();
        }

        switch ($this->_decodeType) {
            case Zend_Json::TYPE_OBJECT:
                // Create new StdClass and populate with $members
                $result = new StdClass();
                foreach ($members as $key => $value) {
                    $result->$key = $value;
                }
                break;
            case Zend_Json::TYPE_ARRAY:
            default:
                $result = $members;
                break;
        }

        $this->_getNextToken();
        return $result;
    }

    /**
     * Decodes a JSON array format:
     *    [element, element2,...,elementN]
     *
     * @return array
     */
    protected function _decodeArray()
    {
        $result = array();
        $starttok = $tok = $this->_getNextToken(); // Move past the '['
        $index  = 0;

        while ($tok && $tok != self::RBRACKET) {
            $result[$index++] = $this->_decodeValue();

            $tok = $this->_token;

            if ($tok == self::RBRACKET || !$tok) {
                break;
            }

            if ($tok != self::COMMA) {
                throw new Zend_Json_Exception('Missing "," in array encoding: ' . $this->_source);
            }

            $tok = $this->_getNextToken();
        }

        $this->_getNextToken();
        return($result);
    }


    /**
     * Removes whitepsace characters from the source input
     */
    protected function _eatWhitespace()
    {
        if (preg_match(
                '/([\t\b\f\n\r ])*/s',
                $this->_source,
                $matches,
                PREG_OFFSET_CAPTURE,
                $this->_offset)
            && $matches[0][1] == $this->_offset)
        {
            $this->_offset += strlen($matches[0][0]);
        }
    }


    /**
     * Retrieves the next token from the source stream
     *
     * @return int Token constant value specified in class definition
     */
    protected function _getNextToken()
    {
        $this->_token      = self::EOF;
        $this->_tokenValue = null;
        $this->_eatWhitespace();
        
        if ($this->_offset >= $this->_sourceLength) {
            return(self::EOF);
        }

        $str        = $this->_source;
        $str_length = $this->_sourceLength;
        $i          = $this->_offset;
        $start      = $i;
        
        switch ($str{$i}) {
            case '{':
               $this->_token = self::LBRACE;
               break;
            case '}':
                $this->_token = self::RBRACE;
                break;
            case '[':
                $this->_token = self::LBRACKET;
                break;
            case ']':
                $this->_token = self::RBRACKET;
                break;
            case ',':
                $this->_token = self::COMMA;
                break;
            case ':':
                $this->_token = self::COLON;
                break;
            case  '"':
                $result = '';
                do {
                    $i++;
                    if ($i >= $str_length) {
                        break;
                    }

                    $chr = $str{$i};
                    if ($chr == '\\') {
                        $i++;
                        if ($i >= $str_length) {
                            break;
                        }
                        $chr = $str{$i};
                        switch ($chr) {
                            case '"' :
                                $result .= '"';
                                break;
                            case '\\':
                                $result .= '\\';
                                break;
                            case '/' :
                                $result .= '/';
                                break;
                            case 'b' :
                                $result .= chr(8);
                                break;
                            case 'f' :
                                $result .= chr(12);
                                break;
                            case 'n' :
                                $result .= chr(10);
                                break;
                            case 'r' :
                                $result .= chr(13);
                                break;
                            case 't' :
                                $result .= chr(9);
                                break;
                            case '\'' :
                                $result .= '\'';
                                break;
                            case 'u':
                              $result .= self::decode_unicode_byte(substr($str, $i + 1, 4));
                              $i += 4;
                              break;
                            default:
                                throw new Zend_Json_Exception("Illegal escape "
                                    .  "sequence '" . $chr . "'");
                            }
                    } elseif ($chr == '"') {
                        break;
                    } else {
                        $result .= $chr;
                    }
                } while ($i < $str_length);

                $this->_token = self::DATUM;
                //$this->_tokenValue = substr($str, $start + 1, $i - $start - 1);
                $this->_tokenValue = $result;
                break;
            case  "'":
                $result = '';
                do {
                    $i++;
                    if ($i >= $str_length) {
                        break;
                    }

                    $chr = $str{$i};
                    if ($chr == '\\') {
                        $i++;
                        if ($i >= $str_length) {
                            break;
                        }
                        $chr = $str{$i};
                        switch ($chr) {
                            case "'" :
                                $result .= "'";
                                break;
                            case '\\':
                                $result .= '\\';
                                break;
                            case '/' :
                                $result .= '/';
                                break;
                            case 'b' :
                                $result .= chr(8);
                                break;
                            case 'f' :
                                $result .= chr(12);
                                break;
                            case 'n' :
                                $result .= chr(10);
                                break;
                            case 'r' :
                                $result .= chr(13);
                                break;
                            case 't' :
                                $result .= chr(9);
                                break;
                            case '"' :
                                $result .= '"';
                                break;
                            default:
                                throw new Zend_Json_Exception("Illegal escape "
                                    .  "sequence '" . $chr . "'");
                            }
                    } elseif ($chr == "'") {
                        break;
                    } else {
                        $result .= $chr;
                    }
                } while ($i < $str_length);

                $this->_token = self::DATUM;
                //$this->_tokenValue = substr($str, $start + 1, $i - $start - 1);
                $this->_tokenValue = $result;
                break;
            case 't':
                if (($i+ 3) < $str_length && substr($str, $start, 4) == "true") {
                    $this->_token = self::DATUM;
                }
                $this->_tokenValue = true;
                $i += 3;
                break;
            case 'f':
                if (($i+ 4) < $str_length && substr($str, $start, 5) == "false") {
                    $this->_token = self::DATUM;
                }
                $this->_tokenValue = false;
                $i += 4;
                break;
            case 'n':
                if (($i+ 3) < $str_length && substr($str, $start, 4) == "null") {
                    $this->_token = self::DATUM;
                }
                $this->_tokenValue = NULL;
                $i += 3;
                break;
              case ' ':
                break;
        }

        if ($this->_token != self::EOF) {
            $this->_offset = $i + 1; // Consume the last token character
            return($this->_token);
        }

        $chr = $str{$i};
        if ($chr == '-' || $chr == '.' || ($chr >= '0' && $chr <= '9')) {
            if (preg_match('/-?([0-9])*(\.[0-9]*)?((e|E)((-|\+)?)[0-9]+)?/s',
                $str, $matches, PREG_OFFSET_CAPTURE, $start) && $matches[0][1] == $start) {

                $datum = $matches[0][0];

                if (is_numeric($datum)) {
                    if (preg_match('/^0\d+$/', $datum)) {
                        throw new Zend_Json_Exception("Octal notation not supported by JSON (value: $datum)");
                    } else {
                        $val  = intval($datum);
                        $fVal = floatval($datum);
                        $this->_tokenValue = ($val == $fVal ? $val : $fVal);
                    }
                } else {
                    throw new Zend_Json_Exception("Illegal number format: $datum");
                }

                $this->_token = self::DATUM;
                $this->_offset = $start + strlen($datum);
            }
        } else {
            throw new Zend_Json_Exception("Illegal Token at pos $i: $chr\nContext:\n--------------------------------------------------" . substr($str, $i) . "\n--------------------------------------------------");
        }

        return($this->_token);
    }
    
    /**
     * Handle a Unicode byte; local to Enano.
     * @param string 4 character byte sequence
     * @return string
     */
    
    protected function decode_unicode_byte($byte)
    {
      if ( strlen($byte) != 4 )
        throw new Zend_Json_Exception("Invalid Unicode sequence \\u$byte");
        
      $value = hexdec($byte);

      if ($value < 0x0080)
      {
        // 1 byte: 0xxxxxxx
        $character = chr($value);
      }
      else if ($value < 0x0800)
      {
        // 2 bytes: 110xxxxx 10xxxxxx
        $character =
            chr((($value & 0x07c0) >> 6) | 0xc0)
          . chr(($value & 0x3f) | 0x80);
      }
      else
      {
        // 3 bytes: 1110xxxx 10xxxxxx 10xxxxxx
        $character =
            chr((($value & 0xf000) >> 12) | 0xe0)
          . chr((($value & 0x0fc0) >> 6) | 0x80)
          . chr(($value & 0x3f) | 0x80);
      }
      
      return $character;
    }
}

/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file licenses/bsdlic.html.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Json
 * @copyright  Copyright (c) 2005-2007 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */


/**
 * @category   Zend
 * @package    Zend_Json
 * @copyright  Copyright (c) 2005-2007 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Json_Exception extends Zend_Exception
{}

/**
 * @category   Zend
 * @package    Zend
 * @copyright  Copyright (c) 2005-2007 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Exception extends Exception
{}

/**
 * Class for encoding to and decoding from JSON.
 *
 * @category   Zend
 * @package    Zend_Json
 * @copyright  Copyright (c) 2005-2007 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Json
{
    /**
     * How objects should be encoded -- arrays or as StdClass. TYPE_ARRAY is 1
     * so that it is a boolean true value, allowing it to be used with
     * ext/json's functions.
     */
    const TYPE_ARRAY  = 1;
    const TYPE_OBJECT = 0;

    /**
     * @var bool
     */
    public static $useBuiltinEncoderDecoder = true;

    /**
     * Decodes the given $encodedValue string which is
     * encoded in the JSON format
     *
     * Uses ext/json's json_decode if available.
     *
     * @param string $encodedValue Encoded in JSON format
     * @param int $objectDecodeType Optional; flag indicating how to decode
     * objects. See {@link ZJsonDecoder::decode()} for details.
     * @return mixed
     */
    public static function decode($encodedValue, $objectDecodeType = Zend_Json::TYPE_ARRAY)
    {
        if (function_exists('json_decode') && self::$useBuiltinEncoderDecoder !== true) {
            return json_decode($encodedValue, $objectDecodeType);
        }

        return Zend_Json_Decoder::decode($encodedValue, $objectDecodeType);
    }


    /**
     * Encode the mixed $valueToEncode into the JSON format
     *
     * Encodes using ext/json's json_encode() if available.
     *
     * NOTE: Object should not contain cycles; the JSON format
     * does not allow object reference.
     *
     * NOTE: Only public variables will be encoded
     *
     * @param mixed $valueToEncode
     * @param boolean $cycleCheck Optional; whether or not to check for object recursion; off by default
     * @return string JSON encoded object
     */
    public static function encode($valueToEncode, $cycleCheck = false)
    {
        if (function_exists('json_encode') && self::$useBuiltinEncoderDecoder !== true) {
            return json_encode($valueToEncode);
        }

        return Zend_Json_Encoder::encode($valueToEncode, $cycleCheck);
    }
}

?>
