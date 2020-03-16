<?php

namespace Infira;
class AdaptiveImage
{
	private $cachePath      = NULL;   // where to store the generated re-sized images. Specify from your document root!
	private $browserCache   = 604800; // How long the BROWSER cache should last (seconds, minutes, hours, days. 7days by default)
	private $params         = [];     //User params
	private $finalPath      = "";
	private $finalExtension = "";
	
	/*
	 * END CONFIG ---------------------------------------------------------------------------------------------------------- ------------------------ Don't edit anything after this line unless you know what you're doing ------------------------- ---------------------------------------------------------------------------------------------------------------------
	 */
	
	/* get all of the required data from the HTTP request */
	
	
	private $basePath = "";
	private $srcFile  = NULL; //Source file full location
	/**
	 * @var Imagick
	 */
	private $Image = FALSE;
	
	private $fileName = "";
	private $Size     = "";
	
	public function make($src = FALSE, $config = NULL, $cachePath = FALSE)
	{
		$this->setConfig($config);
		$this->setCachePath($cachePath);
		if (!is_dir($this->cachePath))
		{
			$this->sendError("Cache '" . $this->cachePath . "' path does not exists");
		}
		
		$this->cachePath = $this->fixPath($this->cachePath);
		$this->fileName  = basename($src);
		$this->srcFile   = $this->basePath . $src;
		
		// check if the file exists at all
		if (!file_exists($this->srcFile))
		{
			return $this->sendError("Source file not found : " . $this->srcFile);
		}
		if (!is_readable($this->srcFile) OR !is_file($this->srcFile))
		{
			return $this->sendError("File is not readable or is not and file : " . $this->srcFile);
		}
		
		if (strpos(mime_content_type($this->srcFile), "image") === FALSE)
		{
			return $this->sendError("File is not image : " . $this->srcFile);
		}
		
		$this->finalExtension = pathinfo($src)["extension"];
		
		
		/* whew might the cache file be? */
		$this->calcSizes();
		$this->setFinalPath();
		//debug($this->params);
		
		if (!$this->getParam("generate"))
		{
			return FALSE;
		}
		
		if ($this->getParam("forceCache") == TRUE && file_exists($this->getFinalPath()))
		{
			unlink($this->getFinalPath());
		}
		
		if (!$this->getParam("writeFile"))
		{
			$this->draw();
		}
		else
		{
			if (!file_exists($this->getFinalPath()))
			{
				$this->draw();
			}
			else
			{
				// not modified
				if (filemtime($this->getFinalPath()) < filemtime($this->srcFile))
				{
					// modified, clear it
					unlink($this->getFinalPath());
					$this->draw();
				}
				$this->Image = new \Imagick($this->getFinalPath());
			}
		}
		
		return $this->send();
	}
	
	public function setCachePath($path)
	{
		if ($path)
		{
			if (method_exists($this, "parseCachePath"))
			{
				$path = $this->parseCachePath($path);
			}
			$this->cachePath = $path;
		}
	}
	
	public function setBasePath($path)
	{
		if ($path)
		{
			$this->basePath = $this->fixPath($path);
		}
	}
	
