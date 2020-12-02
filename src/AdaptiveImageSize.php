<?php

namespace Infira\AdaptiveImage;

class AdaptiveImageSize
{
	public $width  = 'auto';
	public $height = 'auto';
	public $srcWidth;
	public $srcHeight;
	public $finalWidth;
	public $finalHeight;
	public $fitWidth;
	public $fitHeight;
	
	public function calculate(\Imagick $file, $desiredWidth, $desiredHeight, $fit)
	{
		$this->width     = "auto";
		$this->height    = "auto";
		$size            = $file->getImageGeometry();
		$this->srcWidth  = $size["width"];
		$this->srcHeight = $size["height"];
		
		if ($desiredWidth != "auto" && $desiredHeight == "auto")
		{
			$calc         = $this->calcDimensions("W", $this->srcWidth, $this->srcHeight, $desiredWidth, $desiredHeight);
			$this->width  = $calc->width;
			$this->height = $calc->height;
			//debug("s1", $this);
		}
		elseif ($desiredWidth == "auto" && $desiredHeight != "auto")
		{
			//debug("s2", $this);
			$calc         = $this->calcDimensions("H", $this->srcWidth, $this->srcHeight, $desiredWidth, $desiredHeight);
			$this->width  = $calc->width;
			$this->height = $calc->height;
		}
		else //width = n, height = n
		{
			$this->width  = $desiredWidth;
			$this->height = $desiredHeight;
		}
		$this->finalWidth  = $desiredWidth;
		$this->finalHeight = $desiredHeight;
		
		//debug("s3", $this);
		if ($fit)
		{
			$this->fitWidth    = $this->width;
			$this->fitHeight   = $this->height;
			$this->finalWidth  = $this->fitWidth;
			$this->finalHeight = $this->fitHeight;
			
			$calc = $this->calcDimensions("W", $this->srcWidth, $this->srcHeight, $this->fitWidth, $this->fitHeight);
			if ($fit == "fill")
			{
				if ($calc->width >= $this->fitWidth && $calc->height >= $this->fitHeight)
				{
					//debug("s4", $this);
					$this->width  = $calc->width;
					$this->height = $calc->height;
				}
				else
				{
					//debug("s4", $this);
					$calc         = $this->calcDimensions("H", $this->srcWidth, $this->srcHeight, $this->fitWidth, $this->fitHeight);
					$this->width  = $calc->width;
					$this->height = $calc->height;
				}
			}
			else //fit = size
			{
				if ($calc->width <= $this->fitWidth && $calc->height <= $this->fitHeight)
				{
					//debug("s5", $this);
					$this->width  = $calc->width;
					$this->height = $calc->height;
				}
				else
				{
					//debug("s6", $this);
					$calc         = $this->calcDimensions("H", $this->srcWidth, $this->srcHeight, $this->fitWidth, $this->fitHeight);
					$this->width  = $calc->width;
					$this->height = $calc->height;
				}
			}
		}
	}
	
	private function calcDimensions($propFlag, $srcWidth, $srcHeight, $newWidth, $newHeight)
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
				return $this->calcDimensions("W", $srcWidth, $srcHeight, $newWidth, $newHeight);
			}
			else
			{
				return $this->calcDimensions("H", $srcWidth, $srcHeight, $newWidth, $newHeight);
			}
		}
		
		return $Output;
	}
}

?>