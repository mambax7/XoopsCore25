<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
*/
/**
 * xoAdminIcons Smarty compiler plug-in
 *
 * @copyright    (c) 2000-2025 XOOPS Project (https://xoops.org)
 * @license          GNU GPL 2 or later (https://www.gnu.org/licenses/gpl-2.0.html)
 * @author           Andricq Nicolas (AKA MusS)
 * @since            2.5
 * @param string[] $params
 * @param Smarty   $smarty
 * @return string
 */

function smarty_compiler_xoAdminIcons($params, $smarty)
{
    global $xoops, $xoTheme;

    $argStr = reset($params);
    $argStr = trim($argStr,"' \t\n\r\0");

    $icons = xoops_getModuleOption('typeicons', 'system');
    if ($icons == '') {
        $icons = 'default';
    }

    if (file_exists($xoops->path('modules/system/images/icons/' . $icons . '/index.php'))) {
        $url = $xoops->url('modules/system/images/icons/' . $icons . '/' . $argStr);
    } else {
        if (file_exists($xoops->path('modules/system/images/icons/default/' . $argStr))) {
            $url = $xoops->url('modules/system/images/icons/default/' . $argStr);
        } else {
            $url = $xoops->url('modules/system/images/icons/default/xoops/xoops.png');
        }
    }

    return "<?php echo '" . addslashes($url) . "'; ?>";
}