	public function setConfig($config, $value = NULL)
	{
		if (is_string($config) && $value !== NULL)
		{
			$this->params[$config] = $value;
			
			return;
		}
		if (!$config)
		{
			return FALSE;
		}
		if (is_object($config))
		{
			$configArr = (array)$config;
		}
		elseif (is_string($config))
		{
			$configArr = $this->parseConfigString($config);
		}
		else
		{
			$configArr = $config;
		}
		unset($config);
		
		if (method_exists($this, "configParser"))
		{
			$configArr = $this->configParser($configArr);
		}
		foreach ($configArr as $key => $val)
		{
			if (substr($key, 0, 4) == "amp;")
			{
				$this->set(substr($key, 4), $val);
				$this->delete($key);
			}
		}
		
		$default                     = [];
		$default["width"]            = "auto";
		$default["height"]           = "auto";
		$default["quality"]          = 80;
		$default["forceCache"]       = FALSE;
		$default["removeWhiteSpace"] = FALSE;
		$default["generate"]         = TRUE;
		$default["fit"]              = FALSE; //Final image must be exacty the $width and height width backgrounds
		
		/*
		 * fitc = fit coordinates xy
		 * x (c = center, l = left)
		 * y (m = middle, t = top)
		 * OR you can provide coordinates by n,n
		 * OR for example x-10,m that is center is calculated and then minus 10
		 * OR for example x-10,m+20 that is center is calculated and then minus 10, middle is calculated and then added 20
		 *
		 * if x is negative then x is calculated from right
		 */
		$default["fitc"] = "cm";
		
		$default["bg"]                   = FALSE; //Used only in fit
		$default["debug"]                = FALSE; //Image will not send to browser
		$default["centerWaterMark"]      = FALSE;
		$default["coverWaterMarks"]      = FALSE;
		$default["writeFile"]            = TRUE;
		$default["destFileName"]         = "";
		$default["sendToBrowser"]        = TRUE;
		$default["voidRemoveWhiteSpace"] = FALSE;
		$default["blur"]                 = FALSE;
		$default["bds"]                  = "";    //base dir suffix
		$default["usd"]                  = "w";   //Use picture size in dir, w = width, h = height, both together can be used as well
		$default["cropas"]               = FALSE; //crop image after scaling
		
		$params = array_merge($default, $configArr);
		
		if (array_key_exists("size", $params))
		{
			$s = explode("x", strtolower($params["size"]));
			if (!isset($s[1]))
			{
				$s[1] = 0;
			}
			$params["width"]  = trim($s[0]);
			$params['height'] = trim($s[1]);
			
			if ($params["width"] != "auto")
			{
				$params["width"] = preg_replace("/[^0-9]/", "", $params["width"]);//https://stackoverflow.com/questions/33993461/php-remove-all-non-numeric-characters-from-a-string
			}
			if ($params['height'] != "auto")
			{
				$params["height"] = preg_replace("/[^0-9]/", "", $params['height']);
			}
		}
		$params["quality"] = preg_replace("/[^0-9]/", "", $params["quality"]);
		foreach (["width", "height", "quality"] as $cf)
		{
			$v = $params[$cf];
			if (is_numeric($v))
			{
				if ($v <= 0)
				{
					$this->sendError("$cf($v) should be bigger than 0");
				}
			}
			else
			{
				if (in_array($cf, ["height", "width"]))
				{
					if ($v !== "auto")
					{
						$this->sendError("$cf($v) should be number");
					}
				}
				else
				{
					$this->sendError("$cf($v) should be number");
				}
			}
		}
		$params["quality"] = preg_replace("/[^0-9]/", "", $params["quality"]);
		foreach ($params as $n => $v)
		{
			if (in_array($n, ["forceCache", "fit", "debug", "generate", "removeWhiteSpace", "centerWaterMark", "coverWaterMarks", "writeFile", "sendToBrowser", "voidRemoveWhiteSpace", "blur", "hdir"]))
			{
				if ($v === "0" OR $v === "false")
				{
					$v = FALSE;
				}
				elseif ($v === "1" OR $v === "true")
				{
					$v = TRUE;
				}
			}
			$params[$n] = $v;
		}
		
		if ($params["voidRemoveWhiteSpace"])
		{
			$params["removeWhiteSpace"] = FALSE;
		}
		$this->params = $params;
		
		if ($this->getParam("cropas"))
		{
			$ex                     = explode(",", $this->getParam("cropas"));
			$Crop                   = new \stdClass();
			$Crop->width            = $ex[0];
			$Crop->height           = $ex[1];
			$Crop->x                = $ex[2];
			$Crop->y                = $ex[3];
			$this->params["cropas"] = $Crop;
		}
	}
	
	public function getParam($name)
	{
		if (array_key_exists($name, $this->params))
		{
			return $this->params[$name];
		}
		
		return FALSE;
	}
	
	public function getSrc()
	{
		return $this->srcFile;
	}
	
