<?php

namespace Infira\AdaptiveImage;

use Imagick;

class AdaptiveImageImagick extends Imagick
{
	public function __construct($files = null)
	{
		parent::__construct($files);
	}
	
	/**
	 * Sometimes images came our from camera with wrong orentation
	 * this method fixes it
	 */
	public function fixOrentation()
	{
		$orientation = $this->getImageOrientation();
		switch ($orientation)
		{
			case self::ORIENTATION_BOTTOMRIGHT:
				$this->rotateimage("#000", 180); // rotate 180 degrees
			break;
			
			case self::ORIENTATION_RIGHTTOP:
				$this->rotateimage("#000", 90); // rotate 90 degrees CW
			break;
			
			case self::ORIENTATION_LEFTBOTTOM:
				$this->rotateimage("#000", -90); // rotate 90 degrees CCW
			break;
		}
	}
	
	/**
	 * Trims color ro nearest NOT $colorToTrim
	 *
	 * @param string $colorToTrim
	 */
	public function trimColor($colorToTrim = "#FFFFFF")
	{
		// Set the color to trim:
		$this->borderImage($colorToTrim, 1, 1);
		$this->trimImage(0.1 * $this->getQuantumRange()["quantumRangeLong"]);
		$this->setImagePage(0, 0, 0, 0);
	}
	
	public function blur($scale)
	{
		$this->Image->blurImage($scale, $scale);
	}
	
	/**
	 * @param int $opacity - 0 - 100
	 * @return bool
	 */
	public function opacity(int $opacity)
	{
		if ($opacity > 1 || $opacity < 0)
		{
			return false;
		}
		$this->evaluateImage(self::EVALUATE_MULTIPLY, $opacity, self::CHANNEL_ALPHA);
		
		return true;
	}
	
	/*
	 * NOT IMPLEMENTED CORRECTLR, :TODO
	
	protected function setCenterWaterMark()
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
		
		$this->compositeImage($watermark, \Imagick::COMPOSITE_DISSOLVE, $destX, $destY);
	}
	
	protected function setCoverWatermark()
	{
		if ($this->getConf("coverWaterMarks"))
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
	*/
}

?>