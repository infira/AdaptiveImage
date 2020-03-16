<?php
define("ERROR_LEVEL", -1);
error_reporting(ERROR_LEVEL);
ini_set('error_reporting', ERROR_LEVEL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);


require_once "../src/AdaptiveImage.php";
$Ai = new \Infira\AdaptiveImage\AdaptiveImage();
$Ai->setConfig($_GET);
$Ai->setBasePath(__DIR__);
$Ai->make($_GET["src"], NULL, "./temp/");