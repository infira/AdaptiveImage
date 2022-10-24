<?php

namespace Infira\AdaptiveImage;

use Exception;
use Imagick;
use ImagickDraw;
use ImagickDrawException;
use ImagickException;
use ImagickPixel;
use RuntimeException;
use stdClass;

class AdaptiveImage
{
    private $cachePath = null;   // where to store the generated re-sized images. Specify from your document root!
    private $browserCache = 604800; // How long the BROWSER cache should last (seconds, minutes, hours, days. 7days by default)
    private $config = [];     //User params
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

    public function __construct()
    {
        $this->config['width'] = 'auto';
        $this->config['height'] = 'auto';
        $this->config['quality'] = 80;
        $this->config['forceCache'] = false;//overwrite file if exists
        $this->config['removeWhiteSpace'] = false;
        $this->config['generate'] = true;
        $this->config['fit'] = false; //Final image must be exacty the $width and height width backgrounds
        $this->config['maintainTransparency'] = true;  //In case of source png maintain transparency
        $this->config['upScale'] = true;  //If desired image size is smaller than actual image, then upscale it

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
        $this->config['fitc'] = 'cm';

        $this->config['bg'] = null; //Used only in fit
        $this->config['writeFile'] = true;
        $this->config['sendToBrowser'] = true;
        $this->config['blur'] = false;
        $this->config['bds'] = "";       //base dir suffix
        $this->config['useOnlyBds'] = false;    //base dir suffix
        $this->config['usd'] = 'w';      //Use picture size in dir, w = width, h = height, both together can be used as well
        $this->config['crop'] = false;    //crop image after resizing
        $this->config['transparentImageFormat'] = 'png';
        $this->config['webp'] = false; //use webp
        $this->config['fn'] = '';    //use this as file name
        $this->config['webp:method'] = '6';   //use this as file name
    }


