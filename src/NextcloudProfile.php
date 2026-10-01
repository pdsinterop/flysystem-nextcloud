<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FileAttributes;
use League\Flysystem\Config;
use OCA\Solid\ServerConfig;

/**
 * Filesystem adapter to access profile information from Nextcloud
 */
class NextcloudProfile implements FilesystemAdapter
{
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
     * @param Config $config
     */
    final public function copy(string $path, string $newpath, Config $config): void
    {
        // FIXME: Implementation
        return;
    }

    /**
     * Create a dir.
     *
     * @param string $dirName dir name
     *
     * @return array|false
     */
    final public function createDirectory(string $dirname, Config $config): void
    {
        return;
    }

    /**
     * Delete a file.
     *
     * @param string $path
     */
    final public function delete(string $path): void
    {
        return;
    }

    /**
     * Delete a dir.
     *
     * @param string $dirName
     */
    final public function deleteDirectory(string $dirname): void
    {
        return;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return \League\Flysystem\FileAttributes
     */
    final public function getAttributes(string $path): FileAttributes
    {
        $metaData = $this->normalizeProfile();
        return new FileAttributes(
            $path,
            $metaData['size'],
            $metaData['visibility'],
            $metaData['timestamp'],
            $metaData['mimetype']
        );
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return \League\Flysystem\FileAttributes
     */
    final public function mimeType(string $path): FileAttributes
    {
        return $this->getAttributes($path);
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return \League\Flysystem\FileAttributes
     */
    final public function fileSize(string $path): FileAttributes
    {
        return $this->getAttributes($path);
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return \League\Flysystem\FileAttributes
     */
    final public function lastModified(string $path): FileAttributes
    {
        return $this->getAttributes($path);
    }

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return \League\Flysystem\FileAttributes
     */
    final public function visibility(string $path): FileAttributes
    {
        return $this->getAttributes($path);
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return bool
     */
    final public function fileExists(string $path): bool
    {
        if ($path === '.acl' && $this->defaultAcl) {
            return true;
        }

        if ($path === 'card' || $path === '/card') {
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
    final public function directoryExists(string $path): bool
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
    final public function listContents(string $directory = '', bool $recursive = false): iterable
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
     * @return string
     */
    final public function read(string $path): string
    {
        if ($path === '.acl' && $this->defaultAcl) {
            return $this->defaultAcl;
        }
        if ($path === 'card') {
            return $this->profile;
        }
        return '';
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     * @param Config $config
     */
    final public function move(string $path, string $newpath, Config $config): void
    {
        return;
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     */
    final public function setVisibility(string $path, string $visibility): void
    {
        return;
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     */
    final public function write(string $path, string $contents, Config $config): void
    {
        if ($path === 'card') {
            $this->config->setProfileData($this->userId, $contents);
        }
    }


    final public function writeStream(string $path, $contents, Config $config): void
    {
    }

    final public function readStream(string $path): void
    {
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
