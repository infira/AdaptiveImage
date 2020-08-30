<?php
define("ERROR_LEVEL", -1);
error_reporting(ERROR_LEVEL);
ini_set('error_reporting', ERROR_LEVEL);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);

require_once "../src/AdaptiveImageSize.php";
require_once "../src/AdaptiveImageImagick.php";
require_once "../src/AdaptiveImage.php";
$Ai = new \Infira\AdaptiveImage\AdaptiveImage();
$Ai->setConfig($_GET);
$Ai->setSrcPath(__DIR__);
$Ai->setCachePath("./temp/");
$Ai->make($_GET["src"], null, "./temp/");