<?php

namespace Infira\AdaptiveImage;

use Imagick;

class AdaptiveImage
{
	private $cachePath    = null;   // where to store the generated re-sized images. Specify from your document root!
	private $browserCache = 604800; // How long the BROWSER cache should last (seconds, minutes, hours, days. 7days by default)
	private $config       = [];     //User params
	private $finalPath;
	private $finalExtension;
	private $finalFileName;
	private $srcPath;
	private $srcFile; //Source file full location
	
	/**
	 * @var AdaptiveImageSize
	 */
	private $Size;
	
	
	/**
	 * @var AdaptiveImageImagick
	 */
	private $Image = null;
	
	
	public function make($src = false, $config = null)
	{
		$this->setConfig($config);
		if (!is_dir($this->cachePath))
		{
			$this->sendError("Cache '" . $this->cachePath . "' path does not exists");
		}
		if (!$src)
		{
			return $this->sendError("Source file is not defined");
		}
		$this->finalFileName = basename($src);
		$this->srcFile       = $this->srcPath . $src;
		
		// check if the file exists at all
		if (!file_exists($this->srcFile))
		{
			return $this->sendError("Source file not found : " . $this->srcFile);
		}
		if (!is_readable($this->srcFile) or !is_file($this->srcFile))
		{
			return $this->sendError("File is not readable or is not and file : " . $this->srcFile);
		}
		
		if (strpos(mime_content_type($this->srcFile), "image") === false)
		{
			return $this->sendError("File is not image : " . $this->srcFile);
		}
		
		$this->finalExtension = pathinfo($src)["extension"];
		
		
		/* whew might the cache file be? */
		$this->Size = new AdaptiveImageSize();
		$this->Size->calculate($this->srcFile, $this->getConf('width'), $this->getConf('height'), $this->getConf('fit'));
		$this->setFinalPath();
		//debug($this->params);
		
		if (!$this->getConf("generate"))
		{
			return false;
		}
		
		if ($this->getConf("forceCache") == true && file_exists($this->getFinalPath()))
		{
			unlink($this->getFinalPath());
		}
		
		if (!$this->getConf("writeFile"))
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
				else
				{
					$this->Image = new Imagick($this->getFinalPath());
				}
			}
		}
		
		return $this->send();
	}
	
	/**
	 * Where to save images
	 *
	 * @param string $path
	 */
	public function setCachePath(string $path)
	{
		if ($path)
		{
			if (method_exists($this, "parseCachePath"))
			{
				$path = $this->parseCachePath($path);
			}
			if (!is_dir($path))
			{
				$this->sendError("Cache path must be valid path");
			}
			$this->cachePath = $path;
		}
	}
	
	/**
	 * Get source file
	 *
	 * @return null
	 */
	public function getSrc()
	{
		return $this->srcFile;
	}
	
	/**
	 * You can set src path to usr shorter url ?src=image.jpg instead of ?src=/full/path/to/image.jpg
	 *
	 * @param string $path
	 */
	public function setSrcPath(string $path)
	{
		if ($path)
		{
			if (!is_dir($path))
			{
				$this->sendError("source path must be valid path");
			}
			$this->srcPath = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path . DIRECTORY_SEPARATOR);
		}
	}
	
	public function setConfig($config, $value = null)
	{
		if (is_string($config) && $value !== null)
		{
			$this->config[$config] = $value;
			
			return;
		}
		if (!$config)
		{
			return false;
		}
		if (is_object($config))
		{
			$configArr = (array)$config;
		}
		elseif (is_string($config))
		{
			$configArr = [];
			$config    = trim($config);
			
			$config = str_replace("&amp;", "&", $config);
			$config = str_replace("&", "&", $config);
			$config = str_replace("\n", "&", $config);
			parse_str($config, $configArr);
			if (get_magic_quotes_gpc() == 1)
			{
				$configArr = stripslashes($config);
			}
			if (!is_array($configArr))
			{
				$configArr = [];
			}
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
		$default["forceCache"]       = false;
		$default["removeWhiteSpace"] = false;
		$default["generate"]         = true;
		$default["fit"]              = false; //Final image must be exacty the $width and height width backgrounds
		
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
		
		$default["bg"]                   = false; //Used only in fit
		$default["debug"]                = false; //Image will not send to browser
		$default["writeFile"]            = true;
		$default["destFileName"]         = "";
		$default["sendToBrowser"]        = true;
		$default["voidRemoveWhiteSpace"] = false;
		$default["blur"]                 = false;
		$default["bds"]                  = "";    //base dir suffix
		$default["usd"]                  = "w";   //Use picture size in dir, w = width, h = height, both together can be used as well
		$default["cropas"]               = false; //crop image after scaling
		
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
			if ((!is_numeric($params['width']) and $params['width'] != 'auto') or (!is_numeric($params['height']) and $params['height'] != 'auto'))
			{
				$this->sendError('size paramater must be $numberx$number');
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
		if ($params["width"] == "auto" and $params["height"] == "auto")
		{
			if (!isset($params['size']))
			{
				$this->sendError("size parameter is not defined, or width,heigh");
			}
			else
			{
				$this->sendError("width and height cannot be auto at the same time");
			}
		}
		$params["quality"] = preg_replace("/[^0-9]/", "", $params["quality"]);
		foreach ($params as $n => $v)
		{
			if (in_array($n, ["forceCache", "fit", "debug", "generate", "removeWhiteSpace", "writeFile", "sendToBrowser", "voidRemoveWhiteSpace", "blur", "hdir"]))
			{
				if ($v === "0" or $v === "false")
				{
					$v = false;
				}
				elseif ($v === "1" or $v === "true")
				{
					$v = true;
				}
			}
			$params[$n] = $v;
		}
		
		if ($params["voidRemoveWhiteSpace"])
		{
			$params["removeWhiteSpace"] = false;
		}
		$this->config = $params;
		
		if ($this->getConf("cropas"))
		{
			$ex                     = explode(",", $this->getConf("cropas"));
			$Crop                   = new \stdClass();
			$Crop->width            = $ex[0];
			$Crop->height           = $ex[1];
			$Crop->x                = $ex[2];
			$Crop->y                = $ex[3];
			$this->config["cropas"] = $Crop;
		}
	}
	
	/**
	 * Get conf value
	 *
	 * @param string $name
	 * @return false|mixed
	 */
	public function getConf(string $name)
	{
		if (array_key_exists($name, $this->config))
		{
			return $this->config[$name];
		}
		
		return false;
	}
	
	/**
	 * Make image in given configuration
	 *
	 * @throws \ImagickException
	 * @return bool
	 */
	private function draw()
	{
		ini_set('memory_limit', '1024M');
		set_time_limit(999);
		
		if (!$this->Image)
		{
			$this->Image = new AdaptiveImageImagick($this->srcFile);
		}
		
		$this->Image->fixOrentation();
		if ($this->getConf("removeWhiteSpace"))
		{
			$this->Image->trimColor('#FFFFFF');
			$this->Size->calculate($this->Image, $this->getConf('width'), $this->getConf('height'), $this->getConf('fit'));
		}
		if ($this->getConf("blur"))
		{
			$this->Image->blur(99);
		}
		
		$bg = $this->getConf("bg");
		if ($bg == false)
		{
			$bg = "transparent";
		}
		else
		{
			if ($bg{0} != "#" and $bg != "transparent")
			{
				$bg = "#" . $bg;
			}
		}
		$fit = $this->getConf("fit");
		//addExtraErrorInfo("params", $this->params);
		//addExtraErrorInfo("Size", $this->Size);
		if (in_array($fit, ["size", "fill"]) && isset($this->Size->fitWidth) && isset($this->Size->fitHeight))
		{
			if ($fit == "size")
			{
				if ($this->Size->fitWidth > $this->Size->width or $this->Size->fitHeight > $this->Size->height) // does the actual image size is bigger then resized image
				{
					$this->Image->setImageBackgroundColor($bg);
					if ($bg === "transparent")
					{
						$this->Image->setImageFormat("png");
					}
				}
				$this->Image->scaleImage($this->Size->fitWidth, $this->Size->fitHeight, true);
			}
			elseif ($fit == "fill")
			{
				$this->Image->scaleImage($this->Size->width, $this->Size->height, true);
			}
			else
			{
				$this->sendError("fit '$fit' is not implemented");
			}
			if ($this->getConf("fitc"))
			{
				$fitc = rawurldecode($this->getConf("fitc"));
				if (strpos($fitc, ",") !== false)
				{
					$ex = explode(",", $fitc);
				}
				else
				{
					$ex = [$fitc{0}, $fitc{1}];
				}
				
				$xs  = (string)$ex[0];
				$ys  = (string)$ex[1];
				$xsl = false; //does cs contains any letter l,c,etc
				$ysl = false; //does cs contains any letter l,c,etc
				if (strpos($xs, 'l') !== false)
				{
					$x   = 0;
					$xsl = true;
				}
				elseif (strpos($xs, 'c') !== false)
				{
					$x   = ($this->Size->width - $this->Size->fitWidth) / 2;
					$xsl = true;
				}
				else
				{
					$x = $xs;
				}
				$xs = preg_replace('/[a-z]/si', '', $xs);
				if ($xs != "" and $xsl)
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
				if (strpos($ys, "c") !== false)
				{
					$y   = 0;
					$ysl = true;
				}
				elseif (strpos($ys, 'c') !== false or strpos($ys, 'm') !== false) //c = center, m = middle
				{
					$y   = ($this->Size->height - $this->Size->fitHeight) / 2;
					$ysl = true;
				}
				else
				{
					$y = intval($ys);
				}
				$ys = preg_replace('/[a-z]/si', '', $ys);
				if ($ys != "" and $ysl)
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
			$this->Image->scaleImage($this->Size->width, $this->Size->height, true);
		}
		
		if ($this->getConf("cropas"))
		{
			$Crop = $this->getConf("cropas");
			$this->Image->cropImage($Crop->width, $Crop->height, $Crop->x, $Crop->y);
		}
		
		
		$format               = strtolower($this->Image->getImageFormat());
		$this->finalExtension = $format;
		if (in_array($format, ['jpg', 'jpeg', 'webp']))
		{
			$this->Image->setImageCompression(Imagick::COMPRESSION_JPEG);
			$this->Image->setImageCompressionQuality($this->getConf("quality"));
			
			$this->Image->setImageResolution(72, 72);
			$this->Image->setImageUnits(1);
			//$this->Image->setInterlaceScheme(\Imagick::INTERLACE_JPEG);
			//$this->Image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
			$this->Image->setSamplingFactors(['2x2', '1x1', '1x1']); //https://stackoverflow.com/questions/27132047/how-can-i-save-a-jpg-image-in-420-colorspace-with-imagick
			$this->finalExtension = "jpg";
			if ($format == 'webp')
			{
				$this->Image->setImageFormat('jpeg');
			}
			
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
		
		return true;
	}
	
	
	/**
	 * Send image error written on it to browser
	 *
	 * @param string $message
	 * @return mixed
	 */
	private function sendError(string $message)
	{
		/* Create Imagick objects */
		$this->Image = new Imagick();
		$draw        = new \ImagickDraw();
		$color       = new \ImagickPixel('#000000');
		$background  = new \ImagickPixel('#FFFFFF'); // Transparent
		
		/* Font properties */
		$draw->setFont(__DIR__ . "/fonts/Nobile-Regular.ttf");
		$draw->setFontSize(14);
		$draw->setFillColor($color);
		$draw->setStrokeAntialias(true);
		$draw->setTextAntialias(true);
		
		/* Get font metrics */
		$metrics = $this->Image->queryFontMetrics($draw, $message);
		/* Create text */
		$draw->annotation(0, $metrics['ascender'], $message);
		
		/* Create image */
		$this->Image->newImage($metrics['textWidth'], $metrics['textHeight'], $background);
		$this->Image->setImageFormat('png');
		$this->Image->drawImage($draw);
		
		
		return $this->send(true);
	}
	
	private function setFinalPath()
	{
		if ($this->getConf("destFileName") && file_exists($this->getConf("destFileName")))
		{
			$destFileName = $this->getConf("destFileName");
		}
		else
		{
			$extras   = [];
			$extras[] = $this->cachePath;
			if ($this->getConf("bds"))
			{
				$bds = $this->fixDirName($this->getConf("bds"));
				if ($bds)
				{
					$extras[] = $bds;
				}
			}
			
			if ($this->getConf("usd"))
			{
				$usd = strtolower($this->getConf("usd"));
				$a   = "";
				if (strpos($usd, "w") !== false)
				{
					$width = $this->getConf("width");
					if ($width == "auto")
					{
						$width = $this->Size->finalWidth;
					}
					$a = $width;
				}
				if (strpos($usd, "w") !== false and strpos($usd, "h") !== false)
				{
					$a .= "x";
				}
				if (strpos($usd, "h") !== false)
				{
					$height = $this->getConf("height");
					if ($height == "auto")
					{
						$height = $this->Size->finalHeight;
					}
					$a .= $height;
				}
				$extras[] = $a;
			}
			if ($this->getConf("removeWhiteSpace"))
			{
				$extras[] = "rms";
			}
			if ($this->getConf("fit"))
			{
				$extras[] = $this->fixDirName($this->getConf("fit"));
			}
			$destFileName = str_replace("//", "/", join("/", $extras) . "/") . $this->finalFileName;
		}
		
		$info            = (object)(pathinfo($destFileName));
		$this->finalPath = $info->dirname . DIRECTORY_SEPARATOR . $info->filename . ".%extension%";
		$this->finalPath = str_replace('../', '', $this->finalPath); //cant go back in directory
	}
	
	public function getFinalPath()
	{
		$extension = $this->finalExtension;
		
		return str_replace('%extension%', $extension, $this->finalPath);
	}
	
	private function send($sendError = false)
	{
		if ($this->getConf("writeFile") && !file_exists($this->getFinalPath()) and $sendError == false)
		{
			$destPath = dirname($this->getFinalPath());
			
			if (empty($destPath))
			{
				$this->sendError("Destionation path not seted");
			}
			
			// Make the dir if missing.
			if (!is_dir($destPath))
			{
				mkdir($destPath, 0755, true);
			}
			
			// Check if we can write to this dir.
			if (!is_dir($destPath) || !is_writable($destPath))
			{
				return $this->sendError('Failed to create destination directory at: ' . $destPath);
			}
			$this->Image->writeImage($this->getFinalPath());
		}
		
		if ($this->getConf("sendToBrowser") or $sendError)
		{
			if (!$this->getConf("debug"))
			{
				if (ob_get_contents())
				{
					ob_clean();
					ob_end_clean();
				}
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
		if ($this->getConf("debug"))
		{
			exit("ok");
		}
	}
	
	//###########Helpers
	
	/**
	 * Removes all but a-zA-Z0-9 from $dirname
	 *
	 * @param string $name
	 * @return string|string[]|null
	 */
	private function fixDirName(string $name)
	{
		return preg_replace('/[^a-zA-Z0-9]/', '', $name);
	}
}

?>