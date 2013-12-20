<?php
/**
 * @package com.yiiframework.extensions.easyimage
 * @author Artur Zhdanov <zhdanovartur@gmail.com>
 * @author Michael De Soto <michael@de-soto.net>
 * @copyright 2013 Artur Zhdanov
 * @copyright 2013-2014 Michael De Soto
 * @license http://www.opensource.org/licenses/bsd-license.php Revised BSD License
 * @version 1.1.0
 */

Yii::setPathOfAlias('easyimage', __DIR__);
Yii::import('easyimage.vendor.kohana.image.classes.*');
Yii::import('easyimage.vendor.kohana.backport.*');

class EEasyImage extends CApplicationComponent
{
    /** @var EEasyImageFile Various metadata about the image */
//    protected $metadata;

    /** @var Image Instance of Image. */
    protected $instance;

    /** @var string Driver type: GD, Imagick. */
    protected $driver = 'GD';

    protected $fileMode = 0750;

    /** @var string Path relative to the web root. This is where the cached files are kept. */
    protected $relativeCachePath = '/assets/easyimage/';

    /**
     * @var int Cache lifetime in seconds. Default is 30 days.
     */
    protected $timeout = 2592000;

    /**
     * @var int Value of JPG quality, if JPG is the generated image type.
     */
    protected $quality = 100;


    /** @var int Position in the DOM of the Retina.js script. */
    protected $retinaClientScriptPosition = CClientScript::POS_HEAD;


    /**
     * @var bool Use retina-resolutions.
     *
     * This setting increases the load on the server.
     *
     * TODO: Look at this.
     */
    public $retinaSupport = false;

    public $retinaSupportPath;

    /**
     * Constructor.
     *
     * @param string $image
     * @param string $driver
     */
    public function __construct($image = null, $driver = null)
    {
        // The SYSPATH constant must be defined for us to load the Kohana image libraries.
        define('SYSPATH', Yii::getFrameworkPath());

        if ($image != null)
        {
            return $this->instance = Image::factory($this->getFilePath($image), $driver ? $driver : $this->driver);
        }
    }

    /**
     * Gets an instance of the Image class.
     *
     * @return Image|object
     * @throws CException
     */
    protected function getInstance()
    {
        if ($this->instance instanceof Image)
        {
            return $this->instance;
        }

        throw new CException('Must have a valid image to manipulate.');
    }

    /**
     * Initialize EasyImage extension. The only thing we do here is to include the Retina JavaScript library
     * (http://retina.js) if desired.
     *
     * @return void
     */
    public function init()
    {
        parent::init();

        if ($this->retinaSupport)
        {
            if (empty($this->retinaSupportPath))
            {
                $this->retinaSupportPath = Yii::getPathOfAlias('easyimage.vendor.retina.src') . '/retina.js';
            }

            Yii::app()->clientScript->registerScriptFile(
                Yii::app()->assetManager->publish($this->retinaSupportPath),
                $this->retinaClientScriptPosition
            );
        }
    }

    /**
     * Convert object to binary data of current image.
     * Must be rendered with the appropriate Content-Type header or it will not be displayed correctly.
     * @return string as binary
     */
    public function __toString()
    {
        try
        {
            return $this->getInstance()->render();
        }
        catch (CException $exception)
        {
            return '';
        }
    }

    /**
     * Return the URL of a given image. Create the cached image if it doesn't already exist.
     *
     * @param string $image
     * @param array $options
     * @return string
     */
    public function generateThumbnailUrl($image, $options = array())
    {
        $metadata = new EEasyImageFile($image, $this->$relativeCachePath, $options);

        // Return the URL when we've previously generated an image and that image hasn't expired.
        if (file_exists($metadata->getAbsoluteFilePath()) && (time() - filemtime($metadata->getAbsoluteFilePath()) < $this->timeout))
        {
            return $metadata->getAbsoluteUrl();
        }

        // Create the cache directory when it doesn't exist.
        if (!is_dir($metadata->getAbsolutePath()))
        {
            mkdir($metadata->getAbsolutePath(), $this->fileMode, true);
        }

        // TODO: Not sure we want to deviate from upstream. We save this to the instance variable where they do not.
        $this->instance = Image::factory($this->getFilePath($image), $this->driver);


        $this->getThumbnail($metadata);


        // TODO: Address retina support.
//        // Same for high-resolution image
//        if ($this->retinaSupport && $result) {
//            if ($this->image()->width * 2 <= $originWidth && $this->image()->height * 2 <= $originHeight) {
//                $retinaFile = $cachePath . DIRECTORY_SEPARATOR . $hash . '@2x.' . $cacheFileExt;
//                if (isset($params['resize']['width']) && isset($params['resize']['height'])) {
//                    $params['resize']['width'] = $this->image()->width * 2;
//                    $params['resize']['height'] = $this->image()->height * 2;
//                }
//                $this->_doThumbOf($file, $retinaFile, $params);
//            }
//        }

        return $metadata->getAbsoluteUrl();
    }

