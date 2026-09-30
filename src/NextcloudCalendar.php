<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\Adapter\Polyfill\StreamedTrait;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;

use OC;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCA\DAV\Connector\Sabre\Principal;
use Sabre\DAV\Exception;
use Sabre\DAV\Exception\BadRequest;


/**
 * Filesystem adapter to access calendar information from Nextcloud
 */
class NextcloudCalendar implements AdapterInterface
{
    use StreamedTrait;

    /** @var CalDavBackend */
    private $calDavBackend;
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
        $random = OC::$server->getSecureRandom();
        $logger = OC::$server->getLogger();
        $dispatcher = OC::$server->get(\OCP\EventDispatcher\IEventDispatcher::class);
        $legacyDispatcher = OC::$server->getEventDispatcher();
        $this->calDavBackend = new CalDavBackend(
            $db,
            $principalBackend,
            $userManager,
            OC::$server->getGroupManager(),
            $random,
            $logger,
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
     */
    final public function copy(string $path, string $newpath): void
    {
        // FIXME: Implementation
        return;
    }

    /**
     * Create a calendar.
     *
     * @param string $calendarName calendar name
     * @param Config $config
     *
     * @throws Exception
     */
    final public function createDirectory(string $calendarName, Config $config): void
    {
        $calendarId = $this->calDavBackend->createCalendar($this->principalUri, $calendarName, []);
    }

    /**
     * Delete a calendar item.
     *
     * @param string $path
     */
    final public function delete(string $path): void
    {
        list($calendar, $filename) = $this->splitPath($path);
        $calendarId = $this->getCalendarId($calendar);
        $this->calDavBackend->deleteCalendarObject($calendarId, $filename);
    }

    /**
     * Delete a calendar.
     *
     * @param string $calendar
     */
    final public function deleteDirectory(string $calendar): void
    {
        $calendarId = $this->getCalendarId($calendar);
        if (!$calendarId) {
            return;
        }

        $this->calDavBackend->deleteCalendar($calendarId);
    }

    private function getCalendarId($path) {
        $path = explode('/', $path);
        if (count($path) === 1) {
            $calendar = $this->calDavBackend->getCalendarByUri($this->principalUri, $path[0]);
            if ($calendar) {
                return $calendar['id'];
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
        $calendarId = $this->getCalendarId($path);
        if ($calendarId !== null) {
            $calendar = $this->calDavBackend->getCalendarById($calendarId);
            $metaData = $this->normalizeCalendar($calendar);
        } else {
            list($calendar, $filename) = $this->splitPath($path);
            $calendarId = $this->getCalendarId($calendar);
            $calendarItem = $this->calDavBackend->getCalendarObject($calendarId, $filename);
            $metaData = $this->normalizeCalendarItem($calendarItem, $calendar);
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

        $calendarId = $this->getCalendarId($path);
        if ($calendarId !== null) {
            return true;
        } else {
            list($calendar, $filename) = $this->splitPath($path);
            $calendarId = $this->getCalendarId($calendar);
            $calendarItem = $this->calDavBackend->getCalendarObject($calendarId, $filename);

            return is_array($calendarItem);
        }
    }

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
            $calendars = $this->calDavBackend->getCalendarsForUser($this->userId);

            return array_map(function ($calendar) {
                return $this->normalizeCalendar($calendar);
            }, $calendars);
        } else {
            $directory = basename($directory);

            $calendar = $this->calDavBackend->getCalendarByUri($this->principalUri, $directory);
            $calendarObjects = $this->calDavBackend->getCalendarObjects($calendar['id']);

            $contents = [];
            foreach ($calendarObjects as $calendarObject) {
                $contents[] = $this->calDavBackend->getCalendarObject($calendarObject['calendarid'], $calendarObject['uri']);
            }

            return array_map(function($calendarItem) use ($directory) {
                return $this->normalizeCalendarItem($calendarItem, $directory);
            }, $contents);
        }
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

        list($calendar, $filename) = $this->splitPath($path);
        $calendarId = $this->getCalendarId($calendar);
        $calendarItem = $this->calDavBackend->getCalendarObject($calendarId, $filename);
        return $calendarItem['calendardata'];
    }

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     */
    final public function move(string $path, string $newpath): void
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
     *
     * @throws BadRequest
     */
    final public function write(string $path, string $contents, Config $config): void
    {
        list($calendar, $filename) = $this->splitPath($path);
        $calendarId = $this->getCalendarId($calendar);
        if ($this->has($path)) {
            $this->calDavBackend->updateCalendarObject($calendarId, $filename, $contents);
        } else {
            $this->calDavBackend->createCalendarObject($calendarId, $filename, $contents);
        }
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

    private function normalizeCalendarItem($calendarItem, $basePath) {
        if ( ! is_array($calendarItem)) {
            return false;
        }

        return [
            'basename' => $calendarItem['uri'],
            'contents' => $calendarItem['calendardata'],
            'mimetype' => 'text/calendar',
            'path' => $basePath . '/' . $calendarItem['uri'],
            'size' => $calendarItem['size'],
            'timestamp' => $calendarItem['lastmodified'],
            'type' => 'file',
            'visibility' => 'public',
        ];
    }

    private function normalizeCalendar($calendar)
    {
        return [
            'basename' => basename($calendar['uri']),
            'mimetype' => 'directory',
            'path' => $calendar['uri'],
            'size' => 0,
            'timestamp' => 0,
            'type' => 'dir',
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