	/*
	 * Mobile detection NOTE: only used in the event a cookie isn't available.
	 */
	private function isMobile()
	{
		if (!isset($_SERVER['HTTP_USER_AGENT']))
		{
			return FALSE;
		}
		$userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
		
		return strpos($userAgent, 'mobile');
	}
	
	public function getBg()
	{
		$bg = $this->getParam("bg");
		if ($bg == FALSE)
		{
			$bg = "transparent";
		}
		else
		{
			if ($bg{0} != "#" AND $bg != "transparent")
			{
				$bg = "#" . $bg;
			}
		}
		
		return $bg;
	}
	
	/* generates the given cache file for the given source file with the given parameters */
	private function draw()
	{
		ini_set('memory_limit', '1024M');
		set_time_limit(999);
		
		if ($this->Image === FALSE)
		{
			$this->Image = new \Imagick($this->srcFile);
		}
		
		$this->setOrentation();
		$this->removeWhiteSpace();
		$this->setCenterWaterMark();
		$this->setCoverWatermark();
		$this->blur();
		
		$bg  = $this->getBg();
		$fit = $this->getParam("fit");
		//addExtraErrorInfo("params", $this->params);
		//addExtraErrorInfo("Size", $this->Size);
		if (in_array($fit, ["size", "fill"]) && isset($this->Size->fitWidth) && isset($this->Size->fitHeight))
		{
			if ($fit == "size")
			{
				if ($this->Size->fitWidth > $this->Size->width OR $this->Size->fitHeight > $this->Size->height) // does the actual image size is bigger then resized image
				{
					$this->Image->setImageBackgroundColor($bg);
					if ($bg === "transparent")
					{
						$this->Image->setImageFormat("png");
					}
				}
				$this->Image->scaleImage($this->Size->fitWidth, $this->Size->fitHeight, TRUE);
			}
			elseif ($fit == "fill")
			{
				$this->Image->scaleImage($this->Size->width, $this->Size->height, TRUE);
			}
			else
			{
				$this->sendError("fit '$fit' is not implemented");
			}
			if ($this->getParam("fitc"))
			{
				$fitc = rawurldecode($this->getParam("fitc"));
				if ($this->strContains($fitc, ","))
				{
					$ex = explode(",", $fitc);
				}
				else
				{
					$ex = [$fitc{0}, $fitc{1}];
				}
				
				$xs  = (string)$ex[0];
				$ys  = (string)$ex[1];
				$xsl = FALSE; //does cs contains any letter l,c,etc
				$ysl = FALSE; //does cs contains any letter l,c,etc
				if ($this->strContains($xs, "l"))
				{
					$x   = 0;
					$xsl = TRUE;
				}
				elseif ($this->strContains($xs, "c"))
				{
					$x   = ($this->Size->width - $this->Size->fitWidth) / 2;
					$xsl = TRUE;
				}
				else
				{
					$x = $xs;
				}
				$xs = preg_replace('/[a-z]/si', '', $xs);
				if ($xs != "" AND $xsl)
				{
					if ($xs{0} == "-")
					{
						$x -= intval(substr($xs, 1));
					}
					elseif ($xs{0} == "+")
					{
						$x += intval(substr($xs, 1));
					}
					else
					{
						$x += $xs;
					}
				}
				
				if ((string)$x{0} == "-")
				{
					$right0 = $this->Size->width - $this->Size->fitWidth;
					$x      = $right0 + intval($x);
				}
				else
				{
					$x = intval($x);
				}
				//EOF x calculation
				
				//SOF y calculation
				if ($this->strContains($ys, "c") == "t")
				{
					$y   = 0;
					$ysl = TRUE;
				}
				elseif ($this->strContains($ys, "c") OR $this->strContains($ys, "m")) //c = center, m = middle
				{
					$y   = ($this->Size->height - $this->Size->fitHeight) / 2;
					$ysl = TRUE;
				}
				else
				{
					$y = intval($ys);
				}
				$ys = preg_replace('/[a-z]/si', '', $ys);
				if ($ys != "" AND $ysl)
				{
					if ($ys{0} == "-")
					{
						$y -= intval(substr($ys, 1));
					}
					elseif ($ys{0} == "+")
					{
						$y += intval(substr($ys, 1));
					}
					else
					{
						$y += intval($ys);
					}
				}
				
				if ((string)$y{0} == "-")
				{
					$bottom0 = $this->Size->height - $this->Size->fitHeight;
					$y       = $bottom0 + intval($y);
				}
				else
				{
					$y = intval($y);
				}
				//EOF y calculation
				$this->Image->extentImage($this->Size->fitWidth, $this->Size->fitHeight, $x, $y);
			}
			else
			{
				$this->Image->extentImage($this->Size->fitWidth, $this->Size->fitHeight, ($this->Size->width - $this->Size->fitWidth) / 2, ($this->Size->height - $this->Size->fitHeight) / 2);
			}
		}
		else
		{
			$this->Image->scaleImage($this->Size->width, $this->Size->height, TRUE);
		}
		
		if ($this->getParam("cropas"))
		{
			$Crop = $this->getParam("cropas");
			$this->Image->cropImage($Crop->width, $Crop->height, $Crop->x, $Crop->y);
		}
		
		
		$format               = strtolower($this->Image->getImageFormat());
		$this->finalExtension = $format;
		if (in_array($format, ["jpg", "jpeg"]))
		{
			$this->Image->setImageCompression(\Imagick::COMPRESSION_JPEG);
			$this->Image->setImageCompressionQuality($this->getParam("quality"));
			
			$this->Image->setImageResolution(72, 72);
			$this->Image->setImageUnits(1);
			//$this->Image->setInterlaceScheme(\Imagick::INTERLACE_JPEG);
			//$this->Image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
			$this->Image->setSamplingFactors(['2x2', '1x1', '1x1']); //https://stackoverflow.com/questions/27132047/how-can-i-save-a-jpg-image-in-420-colorspace-with-imagick
			$this->finalExtension = "jpg";
			
		}
		elseif ($format == "png")
		{
			$this->Image->setFormat("png"); //rewrite format for transparency, etc
			/*
			$pngColorspace = \Imagick::COLORSPACE_RGB;
			if ($bg == "transparent")
			{
				$pngColorspace = \Imagick::COLORSPACE_TRANSPARENT;
			}
			$colors = min(255, $this->Image->getImageColors());
			$this->Image->quantizeImage($colors, $pngColorspace, 0, false, false);
			*/
			//$this->Image->setImageDepth(24);
		}
		$this->Image->stripImage();
		
		return TRUE;
	}
	
