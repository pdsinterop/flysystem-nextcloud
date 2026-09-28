<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use OCA\Solid\ServerConfig;

/**
 * Filesystem adapter to access profile information from Nextcloud
 */
class NextcloudProfile implements AdapterInterface
{
    use StreamedTrait;

    /** @var ServerConfig */
    private $config;
    /** @var string */
    private $defaultAcl;
    /** @var string */
    private $profile;
    /** @var string */
    private $userId;

    final public function __construct($userId, $profile, $defaultAcl, $config)
    {
        $this->userId = $userId;
        $this->defaultAcl = $defaultAcl;
        $this->config = $config;
        $this->profile = $profile;
    }

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    final public function copy($path, $newpath)
    {
        // FIXME: Implementation
        return false;
    }

    /**
     * Create a dir.
     *
     * @param string $dirName dir name
     *
     * @return array|false
     */
    final public function createDirectory($dirName, Config $config)
    {
        return false;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool
     */
    final public function delete($path)
    {
        return false;
    }

    /**
     * Delete a dir.
     *
     * @param string $dirName
     *
     * @return bool
     */
    final public function deleteDirectory($dirName)
    {
        return false;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function getMetadata($path)
    {
        return $this->normalizeProfile();
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function mimeType($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function fileSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function lastModified($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function visibility($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return bool
     */
    final public function fileExists($path)
    {
        if ($path === '.acl' && $this->defaultAcl) {
            return true;
        }

        if ($path === 'card') {
            return true;
        }
        return false;
    }

    /**
     * Check whether a directory exists.
     *
     * @param string $path
     *
     * @return bool
     */
    final public function directoryExists($path)
    {
        return $this->fileExists($path);
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     */
    final public function listContents($directory = '', $recursive = false)
    {
        return [
            $this->normalizeProfile()
        ];
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function read($path)
    {
        if ($path === '.acl' && $this->defaultAcl) {
            return $this->normalizeAcl($this->defaultAcl);
        }
        if ($path === 'card') {
            return $this->normalizeProfile();
        }
        return false;
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return bool
     */
    final public function move($path, $newpath)
    {
        return false;
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return array|false file meta data
     */
    final public function setVisibility($path, $visibility)
    {
        return false;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    final public function write($path, $contents, Config $config)
    {
        if ($path === 'card') {
            $this->config->setProfileData($this->userId, $contents);
            return true;
        }
        return false;
    }

    private function normalizeAcl($acl) {
        return [
            'basename' => '.acl',
            'contents' => $acl,
            'mimetype' => 'text/turtle',
            'path' => '.acl',
            'size' => strlen($acl),
            'timestamp' => 0,
            'type' => 'file',
            'visibility' => 'public',
        ];
    }

    private function normalizeProfile() {
        $profile = $this->profile;
        return [
            'basename' => 'card',
            'contents' => $profile,
            'mimetype' => 'text/turtle',
            'path' => "card",
            'size' => strlen($profile),
            'timestamp' => 0,
            'type' => 'file',
            'visibility' => 'public',
        ];
    }
}
