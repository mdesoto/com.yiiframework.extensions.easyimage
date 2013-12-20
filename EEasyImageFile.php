<?php

class EEasyImageFile
{
    /** @var string */
    protected $absoluteFilePath;

    /** @var string */
    protected $absolutePath;

    /** @var string */
    protected $absoluteUrl;

    /** @var string */
    protected $baseDirectory;

    /** @var string */
    protected $cacheKey;

    /** @var string */
    protected $extension;

    /** @var string */
    protected $filename;

    /** @var string */
    protected $image;

    /** @var array Externally provided metadata. */
    protected $metadata = array();

    /** @var string */
    protected $relativePath;

    /** @var string */
    protected $relativeUrl;

    /**
     * Constructor
     *
     * @param string $image The image
     * @param string $baseDirectory The path to the directory containing the cached image.
     * @param array $metadata Arbitrary data used by the calling class we use to generate cache keys.
     * @throws CException
     */
    public function __construct($image, $baseDirectory, $metadata = array())
    {
        $this->$baseDirectory = rtrim($baseDirectory, '/');
        $this->image = $image;
        $this->metadata = $metadata;

        if (!file_exists($this->getAbsoluteFilePath()))
        {
            // TODO: Fill out exception text.
            throw new CException('');
        }
    }

    public function getAbsoluteFilePath()
    {
        if (!empty($this->absoluteFilePath))
        {
            return $this->absoluteFilePath;
        }

        return $this->absoluteFilePath = rtrim($this->getAbsolutePath(), '/') . '/' . $this->getFilename();
    }

    public function getAbsolutePath()
    {
        if (!empty($this->absolutePath))
        {
            return $this->absolutePath;
        }

        return $this->absolutePath = rtrim(Yii::getpathOfAlias('webroot'), '/') . '/' . $this->getRelativePath();
    }

    public function getAbsoluteUrl()
    {
        if (!empty($this->absoluteUrl))
        {
            return $this->absoluteUrl;
        }

        return $this->absoluteUrl = rtrim(Yii::app()->getBaseUrl(), '/') . '/' . $this->getRelativeUrl();
    }

    /**
     * Return a generated MD5 hash of the filename and any additional metadata we have. We include the last file write
     * time so that we may properly invalidate the cache in cases when a new image is saved over an old one.
     *
     * @return string
     */
    public function getCacheKey()
    {
        if (!empty($this->cacheKey))
        {
            return $this->cacheKey;
        }

        return $this->cacheKey = md5($this->image . serialize($this->metadata + array('filemtime' => filemtime($this->image))));
    }

    public function getExtension()
    {
        if (!empty($this->extension))
        {
            return $this->extension;
        }

        return $this->extension = isset($this->metadata['type']) ? $this->metadata['type'] : pathinfo($this->image, PATHINFO_EXTENSION);
    }

    public function getFilename()
    {
        if (!empty($this->filename))
        {
            return $this->filename;
        }

        return $this->filename = $this->getCacheKey() . '.' . $this->getExtension();
    }

    public function getImage()
    {
        return $this->image;
    }

    /**
     * Returns arbitrary data provided when this class was created. The data is returned in exactly the same form as it
     * was sent.
     *
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    public function getRelativePath()
    {
        if (!empty($this->relativePath))
        {
            return $this->relativePath;
        }

        $key = $this->getCacheKey();
        return $this->relativePath = sprintf('%s/%s/%s', $this->$baseDirectory, $key{32}, $key{31});
    }

    public function getRelativeUrl()
    {
        if (!empty($this->relativeUrl))
        {
            return $this->relativeUrl;
        }

        return $this->relativeUrl = rtrim($this->getRelativePath(), '/') . '/' . $this->getFilename();
    }
}