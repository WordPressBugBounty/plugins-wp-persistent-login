<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit60f62e169e7b1a91d52287677bf231e7
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'WhichBrowser\\' => 13,
        ),
        'P' => 
        array (
            'Psr\\Cache\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'WhichBrowser\\' => 
        array (
            0 => __DIR__ . '/..' . '/whichbrowser/parser/src',
        ),
        'Psr\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/cache/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit60f62e169e7b1a91d52287677bf231e7::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit60f62e169e7b1a91d52287677bf231e7::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit60f62e169e7b1a91d52287677bf231e7::$classMap;

        }, null, ClassLoader::class);
    }
}