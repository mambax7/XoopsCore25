<?php
/**
 * XOOPS tree class
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
 * @since               2.0.0
 * @author              Kazumi Ono (http://www.myweb.ne.jp/, http://jp.xoops.org/)
 */

defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * A tree structures with {@link XoopsObject}s as nodes
 *
 * @package    kernel
 * @subpackage core
 * @author     Kazumi Ono <onokazu@xoops.org>
 */
class XoopsObjectTree
{
    /**
     * @access private
     */
    protected $parentId;
    protected $myId;
    protected $rootId;
    protected $tree = [];
    protected $objects;

    /**
     * Constructor
     *
     * @param array  $objectArr Array of {@link XoopsObject}s
     * @param string $myId      field name of object ID
     * @param string $parentId  field name of parent object ID
     * @param string|null $rootId    field name of root object ID
     */
    public function __construct($objectArr, $myId, $parentId, ?string $rootId = null)
    {
        $this->objects = $objectArr;
        $this->myId     = $myId;
        $this->parentId = $parentId;
        if (isset($rootId)) {
            $this->rootId = $rootId;
        }
        $this->initialize();
    }

    /**
     * Initialize the object
     *
     * @access private
     */
    protected function initialize()
    {
        foreach (array_keys($this->objects) as $i) {
            $key1                          = $this->objects[$i]->getVar($this->myId);
            $this->tree[$key1]['obj']     = $this->objects[$i];
            $key2                          = $this->objects[$i]->getVar($this->parentId);
            $this->tree[$key1]['parent']  = $key2;
            $this->tree[$key2]['child'][] = $key1;
            if (isset($this->rootId)) {
                $this->tree[$key1]['root'] = $this->objects[$i]->getVar($this->rootId);
            }
        }
    }

    /**
     * Get the tree
     *
     * @return array Associative array comprising the tree
     */
    public function &getTree()
    {
        return $this->tree;
    }

    /**
     * returns an object from the tree specified by its id
     *
     * @param  string $key ID of the object to retrieve
     * @return object Object within the tree
     */
    public function &getByKey($key)
    {
        return $this->tree[$key]['obj'];
    }

    /**
     * returns an array of all the first child object of an object specified by its id
     *
     * @param  string $key ID of the parent object
     * @return array  Array of children of the parent
     */
    public function getFirstChild($key)
    {
        $ret = [];
        if (isset($this->tree[$key]['child'])) {
            foreach ($this->tree[$key]['child'] as $childKey) {
                $ret[$childKey] = $this->tree[$childKey]['obj'];
            }
        }

        return $ret;
    }

    /**
     * returns an array of all child objects of an object specified by its id
     *
     * @param  string $key ID of the parent
     * @param  array  $ret (Empty when called from client) Array of children from previous recursions.
     * @return array  Array of child nodes.
     */
    public function getAllChild($key, $ret = [])
    {
        if (isset($this->tree[$key]['child'])) {
            foreach ($this->tree[$key]['child'] as $childKey) {
                $ret[$childKey] = $this->tree[$childKey]['obj'];
                $children       = $this->getAllChild($childKey, $ret);
                foreach (array_keys($children) as $newKey) {
                    $ret[$newKey] = $children[$newKey];
                }
            }
        }

        return $ret;
    }

    /**
     * returns an array of all parent objects.
     * the key of returned array represents how many levels up from the specified object
     *
     * @param  string $key     ID of the child object
     * @param  array  $ret     (empty when called from outside) Result from previous recursions
     * @param  int    $upLevel (empty when called from outside) level of recursion
     * @return array  Array of parent nodes.
     */
    public function getAllParent($key, $ret = [], $upLevel = 1)
    {
        if (isset($this->tree[$key]['parent']) && isset($this->tree[$this->tree[$key]['parent']]['obj'])) {
            $ret[$upLevel] = $this->tree[$this->tree[$key]['parent']]['obj'];
            $parents       = $this->getAllParent($this->tree[$key]['parent'], $ret, $upLevel + 1);
            foreach (array_keys($parents) as $newKey) {
                $ret[$newKey] = $parents[$newKey];
            }
        }

        return $ret;
    }

    /**
     * Make options for a select box from
     *
     * @param string $fieldName   Name of the member variable from the
     *                            node objects that should be used as the title for the options.
     * @param string $selected    Value to display as selected
     * @param int    $key         ID of the object to display as the root of select options
     * @param string $ret         (reference to a string when called from outside) Result from previous recursions
     * @param string $prefix_orig String to indent items at deeper levels
     * @param string $prefix_curr String to indent the current item
     *
     * @return void
     * @deprecated since 2.5.9, please use makeSelectElement() functionality
     */
    protected function makeSelBoxOptions($fieldName, $selected, $key, &$ret, $prefix_orig, $prefix_curr = '')
    {
        if ($key > 0) {
            $value = $this->tree[$key]['obj']->getVar($this->myId);
            $ret .= '<option value="' . $value . '"';
            if ($value == $selected) {
                $ret .= ' selected';
            }
            $ret .= '>' . $prefix_curr . $this->tree[$key]['obj']->getVar($fieldName) . '</option>';
            $prefix_curr .= $prefix_orig;
        }
        if (isset($this->tree[$key]['child']) && !empty($this->tree[$key]['child'])) {
            foreach ($this->tree[$key]['child'] as $childKey) {
                $this->makeSelBoxOptions($fieldName, $selected, $childKey, $ret, $prefix_orig, $prefix_curr);
            }
        }
    }