    /**
     * Return Yii generated image tag. Create the cached image if it doesn't already exist.
     *
     * @param string $image
     * @param array $options
     * @param array $htmlOptions
     * @return string
     */
    public function generateThumbnailTag($image, $options = array(), $htmlOptions = array())
    {
        return CHtml::image(
            $this->generateThumbnailUrl($image, $options),
            isset($htmlOptions['alt']) ? $htmlOptions['alt'] : '',
            $htmlOptions
        );
    }

    /**
     * Return the path to the image. Start by prepending the web root to the image path. If we find a file there, let's
     * use it. Otherwise we must assume that the calling class knows where the file is.
     *
     * TODO: It might make sense to do some file checks here regardless of whether the image is in the web root or not.
     *
     * @param array $image
     * @return string
     */
    protected function getFilePath($image)
    {
        $path = Yii::getpathOfAlias('webroot') . '/' . $image;
        if (is_file($path))
        {
            return $path;
        }

        return $image;
    }

    /**
     * @param EEasyImageFile $metadata
     * @return bool
     * @throws CException
     */
    protected function getThumbnail($metadata)
    {
        $this->instance = Image::factory($this->getFilePath($metadata->getImage()), $this->driver);

        foreach ($metadata as $key => $value)
        {
            switch($key)
            {
                case 'background':

                    if (is_array($value))
                    {
                        if (!isset($value['color']))
                        {
                            throw new CException('Action: Background; A color is required when attempting to alter the background.');
                        }

                        $this->background($value['color'], isset($value['opacity']) ? $value['opacity'] : 100);
                    }
                    else
                    {
                        $this->background($value);
                    }

                    break;

                case 'crop':

                    if (!isset($value['width']) || !isset($value['height']))
                    {
                        throw new CException('Action: Flip; Both length and width are required when attempting to crop an image.');
                    }

                    // TODO: Note the underscore in the offset keys? I'm not a fan. But to keep
                    // TODO: this API compatible. I must sacrifice some aesthetics for utility.
                    $this->crop(
                        $value['width'],
                        $value['height'],
                        isset($value['offset_x']) ? $value['offset_x'] : null,
                        isset($value['offset_y']) ? $value['offset_y'] : null
                    );

                    break;

                case 'flip':

                    if (is_array($value))
                    {
                        if (!isset($value['direction']))
                        {
                            throw new CException('Action: Flip; You must specify a direction you would like this image to be flipped.');
                        }

                        $this->flip($value['direction']);
                    }
                    else
                    {
                        $this->flip($value);
                    }

                    break;

                case 'quality':

                    if (!isset($value))
                    {
                        throw new CException('Action: Quality; Must have a value.');
                    }

                    $this->quality = $value;

                    break;

                case 'reflection':

                    // TODO: Note the underscore in the keys? I'm not a fan. But to keep this
                    // TODO: API compatible. I must sacrifice some aesthetics for utility.
                    $this->reflection(
                        isset($value['height']) ? $value['height'] : null,
                        isset($value['opacity']) ? $value['opacity'] : 100,
                        isset($value['fade_in']) ? $value['fade_in'] : false
                    );

                    break;

                case 'resize':

                    $this->resize(
                        isset($value['width']) ? $value['width'] : null,
                        isset($value['height']) ? $value['height'] : null,
                        isset($value['master']) ? $value['master'] : null
                    );

                    break;

                case 'rotate':

                    if (is_array($value))
                    {
                        if (!isset($value['degrees']))
                        {
                            throw new CException('Action: Rotate; You must specify the number of degrees the image should rotate.');
                        }

                        $this->rotate($value['degrees']);
                    }
                    else
                    {
                        $this->rotate($value);
                    }

                    break;

                case 'sharpen':

                    if (is_array($value))
                    {
                        if (!isset($value['amount']))
                        {
                            throw new CException('Action: Sharpen; You must specify an amount you would like this image to be sharpened.');
                        }

                        $this->sharpen($value['amount']);
                    }
                    else
                    {
                        $this->sharpen($value);
                    }

                    break;

                case 'type':

                    break;

                case 'watermark':

                    if (is_array($value))
                    {
                        // TODO: Note the underscore in the offset keys? I'm not a fan. But to keep
                        // TODO: this API compatible. I must sacrifice some aesthetics for utility.
                        $this->watermark(
                            isset($value['watermark']) ? $value['watermark'] : null,
                            isset($value['offset_x']) ? $value['offset_x'] : null,
                            isset($value['offset_y']) ? $value['offset_y'] : null,
                            isset($value['opacity']) ? $value['opacity'] : 100
                        );
                    }
                    else
                    {
                        $this->watermark($value);
                    }

                    break;

                default:

                    throw new CException('Action: ' . ucfirst($key) . '; Action not found.');

            }
        }

        return $this->getInstance()->save($metadata->getAbsoluteFilePath(), $this->quality);
    }