	private function calcSizes()
	{
		$Output         = new \stdClass();
		$Output->width  = "auto";
		$Output->height = "auto";
		if ($this->Image === FALSE) //much faster way to calculate image size
		{
			$size              = getimagesize($this->srcFile);
			$Output->srcWidth  = $size[0];
			$Output->srcHeight = $size[1];
		}
		else
		{
			$size              = $this->Image->getImageGeometry();
			$Output->srcWidth  = $size["width"];
			$Output->srcHeight = $size["height"];
		}
		$newWidth  = $this->getParam("width");
		$newHeight = $this->getParam("height");
		
		if ($newWidth != "auto" && $newHeight == "auto")
		{
			$calc           = $this->calcNewSize("W", $Output->srcWidth, $Output->srcHeight, $newWidth, $newHeight);
			$Output->width  = $calc->width;
			$Output->height = $calc->height;
			//debug("s1", $Output);
		}
		elseif ($newWidth == "auto" && $newHeight != "auto")
		{
			//debug("s2", $Output);
			$calc           = $this->calcNewSize("H", $Output->srcWidth, $Output->srcHeight, $newWidth, $newHeight);
			$Output->width  = $calc->width;
			$Output->height = $calc->height;
		}
		else //width = n, height = n
		{
			$Output->width  = $newWidth;
			$Output->height = $newHeight;
		}
		$Output->finalWidth  = $newWidth;
		$Output->finalHeight = $newHeight;
		
		//debug("s3", $Output);
		if ($this->getParam("fit"))
		{
			$Output->fitWidth    = $Output->width;
			$Output->fitHeight   = $Output->height;
			$Output->finalWidth  = $Output->fitWidth;
			$Output->finalHeight = $Output->fitHeight;
			
			$calc = $this->calcNewSize("W", $Output->srcWidth, $Output->srcHeight, $Output->fitWidth, $Output->fitHeight);
			if ($this->getParam("fit") == "fill")
			{
				if ($calc->width >= $Output->fitWidth && $calc->height >= $Output->fitHeight)
				{
					//debug("s4", $Output);
					$Output->width  = $calc->width;
					$Output->height = $calc->height;
				}
				else
				{
					//debug("s4", $Output);
					$calc           = $this->calcNewSize("H", $Output->srcWidth, $Output->srcHeight, $Output->fitWidth, $Output->fitHeight);
					$Output->width  = $calc->width;
					$Output->height = $calc->height;
				}
			}
			else //fit = size
			{
				if ($calc->width <= $Output->fitWidth && $calc->height <= $Output->fitHeight)
				{
					//debug("s5", $Output);
					$Output->width  = $calc->width;
					$Output->height = $calc->height;
				}
				else
				{
					//debug("s6", $Output);
					$calc           = $this->calcNewSize("H", $Output->srcWidth, $Output->srcHeight, $Output->fitWidth, $Output->fitHeight);
					$Output->width  = $calc->width;
					$Output->height = $calc->height;
				}
			}
		}
		$this->Size = $Output;
	}
	
