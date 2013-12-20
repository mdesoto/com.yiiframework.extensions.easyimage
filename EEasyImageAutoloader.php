<?php

class EEasyImageAutoloader
{
    /** @var array Class name prefixes */
    public static $prefixes = array(
        'Image',
        'Kohana'
    );

    /** @var string Base directory where classes needed to be auto-loaded are located. */
    public static $basePath = null;

    /**
     * @param $className
     * @return bool
     */
    static function loadClass($className)
    {
        foreach(static::$prefixes as $prefix)
        {
            if (strpos($className, $prefix.'_') !== false)
            {
                if (!static::$basePath)
                {
                    static::$basePath = Yii::getPathOfAlias('kohanaimage') . '/classes/';
                }

                include static::$basePath . str_replace('_', '/', $className) . '.php';
                return class_exists($className, false) || interface_exists($className, false);
            }
        }

        return false;
    }
}