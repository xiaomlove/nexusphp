<?php
require "../include/bittorrent.php";

if (!empty($_REQUEST['view'])) {
    $view = trim($_REQUEST['view'], "/.");
    $view = str_replace(".", "/", $view);
    if (!empty($_REQUEST['plugin'])) {
        $pluginId = $_REQUEST['plugin'];
        $plugin = \Nexus\Plugin\Plugin::getById($pluginId);
        $viewFile = $plugin->getNexusView($view);
    } else {
        $viewFile = ROOT_PATH . "resources/views/$view";
    }

    if (!str_ends_with($viewFile, ".php")) {
        $viewFile .= ".php";
    }
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        $msg = "viewFile: $viewFile not exists, _REQUEST: " . json_encode($_REQUEST);
        do_log($msg, "error");
        throw new \RuntimeException($msg);
    }
} else {
    $msg = "require view parameter, _REQUEST: " . json_encode($_REQUEST);
    do_log($msg, "error");
    throw new \RuntimeException($msg);
}