    /**
     * @throws ImagickException
     * @throws Exception
     */
    public function make($src = false, $config = null): void
    {
        $this->setConfig($config);
        if (!is_dir($this->cachePath)) {
            $this->error("Cache '" . $this->cachePath . "' path does not exists");
        }
        if (!$src) {
            $this->error('Source file is not defined');
        }
        $this->srcFile = $this->srcPath . $src;

        // check if the file exists at all
        if (!file_exists($this->srcFile)) {
            $this->error('Source file not found : ' . $this->srcFile);
        }
        if (!is_readable($this->srcFile) || !is_file($this->srcFile)) {
            $this->error('File is not readable || is not and file : ' . $this->srcFile);
        }

        if (strpos(mime_content_type($this->srcFile), 'image') === false) {
            $this->error('File is not image : ' . $this->srcFile);
        }

        $pi = pathinfo($src);
        $this->finalExtension = $pi['extension'];
        $this->finalFileName = $pi['filename'];
        if (strtolower($this->finalExtension) === 'svg') {
            $this->error('SVG image is not supported.');
        }

        /* whew might the cache file be? */
        $this->parseConfig();
        $this->Size = new AdaptiveImageSize($this);
        $this->Image = new AdaptiveImageImagick($this->srcFile);
        $this->Size->calculate($this->Image);
        $this->setFinalPath();

        if (!$this->getConf('generate')) {
            return;
        }

        $finalPath = $this->getFinalPath();
        if (!$this->getConf('writeFile')) {
            $this->draw($finalPath);
        }
        elseif (!file_exists($finalPath)) {
            $this->draw($finalPath);
        }
        elseif ($this->getConf('forceCache')) {
            // modified, clear it
            unlink($finalPath);
            $this->draw($finalPath);
        }

        if ($this->getConf('writeFile')) {
            if (file_exists($finalPath)) {
                if ($this->getConf('sendToBrowser')) {
                    header('Content-type: image/' . $this->Image->getImageFormat());
                    header('Cache-Control: private, max-age=' . $this->browserCache);
                    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $this->browserCache) . ' GMT');
                    echo file_get_contents($finalPath);
                    $this->Image->clear();
                    $this->Image->destroy();
                }
            }
            else {
                $this->error('Image was not created');
            }
        }
        elseif ($this->getConf('sendToBrowser')) {
            header('Content-type: image/' . $this->Image->getImageFormat());
            header('Cache-Control: private, max-age=' . $this->browserCache);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $this->browserCache) . ' GMT');
            echo $this->Image->getImagesBlob();
            $this->Image->clear();
            $this->Image->destroy();
        }
    }

    /**
     * @throws ImagickException
     * @throws ImagickDrawException
     */
    public function setCachePath(string $path): void
    {
        if ($path) {
            if (method_exists($this, 'parseCachePath')) {
                $path = $this->parseCachePath($path);
            }
            if (!is_dir($path)) {
                $this->error("Cache path($path) must be valid path");
            }
            $this->cachePath = $path;
        }
    }

    public function getSrc(): string
    {
        return $this->srcFile;
    }

    public function setSrcPath(string $path): void
    {
        if ($path) {
            if (!is_dir($path)) {
                $this->error('source path must be valid path');
            }
            $this->srcPath = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path . DIRECTORY_SEPARATOR);
        }
    }

    public function setConfig($config, $value = null): void
    {
        if (is_string($config) && $value !== null) {
            $this->config[$config] = $value;

            return;
        }
        if (!$config) {
            return;
        }
        if (is_object($config)) {
            $configArr = (array)$config;
        }
        elseif (is_string($config)) {
            $configArr = [];
            $config = trim($config);

            $config = str_replace(array('&amp;', '\n'), '&', $config);
            parse_str($config, $configArr);
            if (get_magic_quotes_gpc() === 1) {
                $configArr = stripslashes($config);
            }
            if (!is_array($configArr)) {
                $configArr = [];
            }
        }
        else {
            $configArr = $config;
        }
        unset($config);

        if (method_exists($this, 'configParser')) {
            $configArr = $this->configParser($configArr);
        }
        foreach ($configArr as $key => $val) {
            if (strpos($key, 'amp;') === 0) {
                unset($configArr[$key]);
                $configArr[substr($key, 4)] = $val;
            }
        }
        if ($configArr) {
            $this->config = array_merge($this->config, $configArr);
        }
    }

    /**
     * @throws ImagickDrawException
     * @throws ImagickException
     */
    private function parseConfig(): void
    {
        if (array_key_exists('size', $this->config)) {
            $s = explode('x', strtolower($this->config['size']));
            if (!isset($s[1])) {
                $s[1] = 0;
            }
            $this->config['width'] = trim($s[0]);
            $this->config['height'] = trim($s[1]);
            if ((!is_numeric($this->config['width']) && $this->config['width'] !== 'auto') || (!is_numeric($this->config['height']) && $this->config['height'] !== 'auto')) {
                $this->error('size paramater must be $numberx$number');
            }
        }
        elseif (array_key_exists('width', $this->config)) {
            if ((!is_numeric($this->config['width']) && $this->config['width'] !== 'auto')) {
                $this->error('width paramater must be $numberx or auto');
            }
        }
        elseif (array_key_exists('height', $this->config)) {
            if ((!is_numeric($this->config['height']) && $this->config['height'] !== 'auto')) {
                $this->error('height paramater must be $numberx or auto');
            }
        }
        foreach (['width', 'height', 'quality'] as $cf) {
            $v = $this->config[$cf];
            if (is_numeric($v)) {
                if ($v <= 0) {
                    $this->error("$cf($v) should be bigger than 0");
                }
            }
            elseif (in_array($cf, ['height', 'width'])) {
                if ($v !== 'auto') {
                    $this->error("$cf($v) should be number");
                }
            }
            else {
                $this->error("$cf($v) should be number");
            }
        }
        if ($this->config['width'] === 'auto' && $this->config['height'] === 'auto') {
            if (!isset($this->config['size'])) {
                $this->error('size parameter is not defined, or width,heigh');
            }
            else {
                $this->error('width and height cannot be auto at the same time');
            }
        }
        foreach ($this->config as $n => $v) {
            if (in_array($n, ['forceCache', 'generate', 'removeWhiteSpace', 'writeFile', 'sendToBrowser', 'blur', 'hdir', 'webp', 'useOnlyBds', 'upScale'])) {
                if ($v === '1' || $v === 'true' || $v === 1 || $v === true) {
                    $v = true;
                }
                else {
                    $v = false;
                }
            }
            $this->config[$n] = $v;
        }

        $this->config['quality'] = preg_replace('/[^0-9]/', "", $this->config['quality']);

        if ($this->getConf('crop')) {
            $ex = explode(',', $this->getConf('crop'));
            $Crop = new stdClass();
            $Crop->width = $ex[0];
            $Crop->height = $ex[1];
            $Crop->x = $ex[2];
            $Crop->y = $ex[3];
            $this->config['crop'] = $Crop;
        }
    }

    private function getBg(): string
    {
        $bg = $this->getConf('bg');
        if ($this->getConf('maintainTransparency')) //colorize transparent background
        {
            if (!$bg) {
                return 'transparent';
            }
        }
        elseif (!$bg) {
            return 'white';
        }
        if ($bg[0] !== '#' && $bg !== 'transparent') {
            $bg = '#' . $bg;
        }

        return $bg;
    }

    /**
     * @throws ImagickException
     */
    private function replaceTransparentBg(): bool
    {
        $bg = $this->getBg();
        $format = strtolower($this->Image->getImageFormat());

        return (!$this->getConf('maintainTransparency') && $bg !== 'transparent' && !in_array($format, ['jpe', 'jpeg']));
    }

    /**
     * Get conf value
     *
     * @param string $name
     * @return string|array|int|float|null
     */
    public function getConf(string $name)
    {
        return $this->config[$name] ?? null;
    }

    /**
     * Make image in given configuration
     *
     * @throws ImagickException
     * @throws ImagickDrawException
     */
    private function draw(string $finalPath): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(999);
        $this->Image->fixOrentation();
        if ($this->getConf('removeWhiteSpace')) {
            $this->Image->removeWhitespace();
            $this->Size->calculate($this->Image);
        }
        if ($this->getConf('blur')) {
            $this->Image->blur(99);
        }

        $bg = $this->getBg();
        $fit = $this->getConf('fit');
        $format = strtolower($this->Image->getImageFormat());
        if ($this->replaceTransparentBg())//colorize transparent background
        {
            $format = 'jpg';
            $flattened = new Imagick();
            $flattened->newImage($this->Image->getImageWidth(), $this->Image->getImageHeight(), $bg);
            $flattened->compositeImage($this->Image, imagick::COMPOSITE_OVER, 0, 0);
            $flattened->setImageFormat($format);
            $this->Image = $flattened;
        }

        if (isset($this->Size->fitWidth, $this->Size->fitHeight) && in_array($fit, ['size', 'fill'])) {
            if ($fit === 'size') {
                if ($this->Size->fitWidth > $this->Size->width || $this->Size->fitHeight > $this->Size->height) // does the actual image size is bigger then resized image
                {
                    if ($bg === 'transparent') {
                        $this->Image->setImageBackgroundColor(new ImagickPixel('transparent'));
                        $format = $this->getConf('transparentImageFormat');
                        $this->Image->setImageFormat($format);
                    }
                    else {
                        $this->Image->setImageBackgroundColor($bg);
                    }
                }
                //$this->Image->scaleImage($this->Size->fitWidth, $this->Size->fitHeight, true);
                $this->Image->scaleImage($this->Size->width, $this->Size->height, true);
            }
            elseif ($fit === 'fill') {
                $this->Image->scaleImage($this->Size->width, $this->Size->height, true);
            }
            else {
                $this->error("fit '$fit' is not implemented");
            }

            if ($this->getConf('fitc')) {
                $fitc = rawurldecode($this->getConf('fitc'));
                if (strpos($fitc, ',') !== false) {
                    $ex = explode(',', $fitc);
                }
                else {
                    $ex = [$fitc[0], $fitc[1]];
                }

                $xs = (string)$ex[0];
                $ys = (string)$ex[1];
                $xsl = false; //does cs contains any letter l,c,etc
                $ysl = false; //does cs contains any letter l,c,etc
                if (strpos($xs, 'l') !== false) {
                    $x = 0;
                    $xsl = true;
                }
                elseif (strpos($xs, 'c') !== false) {
                    $x = ($this->Size->width - $this->Size->fitWidth) / 2;
                    $xsl = true;
                }
                else {
                    $x = $xs;
                }
                $xs = preg_replace('/[a-z]/si', '', $xs);
                if ($xs !== "" && $xsl) {
                    if ($xs[0] === '-') {
                        $x -= (int)substr($xs, 1);
                    }
                    elseif ($xs[0] === '+') {
                        $x += (int)substr($xs, 1);
                    }
                    else {
                        $x += $xs;
                    }
                }

                if ((string)$x[0] === '-') {
                    $right0 = $this->Size->width - $this->Size->fitWidth;
                    $x = $right0 + (int)$x;
                }
                else {
                    $x = (int)$x;
                }
                //EOF x calculation

                //SOF y calculation
                if (strpos($ys, 'c') !== false) {
                    $y = 0;
                    $ysl = true;
                }
                elseif (strpos($ys, 'c') !== false || strpos($ys, 'm') !== false) //c = center, m = middle
                {
                    $y = ($this->Size->height - $this->Size->fitHeight) / 2;
                    $ysl = true;
                }
                else {
                    $y = (int)$ys;
                }
                $ys = preg_replace('/[a-z]/si', '', $ys);
                if ($ys !== "" && $ysl) {
                    if ($ys[0] === '-') {
                        $y -= (int)substr($ys, 1);
                    }
                    elseif ($ys[0] === '+') {
                        $y += (int)substr($ys, 1);
                    }
                    else {
                        $y += (int)$ys;
                    }
                }

                if ((string)$y[0] === '-') {
                    $bottom0 = $this->Size->height - $this->Size->fitHeight;
                    $y = $bottom0 + (int)$y;
                }
                else {
                    $y = (int)$y;
                }
                //EOF y calculation
                $this->Image->extentImage($this->Size->fitWidth, $this->Size->fitHeight, $x, $y);
            }
            else {
                $this->Image->extentImage($this->Size->fitWidth, $this->Size->fitHeight, ($this->Size->width - $this->Size->fitWidth) / 2, ($this->Size->height - $this->Size->fitHeight) / 2);
            }
        }
        else {
            $this->Image->scaleImage($this->Size->width, $this->Size->height, true);
        }
        if ($this->getConf('crop')) {
            $Crop = $this->getConf('crop');
            $this->Image->cropImage($Crop->width, $Crop->height, $Crop->x, $Crop->y);
        }

        if (in_array($format, ['jpg', 'jpeg', 'webp']) && !$this->getConf('webp')) {
            $this->Image->setImageCompression(Imagick::COMPRESSION_JPEG);
            $this->Image->setImageCompressionQuality($this->getConf('quality'));

            $this->Image->setImageResolution(72, 72);
            $this->Image->setImageUnits(1);
            //$this->Image->setInterlaceScheme(\Imagick::INTERLACE_JPEG);
            //$this->Image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
            $this->Image->setSamplingFactors(['2x2', '1x1', '1x1']); //https://stackoverflow.com/questions/27132047/how-can-i-save-a-jpg-image-in-420-colorspace-with-imagick
            $this->Image->setImageFormat('jpeg');
        }
        elseif ($this->getConf('webp')) {
            //$im = new Imagick();
            //$im->pingImage($src);
            //$im->readImage($src);
            //$im->resizeImage($width,$height,Imagick::FILTER_CATROM , 1,TRUE );
            //$this->Image->setOption('webp:low-memory', 'true');
            $this->Image->setImageFormat('webp');
            $this->Image->setImageCompressionQuality($this->getConf('quality'));
            $this->Image->setOption('webp:method', '6');
            $this->Image->setOption('webp:lossless', 'false');
        }
        elseif ($format === 'png') {
            $this->Image->setFormat('png'); //rewrite format for transparency, etc
        }
        //$this->Image->setImageBackgroundColor(new ImagickPixel("red"));
        $this->Image->stripImage();
        if ($this->getConf('writeFile')) {
            $destPath = dirname($finalPath);
            if (empty($destPath)) {
                $this->error('Destionation path not seted');
            }

            // Make the dir if missing.
            if (!is_dir($destPath) && !mkdir($destPath, 0755, true) && !is_dir($destPath)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $destPath));
            }
            if (!is_dir($destPath) || !is_writable($destPath)) {
                $this->error('Failed to create destination path at: ' . $destPath);
            }


            $this->Image->writeImage($finalPath);
        }
    }

    /**
     * @throws ImagickException
     */
    private function setFinalPath(): void
    {
        $bg = $this->getBg();
        $fit = $this->getConf('fit');
        if (isset($this->Size->fitWidth, $this->Size->fitHeight) && $fit === 'size' && $bg === 'transparent') {
            if ($this->Size->fitWidth > $this->Size->width || $this->Size->fitHeight > $this->Size->height) {
                $this->Image->setImageFormat($this->getConf('transparentImageFormat'));
            }
        }

        $format = strtolower($this->Image->getImageFormat());
        if (!$this->replaceTransparentBg()) //colorize transparent background
        {
            $format = 'jpg';
        }
        $this->finalExtension = $format;
        if (in_array($format, ['jpg', 'jpeg', 'webp']) && !$this->getConf('webp')) {
            $this->finalExtension = 'jpg';
        }
        elseif ($this->getConf('webp')) {
            $this->finalExtension = 'webp';
        }
        elseif ($format === 'png') {
            $this->finalExtension = 'png';
        }

        $extras = [];
        $extras[] = $this->cachePath;
        if ($this->getConf('bds')) {
            $bds = $this->fixDirName($this->getConf('bds'));
            if ($bds) {
                $extras[] = $bds;
            }
        }
        if ($this->getConf('useOnlyBds') === false) {
            if ($this->getConf('usd')) {
                $usd = strtolower($this->getConf('usd'));
                $a = "";
                if (strpos($usd, 'w') !== false) {
                    $width = $this->getConf('width');
                    if ($width === 'auto') {
                        $width = $this->Size->pathWidth;
                    }
                    $a = $width;
                }
                if (strpos($usd, 'w') !== false && strpos($usd, 'h') !== false) {
                    $a .= 'x';
                }
                if (strpos($usd, 'h') !== false) {
                    $height = $this->getConf('height');
                    if ($height === 'auto') {
                        $height = $this->Size->pathHeight;
                    }
                    $a .= $height;
                }
                $extras[] = $a;
            }
            if ($this->getConf('removeWhiteSpace')) {
                $extras[] = 'rms';
            }
            if ($this->getConf('fit')) {
                $extras[] = $this->fixDirName($this->getConf('fit'));
            }
        }
        $fn = $this->finalFileName;
        if ($this->getConf('fn')) {
            $fn = $this->getConf('fn');
        }
        $destFileName = str_replace('//', '/', implode('/', $extras) . '/') . $fn;

        $info = (object)(pathinfo($destFileName));
        $this->finalPath = $info->dirname . DIRECTORY_SEPARATOR . $info->filename . '.%extension%';
        $this->finalPath = str_replace('../', '', $this->finalPath); //cant go back in directory
    }

    public function getFinalPath()
    {
        $extension = $this->finalExtension;

        return str_replace('%extension%', $extension, $this->finalPath);
    }

    public function getFinalExtension()
    {
        return $this->finalExtension;
    }

    /**
     * @throws ImagickDrawException
     * @throws ImagickException
     */
    private function error(string $message): void
    {
        if (!$this->getConf('sendToBrowser')) {
            throw new RuntimeException($message);
        }
        /* Create Imagick objects */
        $this->Image = new Imagick();
        $draw = new ImagickDraw();
        $color = new ImagickPixel('#000000');
        $background = new ImagickPixel('#FFFFFF'); // Transparent

        /* Font properties */
        $draw->setFont(__DIR__ . '/fonts/Nobile-Regular.ttf');
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

        if (ob_get_contents()) {
            ob_clean();
            ob_end_clean();
        }
        header('Content-Type: image/' . $this->Image->getImageFormat());
        header('Cache-Control: private, max-age=' . $this->browserCache);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $this->browserCache) . ' GMT');
        echo $this->Image->getImagesBlob();
        exit;
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