    /**
     * Make a select box with options from the tree
     *
     * @param  string  $name           Name of the select box
     * @param  string  $fieldName      Name of the member variable from the
     *                                 node objects that should be used as the title for the options.
     * @param  string  $prefix         String to indent deeper levels
     * @param  string  $selected       Value to display as selected
     * @param  bool    $addEmptyOption Set TRUE to add an empty option with value "0" at the top of the hierarchy
     * @param  integer $key            ID of the object to display as the root of select options
     * @param  string  $extra
     * @return string  HTML select box
     *
     * @deprecated since 2.5.9, please use makeSelectElement()
     */
    public function makeSelBox(
        $name,
        $fieldName,
        $prefix = '-',
        $selected = '',
        $addEmptyOption = false,
        $key = 0,
        $extra = ''
    ) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $GLOBALS['xoopsLogger']->addDeprecated(__METHOD__ . " is deprecated since 2.5.9, please use makeSelectElement(), called from {$trace[0]['file']} line {$trace[0]['line']}");

        $ret = '<select name="' . $name . '" id="' . $name . '" ' . $extra . '>';
        if (false !== (bool)$addEmptyOption) {
            $ret .= '<option value="0"></option>';
        }
        $this->makeSelBoxOptions($fieldName, $selected, $key, $ret, $prefix);

        return $ret . '</select>';
    }

    /**
     * Make a select box with options from the tree
     *
     * @param  string  $name           Name of the select box
     * @param  string  $fieldName      Name of the member variable from the
     *                                 node objects that should be used as the title for the options.
     * @param  string  $prefix         String to indent deeper levels
     * @param  string  $selected       Value to display as selected
     * @param  bool    $addEmptyOption Set TRUE to add an empty option with value "0" at the top of the hierarchy
     * @param  integer $key            ID of the object to display as the root of select options
     * @param  string  $extra          extra content to add to the element
     * @param  string  $caption        optional caption for form element
     *
     * @return XoopsFormSelect form element
     */
    public function makeSelectElement(
        $name,
        $fieldName,
        $prefix = '-',
        $selected = '',
        $addEmptyOption = false,
        $key = 0,
        $extra = '',
        $caption = ''
    ) {
        xoops_load('xoopsformselect');
        $element = new XoopsFormSelect($caption, $name, $selected);
        $element->setExtra($extra);

        if (false !== (bool)$addEmptyOption) {
            $element->addOption('0', ' ');
        }
        $this->addSelectOptions($element, $fieldName, $key, $prefix);

        return $element;
    }

    /**
     * Make options for a select box from
     *
     * @param XoopsFormSelect $element     form element to receive tree values as options
     * @param string          $fieldName   Name of the member variable from the node objects that
     *                                     should be used as the title for the options.
     * @param int             $key         ID of the object to display as the root of select options
     * @param string          $prefix_orig String to indent items at deeper levels
     * @param string          $prefix_curr String to indent the current item
     *
     * @return void
     * @access private
     */
    protected function addSelectOptions($element, $fieldName, $key, $prefix_orig, $prefix_curr = '')
    {
        if ($key > 0) {
            $value = $this->tree[$key]['obj']->getVar($this->myId);
            $name = $prefix_curr . $this->tree[$key]['obj']->getVar($fieldName);
            $element->addOption($value, $name);
            $prefix_curr .= $prefix_orig;
        }
        if (isset($this->tree[$key]['child']) && !empty($this->tree[$key]['child'])) {
            foreach ($this->tree[$key]['child'] as $childKey) {
                $this->addSelectOptions($element, $fieldName, $childKey, $prefix_orig, $prefix_curr);
            }
        }
    }

    /**
     * Magic __get method
     *
     * Some modules did not respect the leading underscore is private convention and broke
     * when code was modernized. This will keep them running for now.
     *
     * @param string $name unknown variable name requested
     *                      currently, only '_tree' is supported
     *
     * @return mixed value
     */
    public function __get($name)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        if ($name === '_tree') {
            $GLOBALS['xoopsLogger']->addDeprecated("XoopsObjectTree::\$_tree is deprecated, accessed from {$trace[0]['file']} line {$trace[0]['line']}");
            return $this->tree;
        }
        $message = 'Undefined property: XoopsObjectTree::$' . $name . " in {$trace[0]['file']} line {$trace[0]['line']}";
        $GLOBALS['xoopsLogger']->addExtra(static::class, $message);

        return null;
    }
}
