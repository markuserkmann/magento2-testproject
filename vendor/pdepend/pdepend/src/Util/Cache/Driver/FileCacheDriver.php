<?php

/**
 * This file is part of PDepend.
 *
 * PHP Version 5
 *
 * Copyright (c) 2008-2017 Manuel Pichler <mapi@pdepend.org>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Manuel Pichler nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @copyright 2008-2017 Manuel Pichler. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @since 0.10.0
 */

namespace PDepend\Util\Cache\Driver;

use PDepend\Util\Cache\CacheDriver;
use PDepend\Util\Cache\Driver\File\FileCacheDirectory;
use PDepend\Util\Cache\Driver\File\FileCacheGarbageCollector;
use RuntimeException;

/**
 * A file system based cache implementation.
 *
 * This class implements the {@link CacheDriver} interface
 * based on the local file system. It creates a special directory structure and
 * stores all cache entries in files under this directory structure.
 *
 * @copyright 2008-2017 Manuel Pichler. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @since 0.10.0
 */
class FileCacheDriver implements CacheDriver
{
    public const DEFAULT_TTL = 2592000; // 30 days

    /** Default cache entry type. */
    private const ENTRY_TYPE = 'cache';

    /** The cache directory handler */
    protected FileCacheDirectory $directory;

    /** The current cache entry type. */
    protected string $type = self::ENTRY_TYPE;

    /** Major and minor version of the currently used PHP. */
    protected string $version;

    /**
     * This method constructs a new file cache instance for the given root
     * directory.
     *
     * @param string $root The cache root directory.
     * @param int $ttl The cache TTL.
     * @param string|null $cacheKey Unique key for this cache instance.
     * @throws RuntimeException
     */
    public function __construct(
        string $root,
        int $ttl = self::DEFAULT_TTL,
        private readonly ?string $cacheKey = null
    ) {
        $this->directory = new FileCacheDirectory($root);
        $this->version = preg_replace('(^(\d+\.\d+).*)', '\\1', PHP_VERSION) ?? PHP_VERSION;

        $this->garbageCollect($root, $ttl);
    }

    /**
     * Sets the type for the next <em>store()</em> or <em>restore()</em> method
     * call. A type is something like a namespace or group for cache entries.
     *
     * Note that the cache type will be reset after each storage method call, so
     * you must invoke right before every call to <em>restore()</em> or
     * <em>store()</em>.
     *
     * @param string $type The name or object type for the next storage method call.
     * @return $this
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * This method will store the given <em>$data</em> under <em>$key</em>. This
     * method can be called with a third parameter that will be used as a
     * verification token, when the a cache entry gets restored. If the stored
     * hash and the supplied hash are not identical, that cache entry will be
     * removed and not returned.
     *
     * @param string $key The cache key for the given data.
     * @param mixed $data Any data that should be cached.
     * @param string $hash Optional hash that will be used for verification.
     */
    public function store(string $key, mixed $data, ?string $hash = null): void
    {
        $file = $this->getCacheFile($key);
        $this->write($file, serialize(['hash' => $hash, 'data' => $data]));
    }

    /**
     * This method writes the given <em>$data</em> into <em>$file</em>.
     *
     * @param string $file The cache file name.
     * @param string $data Serialized cache data.
     */
    protected function write(string $file, string $data): void
    {
        $handle = fopen($file, 'wb');
        if (!$handle) {
            return;
        }
        flock($handle, LOCK_EX);
        fwrite($handle, $data);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * This method tries to restore an existing cache entry for the given
     * <em>$key</em>. If a matching entry exists, this method verifies that the
     * given <em>$hash</em> and the the value stored with cache entry are equal.
     * Then it returns the cached entry. Otherwise this method will return
     * <b>NULL</b>.
     *
     * @param string $key The cache key for the given data.
     * @param ?string $hash Optional hash that will be used for verification.
     */
    public function restore(string $key, ?string $hash = null): mixed
    {
        $file = $this->getCacheFile($key);
        if (file_exists($file)) {
            return $this->restoreFile($file, $hash);
        }

        return null;
    }

    /**
     * This method restores a cache entry, when the given <em>$hash</em> is equal
     * to stored hash value. If both hashes are equal this method returns the
     * cached entry. Otherwise this method returns <b>NULL</b>.
     *
     * @param string  $file The cache file name.
     * @param ?string $hash The verification hash.
     */
    protected function restoreFile(string $file, ?string $hash): mixed
    {
        // unserialize() throws E_NOTICE when data is corrupt
        $data = @unserialize($this->read($file));
        if (is_array($data) && ($hash === null || $data['hash'] === $hash)) {
            return $data['data'];
        }

        return null;
    }

    /**
     * This method reads the raw data from the given <em>$file</em>.
     *
     * @param string $file The cache file name.
     */
    protected function read(string $file): string
    {
        $handle = fopen($file, 'rb');
        if (!$handle) {
            return '';
        }
        flock($handle, LOCK_EX);
        $size = filesize($file);
        if (!$size) {
            return '';
        }

        $data = fread($handle, $size);

        flock($handle, LOCK_UN);
        fclose($handle);

        return $data ?: '';
    }

    /**
     * This method will remove an existing cache entry for the given identifier.
     * It will delete all cache entries where the cache key start with the given
     * <b>$pattern</b>. If no matching entry exists, this method simply does
     * nothing.
     *
     * @param string $pattern The cache key pattern.
     */
    public function remove(string $pattern): void
    {
        $file = $this->getCacheFileWithoutExtension($pattern);
        $glob = glob("{$file}*.*") ?: [];
        // avoid error if we dont find files
        foreach ($glob as $f) {
            @unlink($f);
        }
    }

    /**
     * This method creates the full qualified file name for a cache entry. This
     * file name is a combination of the given <em>$key</em>, the cache root
     * directory and the current entry type.
     *
     * @param string $key The cache key for the given data.
     */
    protected function getCacheFile(string $key): string
    {
        $cacheFile = $this->getCacheFileWithoutExtension($key) .
                     '.' . $this->version .
                     '.' . $this->type;

        $this->type = self::ENTRY_TYPE;

        return $cacheFile;
    }

    /**
     * This method creates the full qualified file name for a cache entry. This
     * file name is a combination of the given <em>$key</em>, the cache root
     * directory and the current entry type, but without the used cache file
     * extension.
     *
     * @param string $key The cache key for the given data.
     */
    protected function getCacheFileWithoutExtension(string $key): string
    {
        if (is_string($this->cacheKey)) {
            $key = md5($key . $this->cacheKey);
        }

        $path = $this->directory->createCacheDirectory($key);

        return "{$path}/{$key}";
    }

    /**
     * Cleans old cache files.
     */
    protected function garbageCollect(string $root, int $ttl): void
    {
        $garbageCollector = new FileCacheGarbageCollector($root, $ttl);
        $garbageCollector->garbageCollect();
    }
}