	private function calcNewSize($propFlag, $srcWidth, $srcHeight, $newWidth, $newHeight)
	{
		$Output   = new \stdClass();
		$propFlag = strtoupper($propFlag);
		if ($propFlag == 'H')
		{
			/**
			 * Calculate the new size to be proportional Vertical.
			 */
			$Output->width  = round($newHeight * ($srcWidth / $srcHeight)); // photoshop way
			$Output->height = $newHeight;
		}
		elseif ($propFlag == 'W')
		{
			/**
			 * Calculate the new size to be proporcional V.
			 */
			$Output->height = round($newWidth * ($srcHeight / $srcWidth)); // photoshop way
			$Output->width  = $newWidth;
		}
		elseif ($propFlag == 'AUTO')
		{
			if ($srcWidth > $srcHeight)
			{
				return $this->calcNewSize("W", $srcWidth, $srcHeight, $newWidth, $newHeight);
			}
			else
			{
				return $this->calcNewSize("H", $srcWidth, $srcHeight, $newWidth, $newHeight);
			}
		}
		
		return $Output;
	}
	
	/* helper function: Create and send an image with an error message. */
	private function sendError($message)
	{
		/* Create Imagick objects */
		$this->Image = new \Imagick();
		$draw        = new \ImagickDraw();
		$color       = new \ImagickPixel('#000000');
		$background  = new \ImagickPixel('#FFFFFF'); // Transparent
		
		/* Font properties */
		$draw->setFont(__DIR__ . "/fonts/Nobile-Regular.ttf");
		$draw->setFontSize(14);
		$draw->setFillColor($color);
		$draw->setStrokeAntialias(TRUE);
		$draw->setTextAntialias(TRUE);
		
		/* Get font metrics */
		$metrics = $this->Image->queryFontMetrics($draw, $message);
		/* Create text */
		$draw->annotation(0, $metrics['ascender'], $message);
		
		/* Create image */
		$this->Image->newImage($metrics['textWidth'], $metrics['textHeight'], $background);
		$this->Image->setImageFormat('png');
		$this->Image->drawImage($draw);
		
		
		return $this->send(TRUE);
	}
	
