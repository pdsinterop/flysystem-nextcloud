<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\Connector\LegacyDAVACL;
use OCA\DAV\CardDav\AddressBookRoot;
use OCA\DAV\Connector\Sabre\Auth;
use OCA\DAV\Connector\Sabre\ExceptionLoggerPlugin;
use OCA\DAV\Connector\Sabre\MaintenancePlugin;
use OCA\DAV\Connector\Sabre\Principal;


/**
 * Filesystem adapter to convert RDF files to and from a default format
 */
class NextcloudContacts implements AdapterInterface
{
    private $defaultAcl;
    private $userId;
    private $principalUri;

    final public function __construct($userId, $defaultAcl)
    {
        $this->userId = $userId;
        $this->principalUri = "principals/users/" . $this->userId;
        $this->defaultAcl = $defaultAcl;

        $authBackend = new Auth(
            \OC::$server->getSession(),
            \OC::$server->getUserSession(),
            \OC::$server->getRequest(),
            \OC::$server->getTwoFactorAuthManager(),
            \OC::$server->getBruteForceThrottler(),
            'principals/'
        );
        $principalBackend = new Principal(
            \OC::$server->getUserManager(),
            \OC::$server->getGroupManager(),
            \OC::$server->getShareManager(),
            \OC::$server->getUserSession(),
            \OC::$server->getAppManager(),
            \OC::$server->query(\OCA\DAV\CalDav\Proxy\ProxyMapper::class),
            \OC::$server->getConfig(),
            'principals/'
        );
        $db = \OC::$server->getDatabaseConnection();
        $userManager = \OC::$server->getUserManager();
        $dispatcher = \OC::$server->getEventDispatcher();

        $this->cardDavBackend = new CardDavBackend(
            $db,
            $principalBackend,
            $userManager,
            \OC::$server->getGroupManager(),
            $dispatcher,
            true
        );	
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
     * Create an address book.
     *
     * @param string $addressBookName address book name
     *
     * @return array|false
     */
    final public function createDir($addressBookName, Config $config)
    {
        $addressBookId = $this->cardDavBackend->createAddressBook($this->principalUri, $addressBookName, array());
        if ($addressBookId) {
            return ['path' => $addressBookName, 'type' => 'dir'];
        }
        return false;
    }

    /**
     * Delete a card.
     *
     * @param string $path
     *
     * @return bool
     */
    final public function delete($path)
    {
        $filename = basename($path);
        $addressBook = dirname($path);
        $addressBookId = $this->getAddressBookId($addressBook);
        $this->cardDavBackend->deleteCard($addressBookId, $filename);
        return true;
    }

    /**
     * Delete an addressBook.
     *
     * @param string $addressBook
     *
     * @return bool
     */
    final public function deleteDir($addressBook)
    {
        $addressBookId = $this->getCalendarId($addressBook);
        if (!$addressBookId) {
            return false;
        }

        $this->cardDavBackend->deleteAddressBook($addressBookId);
        return true;
    }

    private function getAddressBookId($path) {
        $path = explode("/", $path);
        if (sizeof($path) == 1) {
            $addressBook = $this->cardDavBackend->getAddressBooksByUri($this->principalUri, $path[0]);
            if ($addressBook) {
                return $addressBook['id'];
            }
        }
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
        $addressBookId = $this->getAddressBookId($path);
        if ($addressBookId) {
            $addressBook = $this->cardDavBackend->getAddressBookById($addressBookId);
            return $this->normalizeAddressBook($addressBook);
        } else {
            $filename = basename($path);
            $addressBook = dirname($path);
            $addressBookId = $this->getAddressBookId($addressBook);
            $card = $this->cardDavBackend->getCard($addressBookId, $filename);
            if ($card) {
                return $this->normalizeCard($card, $addressBook);
            }
        }
        return false;

        $path = explode("/", $path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function getMimeType($path)
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
    final public function getSize($path)
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
    final public function getTimestamp($path)
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
    final public function getVisibility($path)
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
    final public function has($path)
    {
        if ($path == ".acl" && $this->defaultAcl) {
            return true;
        }

        $addressBookId = $this->getAddressBookId($path);
        if ($addressBookId) {
            return true;
        } else {
            $filename = basename($path);
            $addressBook = dirname($path);
            $addressBookId = $this->getAddressBookId($addressBook);
            $card = $this->cardDavBackend->getCard($addressBookId, $filename);
            if ($card) {
                return true;
            }
        }
        return false;
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
        $result = [];
        if ($directory === "") {
            $addressBooks = $this->cardDavBackend->getAddressBooksForUser($this->userId);
            $result = array_map(function ($addressBook) {
                return $this->normalizeAddressBook($addressBook);
            }, $addressBooks);

            return $result;
        } else {
            $directory = basename($directory);

            $addressBook = $this->cardDavBackend->getAddressBooksByUri($this->principalUri, $directory);
            $cards = $this->cardDavBackend->getCards($addressBook['id']);
            $contents = [];

            foreach ($cards as $card) {
                $contents[] = $this->cardDavBackend->getCard($addressBook['id'], $card['uri']);
            }
            $result = array_map(function($card) use ($directory) {
                return $this->normalizeCard($card, $directory);
            }, $contents);
            return $result;
        }
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
        if ($path == ".acl" && $this->defaultAcl) {
            return $this->normalizeAcl($this->defaultAcl);
        }

        $filename = basename($path);
        $addressBook = dirname($path);
        $addressBookId = $this->getAddressBookId($addressBook);
        $card = $this->cardDavBackend->getCard($addressBookId, $filename);
        if (!$card) {
            return false;
        }
        return $this->normalizeCard($card, $addressBook);
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    final public function readStream($path)
    {
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
    final public function rename($path, $newpath)
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
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    final public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * Update a file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    final public function updateStream($path, $resource, Config $config)
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
        $filename = basename($path);
        $addressBook = dirname($path);
        $addressBookId = $this->getAddressBookId($addressBook);
        if ($this->has($path)) {
            $this->cardDavBackend->updateCard($addressBookId, $filename, $contents);
        } else {
            $this->cardDavBackend->createCard($addressBookId, $filename, $contents);
        }
        return true;
    }

    /**
     * Write a new file using a stream.
     *
     * @param string $path
     * @param resource $resource
     * @param Config $config Config object
     *
     * @return array|false false on failure file meta data on success
     */
    final public function writeStream($path, $resource, Config $config)
    {
        return false;
    }

    private function normalizeAcl($acl) {
        return array(
            'mimetype' => 'text/turtle',
            'path' => ".acl",
            'basename' => ".acl",
            'timestamp' => 0,
            'size' => strlen($acl),
            'type' => "file",
            'visibility' => 'public',
            'contents' => $acl
        );
    }
    private function normalizeCard($card, $basePath) {
        return array(
            'mimetype' => 'text/vcard',
            'path' => $basePath . '/' . $card['uri'],
            'basename' => $card['uri'],
            'timestamp' => $card['lastmodified'],
            'size' => $card['size'],
            'type' => "file",
            'visibility' => 'public',
            'contents' => $card['carddata']
        );
    }

    private function normalizeAddressBook($addressBook)
    {
        return array(
            'mimetype' => "directory",
            'path' => $addressBook['uri'],
            'size' => 0,
            'basename' => basename($addressBook['uri']),
            'timestamp' => 0,
            'type' => "dir",
            // @FIXME: Use $node->getPermissions() to set private or public
            //         as soon as we figure out what Nextcloud permissions mean in this context
            'visibility' => 'public',
            /*/
            'CreationTime' => $node->getCreationTime(),
            'Etag' => $node->getEtag(),
            'Owner' => $node->getOwner(),
            /*/
        );
    }
}
