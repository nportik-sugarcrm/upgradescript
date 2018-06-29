<?php
include './general/treeWalker.php';
include './general/fileManager.php';
include './upgrades/handlebars/pathfixer.php';

function upgradeHandleBars($path) {
    $treeWalker = new treeWalker($path, array('node_modules'));
    $treeWalker->mapFiles();
    $hbsFiles = $treeWalker->getFilesList('hbs', true, 'path');

    $update1 = new pathFixer();
    $manager = new fileManager();
    forEach($hbsFiles as $hbs) {
        $update1->run($hbs, $manager);
    }
}

if (isset($_POST['path'])) {
    upgradeHandleBars($_POST['path']);
}
?>
