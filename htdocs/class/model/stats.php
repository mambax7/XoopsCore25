<?php
/**
 * Object stats handler class.
 *
 * You may not change or alter any portion of this comment or credits
 * of supporting developers from this source code or any supporting source code
 * which is considered copyrighted (c) material of the original comment or credit authors.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright       (c) 2000-2016 XOOPS Project (www.xoops.org)
 * @license             GNU GPL 2 (https://www.gnu.org/licenses/gpl-2.0.html)
 * @package             kernel
 * @subpackage          model
 * @since               2.3.0
 * @author              Taiwen Jiang <phppp@users.sourceforge.net>
 */
defined('XOOPS_ROOT_PATH') || exit('Restricted access');

/**
 * Object stats handler class.
 *
 * @author Taiwen Jiang <phppp@users.sourceforge.net>
 *
 * {@link XoopsModelAbstract}
 */
class XoopsModelStats extends XoopsModelAbstract
{
    /**
     * count objects matching a condition
     *
     * @param  CriteriaElement|CriteriaCompo $criteria {@link CriteriaElement} to match
     * @return int|array    count of objects
     */
    public function getCount(CriteriaElement $criteria = null)
    {
        $field   = '';
        $groupby = false;
        if (isset($criteria) && is_subclass_of($criteria, 'CriteriaElement')) {
            if ($criteria->groupby != '') {
                $groupby = true;
                $field   = $criteria->groupby . ', ';
            }
        }
        $sql = "SELECT {$field} COUNT(*) FROM `{$this->handler->table}`";
        if (isset($criteria) && is_subclass_of($criteria, 'CriteriaElement')) {
            $sql .= ' ' . $criteria->renderWhere();
            $sql .= $criteria->getGroupby();
        }
        $result = $this->handler->db->query($sql);
        if (!$this->handler->db->isResultSet($result)) {
            // \trigger_error("Query Failed! SQL: $sql- Error: " . $this->handler->db->error(), E_USER_ERROR);
            return 0;
        }
        if ($groupby == false) {
            list($count) = $this->handler->db->fetchRow($result);

            return (int) $count;
        } else {
            $ret = array();
            while (false !== (list($id, $count) = $this->handler->db->fetchRow($result))) {
                $ret[$id] = (int) $count;
            }

            return $ret;
        }
    }

    /**
     * get counts matching a condition
     *
     * @param  CriteriaElement|CriteriaCompo  $criteria {@link CriteriaElement} to match
     * @return array  of counts
     */
    public function getCounts(CriteriaElement $criteria = null)
    {
        $ret         = array();
        $sql_where   = '';
        $limit       = null;
        $start       = null;
        $groupby_key = $this->handler->keyName;
        if (isset($criteria) && is_subclass_of($criteria, 'CriteriaElement')) {
            $sql_where = $criteria->renderWhere();
            $limit     = $criteria->getLimit();
            $start     = $criteria->getStart();
            if ($groupby = $criteria->groupby) {
                $groupby_key = $groupby;
            }
        }
        $sql = "SELECT {$groupby_key}, COUNT(*) AS count" . " FROM `{$this->handler->table}`" . " {$sql_where}" . " GROUP BY {$groupby_key}";
        $result = $this->handler->db->query($sql, $limit, $start);
        if (!$this->handler->db->isResultSet($result)) {
            // \trigger_error("Query Failed! SQL: $sql- Error: " . $this->handler->db->error(), E_USER_ERROR);
            return $ret;
        }
        while (false !== (list($id, $count) = $this->handler->db->fetchRow($result))) {
            $ret[$id] = (int) $count;
        }

        return $ret;
    }
}
