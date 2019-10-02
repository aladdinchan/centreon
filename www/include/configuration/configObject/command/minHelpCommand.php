<?php
/*
 * Copyright 2005-2019 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (!isset($oreon)) {
    exit();
}

if (isset($_GET["command_id"])) {
    $commandId = $_GET["command_id"];
} elseif (isset($_POST["command_id"])) {
    $commandId = $_POST["command_id"];
} else {
    $commandId = null;
}
$commandId = filter_var(
    isset($commandId) ? $commandId : null,
    FILTER_VALIDATE_INT
);

if (isset($_GET["command_name"])) {
    $commandName = $_GET["command_name"];
} elseif (isset($_POST["command_name"])) {
    $commandName = $_POST["command_name"];
} else {
    $commandName = null;
}
$commandName = filter_var(
    isset($commandName) ? $commandName : null,
    FILTER_SANITIZE_STRING
);

if ($commandId !== false) {
    /*
     * Get command information
     */
    $res = $pearDB->query(
        "SELECT * FROM `command` WHERE `command_id` = '" . (int)$commandId . "' LIMIT 1"
    );
    $cmd = $res->fetchRow();
    unset($res);

    $aCmd = explode(" ", $cmd["command_line"]);
    $fullLine = $aCmd[0];
    $aCmd = explode("/", $fullLine);
    $resourceInfo = $aCmd[0];
    $resourceDef = str_replace('$', '@DOLLAR@', $resourceInfo);

    /*
     * Match if the first part of the path is a MACRO
     */
    if (preg_match("/@DOLLAR@USER([0-9]+)@DOLLAR@/", $resourceDef, $matches)) {
        /*
         * Select Resource line
         */
        $res = $pearDB->query(
            "SELECT `resource_line` FROM `cfg_resource` WHERE `resource_name` = '\$USER" . $matches[1] . "\$' LIMIT 1"
        );

        $resource = $res->fetchRow();
        unset($res);

        $resourcePath = $resource["resource_line"];
        unset($aCmd[0]);
        $command = rtrim($resourcePath, "/") . "#S#" . implode("#S#", $aCmd);
    } else {
        $command = $fullLine;
    }
} else {
    $command = $oreon->optGen["nagios_path_plugins"] . $commandName;
}

$command = str_replace("#S#", "/", $command);
$command = str_replace("#BS#", "\\", $command);

if (strncmp($command, "/usr/lib/nagios/", strlen("/usr/lib/nagios/"))) {
    if (is_dir("/usr/lib64/nagios/")) {
        $command = str_replace("/usr/lib/nagios/plugins/", "/usr/lib64/nagios/plugins/", $command);
        $oreon->optGen["nagios_path_plugins"] = str_replace("/usr/lib/nagios/plugins/", "/usr/lib64/nagios/plugins/", $oreon->optGen["nagios_path_plugins"]);
    }
}

$tab = explode(' ', $command);
if (strncmp(realpath($tab[0]), $oreon->optGen["nagios_path_plugins"], strlen($oreon->optGen["nagios_path_plugins"]))) {
    $msg = _('Error: Cannot Execute this command due to a path security problem.');
    $command = realpath($tab[0]);
} else {
    $command = realpath($tab[0]);
    $stdout = shell_exec(realpath($tab[0])." --help");
    $msg = str_replace("\n", "<br />", $stdout);
}

$attrsText  = array("size" => "25");
$form = new HTML_QuickForm('Form', 'post', "?p=".$p);
$form->addElement('header', 'title', _("Plugin Help"));

/*
 * Command information
 */
$form->addElement('header', 'information', _("Help"));
$form->addElement('text', 'command_line', _("Command Line"), $attrsText);
$form->addElement('text', 'command_help', _("Output"), $attrsText);

/*
 * Smarty template Init
 */
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

/*
 * Apply a template definition
 */
$renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl);
$form->accept($renderer);
$tpl->assign('form', $renderer->toArray());
$tpl->assign('o', $o);
$tpl->assign('command_line', $command." --help");
if (isset($msg) && $msg) {
    $tpl->assign('msg', $msg);
}

$tpl->display("minHelpCommand.ihtml");