	private function setFinalPath()
	{
		if ($this->getParam("destFileName") && file_exists($this->getParam("destFileName")))
		{
			$destFileName = $this->getParam("destFileName");
		}
		else
		{
			$extras   = [];
			$extras[] = $this->cachePath;
			if ($this->getParam("bds"))
			{
				$extras[] = $this->fixPath($this->getParam("bds"));
			}
			
			if ($this->getParam("usd"))
			{
				$usd = strtolower($this->getParam("usd"));
				$a   = "";
				if ($this->strContains($usd, "w"))
				{
					$width = $this->getParam("width");
					if ($width == "auto")
					{
						$width = $this->Size->finalWidth;
					}
					$a = $width;
				}
				if ($this->strContains($usd, "w") AND $this->strContains($usd, "h"))
				{
					$a .= "x";
				}
				if ($this->strContains($usd, "h"))
				{
					$height = $this->getParam("height");
					if ($height == "auto")
					{
						$height = $this->Size->finalHeight;
					}
					$a .= $height;
				}
				$extras[] = $a;
			}
			if ($this->getParam("removeWhiteSpace"))
			{
				$extras[] = "rms";
			}
			if ($this->getParam("centerWaterMark"))
			{
				$extras[] = "cwm";
			}
			if ($this->getParam("fit"))
			{
				$extras[] = $this->getParam("fit");
			}
			$destFileName = str_replace("//", "/", join("/", $extras) . "/") . $this->fileName;
		}
		
		$info            = (object)(pathinfo($destFileName));
		$this->finalPath = $this->fixPath($info->dirname) . $info->filename . ".%extension%";
	}
	
	public function getFinalPath()
	{
		$extension = $this->finalExtension;
		
		return $this->assignStrVars(["extension" => $extension], $this->finalPath);
	}
	