    /**
     * Return Yii generated image tag whether the cached image is generated or not.
     *
     * @param string $image
     * @param array $options
     * @param array $htmlOptions
     * @return string
     */
    public function getThumbnailTag($image, $options = array(), $htmlOptions = array())
    {
        return CHtml::image(
            $this->getThumbnailUrl($image, $options),
            isset($htmlOptions['alt']) ? $htmlOptions['alt'] : '',
            $htmlOptions
        );
    }

    /**
     * Return the URL of a given image whether the cached image is generated or not.
     *
     * @param string $image
     * @param array $options
     * @return string
     */
    public function getThumbnailUrl($image, $options = array())
    {
        $metadata = new EEasyImageFile($image, $this->relativeCachePath, $options);
        return $metadata->getRelativeUrl();
    }

    public function background($color, $opacity = 100)
    {
        return $this->getInstance()->background($color, $opacity);
    }

    public function crop($width, $height, $offset_x = NULL, $offset_y = NULL)
    {
        return $this->getInstance()->crop($width, $height, $offset_x, $offset_y);
    }

    public function flip($direction)
    {
        return $this->getInstance()->flip($direction);
    }

    public function reflection($height = NULL, $opacity = 100, $fade_in = FALSE)
    {
        return $this->getInstance()->reflection($height, $opacity, $fade_in);
    }
    /**
     * Render the image and return the binary string.
     *
     * @param null $type The image type to be rendered.
     * @param int $quality Image quality. Used by JPG images only.
     * @return string
     */
    public function render($type = null, $quality = 100)
    {
        return $this->getInstance()->render($type, $quality);
    }

    public function resize($width = NULL, $height = NULL, $master = NULL)
    {
        return $this->resize()->resize($width, $height, $master);
    }

    public function rotate($degrees)
    {
        return $this->getInstance()->rotate($degrees);
    }

    /**
     * @param string $filename New image path.
     * @param int $quality Image quality. Used by JPG images only.
     * @return bool
     */
    public function save($filename = null, $quality = 100)
    {
        return $this->getInstance()->save($filename, $quality);
    }

    public function sharpen($amount)
    {
        return $this->getInstance()->sharpen($amount);
    }


    /**
     * Add a watermark to an image at a given opacity. Alpha transparency will be preserved. If no offset is specified,
     * the center of the axis will be used. If an offset of true is specified, the bottom of the axis will be used.
     *
     * @param EasyImage|string $watermark Watermark EasyImage Instance.
     * @param null $offsetX Offset from the left.
     * @param null $offsetY Offset from the right.
     * @param int $opacity Opacity of the watermark. Valid values are between 1 and 100.
     * @return $this
     */
    public function watermark($watermark, $offsetX = null, $offsetY = null, $opacity = 100)
    {
        if ($watermark instanceof EasyImage)
        {
            $watermark = $watermark->getInstance();
        }
        elseif (is_string($watermark))
        {
            // TODO: Should we assume webroot here?
            $watermark = Image::factory(Yii::getpathOfAlias('webroot') . '/' . $watermark);
        }

        return $this->getInstance()->watermark($watermark, $offsetX, $offsetY, $opacity);
    }

    /**
     * Return Yii generated image tag. Create the cached image if it doesn't already exist.
     *
     * @deprecated
     * @param string $image
     * @param array $options
     * @param array $htmlOptions
     * @return string
     */
    public function thumbOf($image, $options = array(), $htmlOptions = array())
    {
        return $this->generateThumbnailTag($image, $options, $htmlOptions);
    }

    /**
     * Return the URL of a given image. Generate the cached image if it doesn't already exist.
     *
     * @deprecated
     * @param string $image
     * @param array $options
     * @return string
     */
    public function thumbSrcOf($image, $options = array())
    {
        return $this->generateThumbnailUrl($image, $options);
    }
}