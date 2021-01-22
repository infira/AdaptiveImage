<?php

namespace Infira\AdaptiveImage;

class AdaptiveImageSize
{
	public $width  = 'auto';
	public $height = 'auto';
	public $srcWidth;
	public $srcHeight;
	public $pathWidth;
	public $pathHeight;
	public $fitWidth;
	public $fitHeight;
	
	/**
	 * @var \Infira\AdaptiveImage\AdaptiveImage
	 */
	private $Ai;
	
	public function __construct(&$Ai)
	{
		$this->Ai = &$Ai;
	}
	
	public function calculate(\Imagick $file)
	{
		$desiredWidth  = $this->Ai->getConf('width');
		$desiredHeight = $this->Ai->getConf('height');
		$fit           = $this->Ai->getConf('fit');
		
		$this->width     = "auto";
		$this->height    = "auto";
		$size            = $file->getImageGeometry();
		$this->srcWidth  = $size["width"];
		$this->srcHeight = $size["height"];
		$this->fitWidth  = $this->fitHeight = null;
		$upScale         = $this->Ai->getConf('upScale');
		
		//cleanOutput(true);
		//debug(['desired' => [$desiredWidth, $desiredHeight]]);
		if ($desiredWidth != "auto" && $desiredHeight == "auto")
		{
			if ($desiredWidth > $this->srcWidth and !$upScale and !$fit)
			{
				$desiredWidth = $this->srcWidth;
			}
			$calc         = $this->calcDimensions("W", $this->srcWidth, $this->srcHeight, $desiredWidth, $desiredHeight);
			$this->width  = $calc->width;
			$this->height = $calc->height;
		}
		elseif ($desiredWidth == "auto" && $desiredHeight != "auto")
		{
			if ($desiredHeight > $this->srcHeight and !$upScale and !$fit)
			{
				$desiredHeight = $this->srcHeight;
			}
			$calc         = $this->calcDimensions("H", $this->srcWidth, $this->srcHeight, $desiredWidth, $desiredHeight);
			$this->width  = $calc->width;
			$this->height = $calc->height;
		}
		else //width = n, height = n
		{
			if (!$upScale && !$fit)
			{
				if ($desiredHeight > $this->srcHeight)
				{
					$desiredHeight = $this->srcHeight;
				}
				if ($desiredWidth > $this->srcWidth)
				{
					$desiredWidth = $this->srcWidth;
				}
			}
			$this->width  = $desiredWidth;
			$this->height = $desiredHeight;
		}
		
		//$this->debug("beforeCalc");
		if ($fit)
		{
			$this->pathWidth  = $this->fitWidth = $this->width;
			$this->pathHeight = $this->fitHeight = $this->height;
			
			if (!$upScale and $this->width > $this->srcWidth and $this->height > $this->srcHeight)
			{
				//debug(1);
				$this->width  = $this->srcWidth;
				$this->height = $this->srcHeight;
			}
			elseif (!$upScale and ($this->width > $this->srcWidth || $this->height > $this->srcHeight))
			{
				if ($this->srcWidth > $this->width)
				{
					//debug(2.1);
					$calc = $this->calcDimensions("W", $this->srcWidth, $this->srcHeight, $this->width, "auto");
				}
				else
				{
					//debug(2.2);
					$calc = $this->calcDimensions("H", $this->srcWidth, $this->srcHeight, "auto", $this->height);
				}
				$this->width  = $calc->width;
				$this->height = $calc->height;
			}
			else
			{
				//debug(3);
				$calc = $this->calcDimensions("W", $this->srcWidth, $this->srcHeight, $this->fitWidth, $this->fitHeight);
				//debug(['$calc' => $calc]);
				if ($calc->width >= $this->fitWidth && $calc->height >= $this->fitHeight and $fit == "fill")
				{
					//debug("1");
					$this->width  = $calc->width;
					$this->height = $calc->height;
				}
				elseif ($calc->width <= $this->fitWidth && $calc->height <= $this->fitHeight and $fit == "size")
				{
					//debug("2");
					$this->width  = $calc->width;
					$this->height = $calc->height;
				}
				else
				{
					//debug("3");
					$calc         = $this->calcDimensions("H", $this->srcWidth, $this->srcHeight, $this->fitWidth, $this->fitHeight);
					$this->width  = $calc->width;
					$this->height = $calc->height;
				}
			}
			
		}
		else
		{
			$this->pathWidth  = $this->width;
			$this->pathHeight = $this->height;
		}
	}
	
	private function calcDimensions($propFlag, $srcWidth, $srcHeight, $newWidth = "auto", $newHeight = "auto")
	{
		$Output   = new \stdClass();
		$propFlag = strtoupper($propFlag);
		if ($propFlag == 'H')
		{
			/**
			 * Calculate the new size to be proportional Vertical.
			 */
			$Output->height = $newHeight;
			$Output->width  = round($newHeight * ($srcWidth / $srcHeight)); // photoshop way
		}
		elseif ($propFlag == 'W')
		{
			/**
			 * Calculate the new size to be proporcional V.
			 */
			$Output->width  = $newWidth;
			$Output->height = round($newWidth * ($srcHeight / $srcWidth)); // photoshop way
		}
		elseif ($propFlag == 'AUTO')
		{
			if ($srcWidth > $srcHeight)
			{
				return $this->calcDimensions("W", $srcWidth, $srcHeight, $newWidth, $newHeight);
			}
			else
			{
				return $this->calcDimensions("H", $srcWidth, $srcHeight, $newWidth, $newHeight);
			}
		}
		
		return $Output;
	}
	
	public function debug($debugName = 'sizeDbug')
	{
		$debug            = new \stdClass();
		$debug->width     = $this->width;
		$debug->height    = $this->height;
		$debug->srcWidth  = $this->srcWidth;
		$debug->srcHeight = $this->srcHeight;
		//$Debug->pathWidth  = $this->pathWidth;
		//$Debug->pathHeight = $this->pathHeight;
		$debug->fitWidth  = $this->fitWidth;
		$debug->fitHeight = $this->fitHeight;
		if ($debugName)
		{
			debug([$debugName => $debug]);
		}
		else
		{
			debug($debug);
		}
	}
}

?>