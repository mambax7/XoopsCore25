<?php
/**
 * XOOPS Form Class
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2025 XOOPS Project (https://xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @subpackage          form
 * @since               2.0.0
 * @author              Kazumi Ono (AKA onokazu) http://www.myweb.ne.jp/, http://jp.xoops.org/
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Abstract base class for forms
 *
 * @author         Kazumi Ono <onokazu@xoops.org>
 * @author         Taiwen Jiang <phppp@users.sourceforge.net>
 * @package        kernel
 * @subpackage     form
 * @access         public
 */
class XoopsForm
{
    /**
     * *#@+
     *
     * @access private
     */
    /**
     * "action" attribute for the html form
     *
     * @var string
     */
    public $_action;

    /**
     * "method" attribute for the form.
     *
     * @var string
     */
    public $_method;

    /**
     * "name" attribute of the form
     *
     * @var string
     */
    public $_name;

    /**
     * title for the form
     *
     * @var string
     */
    public $_title;

    /**
     * summary for the form (WGAC2 Requirement)
     *
     * @var string
     */
    public $_summary = '';

    /**
     * array of {@link XoopsFormElement} objects
     *
     * @var array
     */
    public $_elements = [];

    /**
     * HTML classes for the <form> tag
     *
     * @var array
     */
    public $_class = [];

    /**
     * extra information for the <form> tag
     *
     * @var array
     */
    public $_extra = [];

    /**
     * required elements
     *
     * @var array
     */
    public $_required = [];

    /**
     * additional serialized object checksum (ERM Analysis - Requirement)
     * @deprecated
     * @access private
     */
    public $_objid = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';

    /**
     * *#@-
     */