	private function send($sendError = FALSE)
	{
		if ($this->getParam("writeFile") && !file_exists($this->getFinalPath()) AND $sendError == FALSE)
		{
			$destPath = dirname($this->getFinalPath());
			
			if (empty($destPath))
			{
				$this->sendError("Destionation path not seted");
			}
			
			// Make the dir if missing.
			if (!is_dir($destPath))
			{
				mkdir($destPath, 0755, TRUE);
			}
			
			// Check if we can write to this dir.
			if (!is_dir($destPath) || !is_writable($destPath))
			{
				return $this->sendError('Failed to create destination directory at: ' . $destPath);
			}
			$this->Image->writeImage($this->getFinalPath());
		}
		
		if ($this->getParam("sendToBrowser") OR $sendError)
		{
			if (!$this->getParam("debug"))
			{
				$this->cleanOutput(TRUE);
				header("Content-Type: image/" . $this->Image->getImageFormat());
				header("Cache-Control: private, max-age=" . $this->browserCache);
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $this->browserCache) . ' GMT');
				if (file_exists($this->getFinalPath()))
				{
					echo file_get_contents($this->getFinalPath());
				}
				else
				{
					echo $this->Image->getImageBlob();
				}
				exit();
			}
		}
		if ($this->getParam("debug"))
		{
			exit("ok");
		}
	}
	
	protected function setOrentation()
	{
		$orientation = $this->Image->getImageOrientation();
		switch ($orientation)
		{
			case \Imagick::ORIENTATION_BOTTOMRIGHT:
				$this->Image->rotateimage("#000", 180); // rotate 180 degrees
			break;
			
			case \Imagick::ORIENTATION_RIGHTTOP:
				$this->Image->rotateimage("#000", 90); // rotate 90 degrees CW
			break;
			
			case \Imagick::ORIENTATION_LEFTBOTTOM:
				$this->Image->rotateimage("#000", -90); // rotate 90 degrees CCW
			break;
		}
	}
	
	protected function removeWhiteSpace($colorToTrim = "#FFFFFF")
	{
		if ($this->getParam("removeWhiteSpace"))
		{
			// Set the color to trim:
			$this->Image->borderImage($colorToTrim, 1, 1);
			$this->Image->trimImage(0.1 * $this->Image->getQuantumRange()["quantumRangeLong"]);
			$this->Image->setImagePage(0, 0, 0, 0);
			$this->calcSizes();
		}
	}
	
	protected function opacity(&$img, $alpha)
	{
		if (!is_object($img))
		{
			return FALSE;
		}
		if ($alpha > 1 || $alpha < 0)
		{
			return FALSE;
		}
		$img->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $alpha, \Imagick::CHANNEL_ALPHA);
		
		return TRUE;
	}
	
	protected function setCenterWaterMark()
	{
		if ($this->getParam("centerWaterMark"))
		{
			
			$watermark = new \Imagick();
			$watermark->readImage("app/assets/img/logoWaterMarkBig.png");
			
			$this->opacity($watermark, 0.33);
			
			// how big are the images?
			$wWidth  = $watermark->getImageWidth();
			$wHeight = $watermark->getImageHeight();
			
			$Calc = $this->calcNewSize("W", $wWidth, $wHeight, $this->Size->width * 0.7, $this->Size->height * 0.7);
			$watermark->scaleImage($Calc->width, $Calc->height);
			// get new size
			$wWidth  = $watermark->getImageWidth();
			$wHeight = $watermark->getImageHeight();
			
			
			// calculate the position
			$destX = ($this->Size->width / 2) - ($wWidth / 2);
			$destY = ($this->Size->height / 2) - ($wHeight / 2);
			
			$this->Image->compositeImage($watermark, \Imagick::COMPOSITE_DISSOLVE, $destX, $destY);
		}
	}
	
	protected function setCoverWatermark()
	{
		if ($this->getParam("coverWaterMarks"))
		{
			$watermark = new \Imagick();
			$watermark->readImage("app/assets/img/coverLogoWaterMarkBig.png");
			$this->opacity($watermark, 0.33);
			
			$wWidth  = $watermark->getImageWidth();
			$wHeight = $watermark->getImageHeight();
			$Calc    = $this->calcNewSize("W", $wWidth, $wHeight, $wWidth * 0.4, $wHeight * 0.4);
			$watermark->scaleImage($Calc->width, $Calc->height);
			// get new size
			$wWidth  = $watermark->getImageWidth();
			$wHeight = $watermark->getImageHeight();
			
			// calculate the position
			$destXSum = 0;
			$destYSum = 0;
			while ($destXSum <= $this->Size->width)
			{
				while ($destYSum <= $this->Size->height)
				{
					$this->Image->compositeImage($watermark, \Imagick::COMPOSITE_DISSOLVE, $destXSum, $destYSum);
					$destYSum += $wHeight;
				}
				$destXSum += $wWidth;
				$destYSum = 0;
			}
		}
	}
	
	private function blur()
	{
		if ($this->getParam("blur"))
		{
			$this->Image->blurImage(99, 99);
		}
	}
	
	//###########Helpers
	private function fixPath($path)
	{
		$path = str_replace("/", DIRECTORY_SEPARATOR, $path);
		$len  = strlen($path) - 1;
		if ($len > 0)
		{
			if ($path{$len} != DIRECTORY_SEPARATOR and !is_file($path))
			{
				$path .= DIRECTORY_SEPARATOR;
			}
		}
		
		return $path;
	}
	
	private function parseConfigString(string $string): array
	{
		$data   = [];
		$string = trim($string);
		
		$string = str_replace("&amp;", "&", $string);
		$string = str_replace("&", "&", $string);
		$string = str_replace("\n", "&", $string);
		
		parse_str($string, $data);
		if (get_magic_quotes_gpc() == 1)
		{
			$data = stripslashes($data);
		}
		if (!is_array($data))
		{
			$data = [];
		}
		
		return $data;
	}
	
	private function cleanOutput($isRecursive = FALSE)
	{
		if (ob_get_contents())
		{
			ob_clean();
			ob_end_clean();
			if ($isRecursive)
			{
				cleanOutput(TRUE);
			}
		}
	}
	
	private function assignStrVars($vars, $string, $defaultVars = FALSE)
	{
		if (is_string($vars))
		{
			$vars = parseStr($vars);
		}
		if (is_array($vars) AND count($vars) > 0)
		{
			foreach ($vars as $name => $value)
			{
				$string = str_replace(['%' . $name . '%', '[' . $name . ']'], $value, $string);
			}
		}
		if ($defaultVars !== FALSE)
		{
			$string = $this->assignStrVars($defaultVars, $string, FALSE);
		}
		
		return $string;
	}
	
	private function strContains($from, $str)
	{
		$post = strpos($from, $str);
		if ($post === FALSE)
		{
			return FALSE;
		}
		if ($post >= 0)
		{
			return TRUE;
		}
		
		return FALSE;
	}
}

?>