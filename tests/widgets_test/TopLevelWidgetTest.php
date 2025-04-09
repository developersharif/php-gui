<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpGui\Application;
use PhpGui\Widget\TopLevel;

$app = new Application();

$topLevel = new TopLevel(['text' => 'Initial Top Level']);
echo "TopLevelWidgetTest: TopLevel widget created with text: 'Initial Top Level'\n";

$color = $topLevel->chooseColor(); 
echo "TopLevelWidgetTest: chooseColor returned: $color\n";

$dir = $topLevel->chooseDirectory();
echo "TopLevelWidgetTest: chooseDirectory returned: $dir\n";

$response = $topLevel->dialog("Test Dialog", "This is a test", "info", "OK", "Cancel", "Extra");
echo "TopLevelWidgetTest: dialog returned: $response\n";

$openFile = $topLevel->getOpenFile();
echo "TopLevelWidgetTest: getOpenFile returned: $openFile\n";

$saveFile = $topLevel->getSaveFile();
echo "TopLevelWidgetTest: getSaveFile returned: $saveFile\n";

$msgResponse = $topLevel->messageBox("Test Message", "okcancel");
echo "TopLevelWidgetTest: messageBox returned: $msgResponse\n";

$popupResponse = $topLevel->popupMenu(100, 100);
echo "TopLevelWidgetTest: popupMenu returned: $popupResponse\n";

$topLevel->setText("Updated Top Level Text");
echo "TopLevelWidgetTest: TopLevel text updated\n";

$topLevel->destroy();
echo "TopLevelWidgetTest: TopLevel widget destroyed\n";

$app->quit();
