<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FileAttributes;
use League\Flysystem\Config;

use OC;
use OCA\DAV\CalDav\Proxy\ProxyMapper;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\Connector\Sabre\Principal;
use Sabre\DAV\Exception\BadRequest;


/**
 * Filesystem adapter to access contacts information from Nextcloud
 */
class NextcloudContacts implements FilesystemAdapter
{
    /** @var CardDavBackend */
    private $cardDavBackend;
    /** @var string */
    private $defaultAcl;
    /** @var string */
    private $principalUri;
    /** @var string */
    private $userId;

    final public function __construct($userId, $defaultAcl)
    {
        $this->userId = $userId;
        $this->principalUri = 'principals/users/' . $this->userId;
        $this->defaultAcl = $defaultAcl;

        $principalBackend = new Principal(
            OC::$server->getUserManager(),
            OC::$server->getGroupManager(),
            OC::$server->getShareManager(),
            OC::$server->getUserSession(),
            OC::$server->getAppManager(),
            OC::$server->query(ProxyMapper::class),
            OC::$server->getConfig(),
            'principals/'
        );
        $db = OC::$server->getDatabaseConnection();
        $userManager = OC::$server->getUserManager();
        $dispatcher = OC::$server->get(\OCP\EventDispatcher\IEventDispatcher::class);
        $legacyDispatcher = OC::$server->getEventDispatcher();

        $this->cardDavBackend = new CardDavBackend(
            $db,
            $principalBackend,
            $userManager,
            OC::$server->getGroupManager(),
            $dispatcher,
            $legacyDispatcher,
            true
        );
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
     * Create an address book.
     *
     * @param string $addressBookName address book name
     * @param Config $config
     *
     * @throws BadRequest
     */
    final public function createDirectory(string $addressBookName, Config $config): void
    {
        $this->cardDavBackend->createAddressBook($this->principalUri, $addressBookName, array());
    }

    /**
     * Delete a card.
     *
     * @param string $path
     */
    final public function delete(string $path): void
    {
        list($addressBook, $filename) = $this->splitPath($path);
        $addressBookId = $this->getAddressBookId($addressBook);
        $this->cardDavBackend->deleteCard($addressBookId, $filename);
    }

    /**
     * Delete an addressBook.
     *
     * @param string $addressBook
     */
    final public function deleteDirectory(string $addressBook): void
    {
        $addressBookId = $this->getAddressBookId($addressBook);
        if (!$addressBookId) {
            return;
        }

        $this->cardDavBackend->deleteAddressBook($addressBookId);
    }

    private function getAddressBookId($path) {
        $path = explode('/', $path);
        if (count($path) === 1) {
            $addressBook = $this->cardDavBackend->getAddressBooksByUri($this->principalUri, $path[0]);
            if ($addressBook) {
                return $addressBook['id'];
            }
        }

        return null;
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
        $addressBookId = $this->getAddressBookId($path);
        if ($addressBookId !== null) {
            $addressBook = $this->cardDavBackend->getAddressBookById($addressBookId);
            $metaData = $this->normalizeAddressBook($addressBook);
        } else {
            list($addressBook, $filename) = $this->splitPath($path);
            $addressBookId = $this->getAddressBookId($addressBook);
            $card = $this->cardDavBackend->getCard($addressBookId, $filename);
            $metaData = $this->normalizeCard($card, $addressBook);
        }
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

        $addressBookId = $this->getAddressBookId($path);
        if ($addressBookId !== null) {
            return true;
        } else {
            list($addressBook, $filename) = $this->splitPath($path);
            $addressBookId = $this->getAddressBookId($addressBook);
            $card = $this->cardDavBackend->getCard($addressBookId, $filename);

            return is_array($card);
        }
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
        if ($directory === '') {
            $addressBooks = $this->cardDavBackend->getAddressBooksForUser($this->userId);

            return array_map(function ($addressBook) {
                $attributes = $this->normalizeAddressBook($addressBook);
                return new DirectoryAttributes(
                    $attributes['path'],
                    $attributes['visibility'],
                    $attributes['time']
                );
            }, $addressBooks);
        } else {
            $directory = basename($directory);

            $addressBook = $this->cardDavBackend->getAddressBooksByUri($this->principalUri, $directory);
            $cards = $this->cardDavBackend->getCards($addressBook['id']);

            $contents = [];
            foreach ($cards as $card) {
                $contents[] = $this->cardDavBackend->getCard($addressBook['id'], $card['uri']);
            }

            return array_map(function($card) use ($directory) {
                $attributes = $this->normalizeCard($card, $directory);
                return new FileAttributes(
                    $attributes['path'],
                    $attributes['size'],
                    $attributes['visibility'],
                    $attributes['timestamp']
                );
            }, $contents);
        }
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function read(string $path): string
    {
        if ($path === '.acl' && $this->defaultAcl) {
            return $this->defaultAcl;
        }

        list($addressBook, $filename) = $this->splitPath($path);
        $addressBookId = $this->getAddressBookId($addressBook);
        $card = $this->cardDavBackend->getCard($addressBookId, $filename);
        return $card['carddata'];
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
     *
     * @return array|false file meta data
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
     *
     * @return array|false false on failure file meta data on success
     *
     * @throws BadRequest
     */
    final public function write(string $path, string $contents, Config $config): void
    {
        list($addressBook, $filename) = $this->splitPath($path);
        $addressBookId = $this->getAddressBookId($addressBook);
        if ($this->has($path)) {
            $this->cardDavBackend->updateCard($addressBookId, $filename, $contents);
        } else {
            $this->cardDavBackend->createCard($addressBookId, $filename, $contents);
        }
        return;
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

    private function normalizeCard($card, $basePath) {
        if ( ! is_array($card)) {
            return false;
        }

        return [
            'basename' => $card['uri'],
            'contents' => $card['carddata'],
            'mimetype' => 'text/vcard',
            'path' => $basePath . '/' . $card['uri'],
            'size' => $card['size'],
            'timestamp' => $card['lastmodified'],
            'type' => 'file',
            'visibility' => 'public',
        ];
    }

    private function normalizeAddressBook($addressBook)
    {
        return [
            'basename' => basename($addressBook['uri']),
            'mimetype' => 'directory',
            'path' => $addressBook['uri'],
            'size' => 0,
            'timestamp' => 0,
            'type' => 'dir',
            // @FIXME: Use $node->getPermissions() to set private or public
            //         as soon as we figure out what Nextcloud permissions mean in this context
            'visibility' => 'public',
            /*/
            'CreationTime' => $node->getCreationTime(),
            'Etag' => $node->getEtag(),
            'Owner' => $node->getOwner(),
            /*/
        ];
    }

    /**
     * @param string $path
     *
     * @return string[]
     */
    private function splitPath(string $path)
    {
        return [
            dirname($path),
            basename($path)
        ];
    }
}
