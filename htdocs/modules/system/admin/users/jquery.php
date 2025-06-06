<?php
/**
 * jQuery File Tree PHP Connector
 * Output a list of files for jQuery File Tree
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
 * @author              Maxime Cointin (AKA Kraven30)
 * @package             system
 */
/** @var XoopsUser $xoopsUser */
/** @var XoopsModule $xoopsModule */
use Xmf\Request;

require dirname(__DIR__, 4) . '/mainfile.php';
require XOOPS_ROOT_PATH . '/header.php';

// defined('XOOPS_ROOT_PATH') || exit('XOOPS root path not defined');

if (!is_object($xoopsUser) || !is_object($xoopsModule) || !$xoopsUser->isAdmin($xoopsModule->mid())) {
    exit(_NOPERM);
}

$op = Request::getCmd('op', 'default', 'REQUEST');

switch ($op) {

    // Display post
    case 'display_post':
        global $xoopsDB;

        $GLOBALS['xoopsLogger']->activated = false;

        include_once XOOPS_ROOT_PATH . '/include/comment_constants.php';
        include_once XOOPS_ROOT_PATH . '/kernel/module.php';
        include_once XOOPS_ROOT_PATH . '/modules/system/include/functions.php';

        $tables = [];
        // Count comments (approved only: com_status == XOOPS_COMMENT_ACTIVE)
        $tables[] = ['table_name' => 'xoopscomments', 'uid_column' => 'com_uid', 'criteria' => new Criteria('com_status', XOOPS_COMMENT_ACTIVE)];
        // Count forum posts
        if (XoopsModule::getByDirname('newbb')) {
            // Added support for NewBB 5.0 new table naming convention
            $tableTest = new \Xmf\Database\Tables();
            if($tableTest->useTable('newbb_posts')) {
                $tables[] = ['table_name' => 'newbb_posts', 'uid_column' => 'uid'];
            } else {
                $tables[] = ['table_name' => 'bb_posts', 'uid_column' => 'uid'];
            }
        }
        $uid         = Request::getInt('uid', 0);
        $total_posts = 0;
        foreach ($tables as $table) {
            $criteria = new CriteriaCompo();
            $criteria->add(new Criteria($table['uid_column'], $uid));
            if (!empty($table['criteria'])) {
                $criteria->add($table['criteria']);
            }
            $sql = 'SELECT COUNT(*) AS total FROM ' . $xoopsDB->prefix($table['table_name']) . ' ' . $criteria->renderWhere();
            $result = $xoopsDB->query($sql);
            if ($xoopsDB->isResultSet($result)) {
                if ($row = $xoopsDB->fetchArray($result)) {
                    $total_posts += $row['total'];
                }
            }
        }

        $sql = 'UPDATE ' . $xoopsDB->prefix('users') . " SET posts = '" . $total_posts . "' WHERE uid = '" . $uid . "'";
        if (!$result = $xoopsDB->queryF($sql)) {
            redirect_header('admin.php?fct=users', 1, _AM_SYSTEM_USERS_CNUUSER);
        } else {
            echo $total_posts;
        }
        break;
}