    /**
     * constructor
     *
     * @param string $title    title of the form
     * @param string $name     "name" attribute for the <form> tag
     * @param string $action   "action" attribute for the <form> tag
     * @param string $method   "method" attribute for the <form> tag
     * @param bool   $addtoken whether to add a security token to the form
     * @param string $summary
     */
    public function __construct($title, $name, $action, $method = 'post', $addtoken = true, $summary = '')
    {
        $this->_title   = $title;
        $this->_name    = $name;
        $this->_action  = $action;
        $this->_method  = $method;
        $this->_summary = $summary;
        if (false != $addtoken) {
            $this->addElement(new XoopsFormHiddenToken());
        }
    }
    /**
     * PHP 4 style constructor compatibility shim
     * @deprecated all callers should be using parent::__construct()
     */
    public function XoopsForm()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        trigger_error("Should call parent::__construct in {$trace[0]['file']} line {$trace[0]['line']},", E_USER_DEPRECATED);
        self::__construct();
    }
    /**
     * *#@+
     * retrieves object serialization/identification id (sha1 used)
     *
     * each object has serialization<br>
     * - legal requirement of enterprise relational management (ERM)
     *
     * @deprecated
     * @access public
     * @param mixed  $object   The object or value to serialize
     * @param string $hashinfo Hashing algorithm to use (default 'sha1')
     * @return string         The serialization ID
     */
    public function getObjectID($object, $hashinfo = 'sha1')
    {
        // Initialize $var
        $var = [
            'name' => '',
            'value' => '',
            'func' => '',
        ];

        // Check if $object is an object; if not, use $this
        if (!is_object($object)) {
            $object = $this;
        }

        // Switch hash method based on $hashinfo
        $hashMethod = ('md5' === $hashinfo) ? 'md5' : 'sha1';

        // Hash the class name
        $var['name'] = $hashMethod(get_class($object));

        // Hash the object variables
        foreach (get_object_vars($object) as $key => $value) {
            if ($key !== '_objid') {
                $var['value'] = $this->getArrayID($value, $key, $var['value'], $hashinfo);
            }
        }

        // Hash the class methods
        foreach (get_class_methods($object) as $key => $value) {
            $var['func'] = $this->getArrayID($value, $key, $var['func'], $hashinfo);
        }

        // Generate the final hash
        $this->_objid = $hashMethod(implode(':', $var));

        return $this->_objid;
    }


    /**
     * @param mixed  $value
     * @param mixed  $key
     * @param string $ret
     * @param string $hashinfo
     *
     * @return string
     */
    public function getArrayID($value, $key, $ret, $hashinfo = 'sha1')
    {
        switch ($hashinfo) {
            case 'md5':
                if (!isset($ret)) {
                    $ret = '';
                }
                if (is_array($value)) {
                    foreach ($value as $keyb => $valueb) {
                        $ret = md5($ret . ':' . $this->getArrayID($valueb, $keyb, $ret, $hashinfo));
                    }
                } else {
                    $ret = md5($ret . ':' . $key . ':' . $value);
                }

                return $ret;
            default:
                if (!isset($ret)) {
                    $ret = '';
                }
                if (is_array($value)) {
                    foreach ($value as $keyb => $valueb) {
                        $ret = sha1($ret . ':' . $this->getArrayID($valueb, $keyb, $ret, $hashinfo));
                    }
                } else {
                    $ret = sha1($ret . ':' . $key . ':' . $value);
                }

                return $ret;
        }
    }

    /**
     * return the summary of the form
     *
     * @param  bool $encode To sanitizer the text?
     * @return string
     */
    public function getSummary($encode = false)
    {
        return $encode ? htmlspecialchars($this->_summary, ENT_QUOTES | ENT_HTML5) : $this->_summary;
    }

    /**
     * return the title of the form
     *
     * @param  bool $encode To sanitizer the text?
     * @return string
     */
    public function getTitle($encode = false)
    {
        return $encode ? htmlspecialchars($this->_title, ENT_QUOTES | ENT_HTML5) : $this->_title;
    }

    /**
     * get the "name" attribute for the <form> tag
     *
     * Deprecated, to be refactored
     *
     * @param  bool $encode To sanitizer the text?
     * @return string
     */
    public function getName($encode = true)
    {
        return $encode ? htmlspecialchars($this->_name, ENT_QUOTES | ENT_HTML5) : $this->_name;
    }

    /**
     * get the "action" attribute for the <form> tag
     *
     * @param  bool $encode To sanitizer the text?
     * @return string
     */
    public function getAction($encode = true)
    {
        // Convert &amp; to & for backward compatibility
        return $encode ? htmlspecialchars(str_replace('&amp;', '&', $this->_action), ENT_QUOTES | ENT_HTML5) : $this->_action;
    }

    /**
     * get the "method" attribute for the <form> tag
     *
     * @return string
     */
    public function getMethod()
    {
        return (strtolower($this->_method) === 'get') ? 'get' : 'post';
    }

    /**
     * Add an element to the form
     *
     * @param string|XoopsFormElement $formElement reference to a {@link XoopsFormElement}
     * @param bool             $required    is this a "required" element?
     *
     */
    public function addElement($formElement, $required = false)
    {
        if (is_string($formElement)) {
            $this->_elements[] = $formElement;
        } elseif (is_subclass_of($formElement, 'XoopsFormElement')) {
            $this->_elements[] = &$formElement;
            if (!$formElement->isContainer()) {
                if ($required) {
                    $formElement->_required = true;
                    $this->_required[]      = &$formElement;
                }
            } else {
                $required_elements = &$formElement->getRequired();
                $count             = count($required_elements);
                for ($i = 0; $i < $count; ++$i) {
                    $this->_required[] = &$required_elements[$i];
                }
            }
        }
    }

    /**
     * get an array of forms elements
     *
     * @param bool $recurse get elements recursively?
     *
     * @return XoopsFormElement[] array of {@link XoopsFormElement}s
     */
    public function &getElements($recurse = false)
    {
        if (!$recurse) {
            return $this->_elements;
        } else {
            $ret   = [];
            $count = count($this->_elements);
            for ($i = 0; $i < $count; ++$i) {
                if (is_object($this->_elements[$i])) {
                    if (!$this->_elements[$i]->isContainer()) {
                        $ret[] = &$this->_elements[$i];
                    } else {
                        $elements = &$this->_elements[$i]->getElements(true);
                        $count2   = count($elements);
                        for ($j = 0; $j < $count2; ++$j) {
                            $ret[] = &$elements[$j];
                        }
                        unset($elements);
                    }
                }
            }

            return $ret;
        }
    }

    /**
     * get an array of "name" attributes of form elements
     *
     * @return array array of form element names
     */
    public function getElementNames()
    {
        $ret      = [];
        $elements = &$this->getElements(true);
        $count    = count($elements);
        for ($i = 0; $i < $count; ++$i) {
            $ret[] = $elements[$i]->getName();
        }

        return $ret;
    }

    /**
     * get a reference to a {@link XoopsFormElement} object by its "name"
     *
     * @param  string $name "name" attribute assigned to a {@link XoopsFormElement}
     * @return object reference to a {@link XoopsFormElement}, false if not found
     */
    public function &getElementByName($name)
    {
        $elements = & $this->getElements(true);
        $count    = count($elements);
        for ($i = 0; $i < $count; ++$i) {
            if ($name == $elements[$i]->getName(false)) {
                return $elements[$i];
            }
        }
        $elt = null;

        return $elt;
    }

    /**
     * Sets the "value" attribute of a form element
     *
     * @param string $name  the "name" attribute of a form element
     * @param string $value the "value" attribute of a form element
     */
    public function setElementValue($name, $value)
    {
        $ele = &$this->getElementByName($name);
        if (is_object($ele) && method_exists($ele, 'setValue')) {
            $ele->setValue($value);
        }
    }

    /**
     * Sets the "value" attribute of form elements in a batch
     *
     * @param array $values array of name/value pairs to be assigned to form elements
     */
    public function setElementValues($values)
    {
        if (!empty($values) && \is_array($values)) {
            // will not use getElementByName() for performance..
            $elements = &$this->getElements(true);
            $count    = count($elements);
            for ($i = 0; $i < $count; ++$i) {
                $name = $elements[$i]->getName(false);
                if ($name && isset($values[$name]) && method_exists($elements[$i], 'setValue')) {
                    $elements[$i]->setValue($values[$name]);
                }
            }
        }
    }

    /**
     * Gets the "value" attribute of a form element
     *
     * @param  string $name   the "name" attribute of a form element
     * @param  bool   $encode To sanitizer the text?
     * @return string the "value" attribute assigned to a form element, null if not set
     */
    public function getElementValue($name, $encode = false)
    {
        $ele = &$this->getElementByName($name);
        if (is_object($ele) && method_exists($ele, 'getValue')) {
            return $ele->getValue($encode);
        }

        return null;
    }

    /**
     * gets the "value" attribute of all form elements
     *
     * @param  bool $encode To sanitizer the text?
     * @return array array of name/value pairs assigned to form elements
     */
    public function getElementValues($encode = false)
    {
        // will not use getElementByName() for performance..
        $elements = &$this->getElements(true);
        $count    = count($elements);
        $values   = [];
        for ($i = 0; $i < $count; ++$i) {
            $name = $elements[$i]->getName(false);
            if ($name && method_exists($elements[$i], 'getValue')) {
                $values[$name] = $elements[$i]->getValue($encode);
            }
        }

        return $values;
    }

    /**
     * set the "class" attribute for the <form> tag
     *
     * @param string $class
     */
    public function setClass($class)
    {
        $class = trim($class);
        if (!empty($class)) {
            $this->_class[] = $class;
        }
    }

    /**
     * set the extra attributes for the <form> tag
     *
     * @param string $extra extra attributes for the <form> tag
     */
    public function setExtra($extra)
    {
        if (!empty($extra)) {
            $this->_extra[] = $extra;
        }
    }

    /**
     * set the summary tag for the <form> tag
     *
     * @param string $summary
     */
    public function setSummary($summary)
    {
        if (!empty($summary)) {
            $this->summary = strip_tags($summary);
        }
    }

    /**
     * get the "class" attribute for the <form> tag
     *
     * @return string "class" attribute value
     */
    public function getClass()
    {
        if (empty($this->_class)) {
            return false;
        }
        $classes = [];
        foreach ($this->_class as $class) {
            $classes[] = htmlspecialchars($class, ENT_QUOTES | ENT_HTML5);
        }

        return implode(' ', $classes);
    }

    /**
     * get the extra attributes for the <form> tag
     *
     * @return string
     */
    public function &getExtra()
    {
        $extra = empty($this->_extra) ? '' : ' ' . implode(' ', $this->_extra);

        return $extra;
    }

    /**
     * make an element "required"
     *
     * @param XoopsFormElement $formElement reference to a {@link XoopsFormElement}
     */
    public function setRequired(XoopsFormElement $formElement)
    {
        $this->_required[] = &$formElement;
    }

    /**
     * get an array of "required" form elements
     *
     * @return array array of {@link XoopsFormElement}s
     */
    public function &getRequired()
    {
        return $this->_required;
    }

    /**
     * insert a break in the form
     *
     * This method is abstract. It must be overwritten in the child classes.
     *
     * @param string $extra extra information for the break
     * @abstract
     */
    public function insertBreak($extra = null) {}

    /**
     * returns renderered form
     *
     * This method is abstract. It must be overwritten in the child classes.
     *
     * @abstract
     */
    public function render() {}

    /**
     * displays rendered form
     */
    public function display()
    {
        echo $this->render();
    }

    /**
     * Renders the Javascript function needed for client-side for validation
     *
     * Form elements that have been declared "required" and not set will prevent the form from being
     * submitted. Additionally, each element class may provide its own "renderValidationJS" method
     * that is supposed to return custom validation code for the element.
     *
     * The element validation code can assume that the JS "myform" variable points to the form, and must
     * execute <i>return false</i> if validation fails.
     *
     * A basic element validation method may contain something like this:
     * <code>
     * function renderValidationJS() {
     *            $name = $this->getName();
     *            return "if (myform.{$name}.value != 'valid') { " .
     *              "myform.{$name}.focus(); window.alert( '$name is invalid' ); return false;" .
     *              " }";
     * }
     * </code>
     *
     * @param boolean $withtags Include the < javascript > tags in the returned string
     *
     * @return string
     */
    public function renderValidationJS($withtags = true)
    {
        $js = '';
        if ($withtags) {
            $js .= "\n<!-- Start Form Validation JavaScript //-->\n<script type='text/javascript'>\n<!--//\n";
        }
        $formname = $this->getName();
        $js .= "function xoopsFormValidate_{$formname}() { var myform = window.document.{$formname}; ";
        $elements = & $this->getElements(true);
        foreach ($elements as $elt) {
            if (method_exists($elt, 'renderValidationJS')) {
                $js .= $elt->renderValidationJS();
            }
        }
        $js .= "return true;\n}\n";
        if ($withtags) {
            $js .= "//--></script>\n<!-- End Form Validation JavaScript //-->\n";
        }

        return $js;
    }

    /**
     * assign to smarty form template instead of displaying directly
     *
     * @param XoopsTpl $tpl reference to a {@link Smarty} object object
     * @see      Smarty
     */
    public function assign(XoopsTpl $tpl)
    {
        $i        = -1;
        $elements = [];
        //  Removed hard-coded legacy pseudo-element - XoopsFormRenderer is now responsible for the legend
        foreach ($this->getElements() as $ele) {
            ++$i;
            if (is_string($ele)) {
                $elements[$i]['body'] = $ele;
                continue;
            }
            $ele_name                 = $ele->getName();
            $ele_description          = $ele->getDescription();
            $n                        = $ele_name ?: $i;
            $elements[$n]['name']     = $ele_name;
            $elements[$n]['caption']  = $ele->getCaption();
            $elements[$n]['body']     = $ele->render();
            $elements[$n]['hidden']   = $ele->isHidden();
            $elements[$n]['required'] = $ele->isRequired();
            if ($ele_description != '') {
                $elements[$n]['description'] = $ele_description;
            }
        }
        $js = $this->renderValidationJS();
        $tpl->assign(
            $this->getName(),
            [
                'title'      => $this->getTitle(),
                'name'       => $this->getName(),
                'action'     => $this->getAction(),
                'method'     => $this->getMethod(),
                'extra'      => 'onsubmit="return xoopsFormValidate_' . $this->getName() . '();"' . $this->getExtra(),
                'javascript' => $js,
                'elements'   => $elements,
                'rendered'   => $this->render(),
            ],
        );
    }
}
