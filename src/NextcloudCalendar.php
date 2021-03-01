<?php declare(strict_types=1);

namespace Pdsinterop\Flysystem\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use OCP\Files\Folder;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\Connector\LegacyDAVACL;
use OCA\DAV\CalDAV\CalendarRoot;
use OCA\DAV\Connector\Sabre\Auth;
use OCA\DAV\Connector\Sabre\ExceptionLoggerPlugin;
use OCA\DAV\Connector\Sabre\MaintenancePlugin;
use OCA\DAV\Connector\Sabre\Principal;


/**
 * Filesystem adapter to convert RDF files to and from a default format
 */
class NextcloudCalendar implements AdapterInterface
{
    private $defaultAcl;
    private $userId;
    private $principalUri;

    final public function __construct($userId, $defaultAcl)
    {
        $this->userId = $userId;
        $this->principalUri = "principals/users/" . $this->userId;

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
            \OC::$server->query(\OCA\DAV\CalDAV\Proxy\ProxyMapper::class),
            \OC::$server->getConfig(),
            'principals/'
        );
        $db = \OC::$server->getDatabaseConnection();
        $userManager = \OC::$server->getUserManager();
        $random = \OC::$server->getSecureRandom();
        $logger = \OC::$server->getLogger();
        $dispatcher = \OC::$server->getEventDispatcher();

        $this->calDavBackend = new CalDavBackend($db, $principalBackend, $userManager, \OC::$server->getGroupManager(), $random, $logger, $dispatcher, true);	
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
     * Create a calendar.
     *
     * @param string $calendarName calendar name
     *
     * @return array|false
     */
    final public function createDir($calendarName, Config $config)
    {
        $calendarId = $this->calDavBackend->createCalendar($this->principalUri, $calendarName, array());
        if ($calendarId) {
            return ['path' => $calendarName, 'type' => 'dir'];
        }
        return false;
    }

    /**
     * Delete a calendar item.
     *
     * @param string $path
     *
     * @return bool
     */
    final public function delete($path)
    {
        $filename = basename($path);
        $calendar = dirname($path);
        $calendarId = $this->getCalendarId($calendar);
        $this->calDavBackend->deleteCalendarObject($calendarId, $filename);
        return true;
    }

    /**
     * Delete a calendar.
     *
     * @param string $calendar
     *
     * @return bool
     */
    final public function deleteDir($calendar)
    {
        $calendarId = $this->getCalendarId($calendar);
        if (!$calendarId) {
            return false;
        }

        $this->calDavBackend->deleteCalendar($calendarId);
        return true;
    }

    private function getCalendarId($path) {
        $path = explode("/", $path);
        if (sizeof($path) == 1) {
            $calendar = $this->calDavBackend->getCalendarByUri($this->principalUri, $path[0]);
            if ($calendar) {
	            return $calendar['id'];
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
        $calendarId = $this->getCalendarId($path);
        if ($calendarId) {
            $calendar = $this->calDavBackend->getCalendarById($calendarId);
            return $this->normalizeCalendar($calendar);
        } else {
            $filename = basename($path);
            $calendar = dirname($path);
            $calendarId = $this->getCalendarId($calendar);
            $calendarItem = $this->calDavBackend->getCalendarObject($calendarId, $filename);
            if ($calendarItem) {
                return $this->normalizeCalendarItem($calendarItem, $calendar);
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

        $calendarId = $this->getCalendarId($path);
        if ($calendarId) {
            return true;
        } else {
            $filename = basename($path);
            $calendar = dirname($path);
            $calendarId = $this->getCalendarId($calendar);
            $calendarItem = $this->calDavBackend->getCalendarObject($calendarId, $filename);
            if ($calendarItem) {
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
            $calendars = $this->calDavBackend->getCalendarsForUser($this->userId);
            $result = array_map(function ($calendar) {
                return $this->normalizeCalendar($calendar);
            }, $calendars);

            return $result;
        } else {
            $directory = basename($directory);

            $calendar = $this->calDavBackend->getCalendarByUri($this->principalUri, $directory);
    	    $calendarObjects = $this->calDavBackend->getCalendarObjects($calendar['id']);
    	    $contents = [];

    	    foreach ($calendarObjects as $calendarObject) {
                $contents[] = $this->calDavBackend->getCalendarObject($calendarObject['calendarid'], $calendarObject['uri']);
            }
	        $result = array_map(function($calendarItem) use ($directory) {
                return $this->normalizeCalendarItem($calendarItem, $directory);
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
        $calendar = dirname($path);
        $calendarId = $this->getCalendarId($calendar);
        $calendarItem = $this->calDavBackend->getCalendarObject($calendarId, $filename);
        if (!$calendarItem) {
            return false;
        }
        return $this->normalizeCalendarItem($calendarItem, $calendar);
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
        $result = true;

        $filename = basename($path);
        $calendar = dirname($path);
        $calendarId = $this->getCalendarId($calendar);
        if ($this->has($path)) {
            $this->calDavBackend->updateCalendarObject($calendarId, $filename, $contents);
        } else {
            $this->calDavBackend->createCalendarObject($calendarId, $filename, $contents);
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
    private function normalizeCalendarItem($calendarItem, $basePath) {
        return array(
            'mimetype' => 'text/calendar',
            'path' => $basePath . '/' . $calendarItem['uri'],
            'basename' => $calendarItem['uri'],
            'timestamp' => $calendarItem['lastmodified'],
            'size' => $calendarItem['size'],
            'type' => "file",
            'visibility' => 'public',
            'contents' => $calendarItem['calendardata']
        );
    }

    private function normalizeCalendar($calendar)
    {
        return array(
            'mimetype' => "directory",
            'path' => $calendar['uri'],
            'size' => 0,
            'basename' => basename($calendar['uri']),
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
