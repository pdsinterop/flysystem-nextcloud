<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FileAttributes;
use League\Flysystem\Config;
use OCP\Files\Folder;

/**
 * Filesystem adapter to access files in Nextcloud
 */
class Nextcloud implements FilesystemAdapter
{
    /** @var Folder */
    private $folder;

    final public function __construct(Folder $folder)
    {
        $this->folder = $folder;
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
        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return;
        }

        $node->copy($newpath);
    }

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @throws \OCP\Files\NotPermittedException
     */
    final public function createDirectory(string $dirname, Config $config): void
    {
        $this->folder->newFolder($dirname);
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotPermittedException
     */
    final public function delete(string $path): void
    {
        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return;
        }

        $node->delete();
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotPermittedException
     */
    final public function deleteDirectory(string $dirname): void
    {
        try {
            $node = $this->folder->get($dirname);
        } catch (\OCP\Files\NotFoundException $exception) {
            return;
        }

        if ($this->isDirectory($node)) {
            $node->delete();
        }
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return \League\Flysystem\FileAttributes
     *
     * @throws \OCP\Files\InvalidPathException
     */
    final public function getAttributes(string $path): FileAttributes
    {
        try {
            $node = $this->folder->get($path);
            $metaData = $this->normalizeNodeInfo($node);
            return new FileAttributes(
                $path,
                $metaData['size'],
                $metaData['visibility'],
                $metaData['timestamp'],
                $metaData['mimetype']
            );
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }
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
        return $this->folder->nodeExists($path);
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
        return $this->folder->nodeExists($path);
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotFoundException
     */
    final public function listContents(string $directory = '', bool $recursive = false): iterable
    {
        $result = [];

        try {
            $node = $this->folder->get("/" . $directory);
        } catch (\OCP\Files\NotFoundException $exception) {
            return [];
        }

        if (method_exists($node, 'getDirectoryListing')) {
            $nodes = $node->getDirectoryListing();

            $result = array_map(function (\OCP\Files\Node $node) {
                return $this->normalizeNodeInfo($node);
            }, $nodes);
        }

        return $result;
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return string
     *
     * @throws \OCP\Files\InvalidPathException
     */
    final public function read(string $path): string
    {
        $result = false;

        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }

        if (method_exists($node, 'getContent')) {
            return $node->getContent();
        }
        // FIXME: throw exception
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function readStream(string $path)
    {
        $result = false;

        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return false;
        }

        if (method_exists($node, 'fopen')) {
            return $node->fopen('rb');
        }
        // FIXME: throw exception
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     * @param Config $config
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotPermittedException
     * @throws \OCP\Lock\LockedException
     */
    final public function move(string $path, string $newpath, Config $config): void
    {
        try {
            $this->folder->get($path)->move($newpath);
        } catch (\OCP\Files\NotFoundException $exception) {
            return;
        }
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     */
    final public function setVisibility(string $path, string $visibility): void
    {
        // FIXME: implement something here
    }

    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @throws \OCP\Files\InvalidPathException
     */
    final public function write(string $path, string $contents, Config $config): void
    {
        try {
            if ($this->folder->nodeExists($path)) {
                $node = $this->folder->get($path);
                if (method_exists($node, 'putContent')) {
                    $node->putContent($contents);
                }
            } else {
                $filename = basename($path);
                $dirname = dirname($path);
                if (!$this->folder->nodeExists($dirname)) {
                    $this->folder->newFolder($dirname);
                }
                $node = $this->folder->get($dirname);
                $node->newFile($filename, $contents);
            }
        } catch(\Exception $e) {
            // FIXME: throw?
        }
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @throws \OCP\Files\NotPermittedException
     */
    final public function writeStream($path, $resource, Config $config): void
    {
        try {
            $node = $this->folder->get($path);
        } catch (\OCP\Files\NotFoundException $exception) {
            return;
        }

        if (method_exists($node, 'fopen')) {
            $dirname = dirname($path);
            $folder = $this->folder->nodeExists($dirname);

            // @CHECKME: Do we need to create a directory or will the Node do that for us?
            if ($folder === false) {
                $this->createDirectory($dirname, $config);
            }

            $stream = $node->fopen('w+b');

            if (stream_copy_to_stream($resource, $stream) === false) {
                fclose($stream);
                return;
            }
        }
    }

    /**
     * @param \OCP\Files\Node $node
     *
     * @return bool
     */
    private function isDirectory(\OCP\Files\Node $node)
    {
        return $node->getType() === \OCP\Files\FileInfo::TYPE_FOLDER;
    }

    /**
     * @param \OCP\Files\Node $node
     * @param array $metaData
     *
     * @return array
     *
     * @throws \OCP\Files\InvalidPathException
     * @throws \OCP\Files\NotFoundException
     */
    private function normalizeNodeInfo(\OCP\Files\Node $node, array $metaData = []) : array
    {
        return array_merge([
            'mimetype' => $this->isDirectory($node) ? "directory" : $node->getMimetype(),
            'path' => substr($node->getPath(), strlen($this->folder->getPath())+1),
            'size' => $node->getSize(),
            'basename' => basename($node->getPath()),
            'timestamp' => $node->getMTime(),
            'type' => $node->getType(),
            // @FIXME: Use $node->getPermissions() to set private or public
            //         as soon as we figure out what Nextcloud permissions mean in this context
            'visibility' => 'public',
            /*/
            'CreationTime' => $node->getCreationTime(),
            'Etag' => $node->getEtag(),
            'Owner' => $node->getOwner(),
            /*/
        ], $metaData);
    }
}
