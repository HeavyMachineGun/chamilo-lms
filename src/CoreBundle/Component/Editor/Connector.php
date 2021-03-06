<?php

/* For licensing terms, see /license.txt */

namespace Chamilo\CoreBundle\Component\Editor;

use Chamilo\CoreBundle\Component\Editor\Driver\Driver;
use Chamilo\CoreBundle\Entity\Course;
use Chamilo\CoreBundle\Entity\Session;
use Chamilo\CoreBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

//use Symfony\Component\Security\Core\SecurityContext;

/**
 * Class elFinder Connector - editor + Chamilo repository.
 */
class Connector
{
    /** @var Course */
    public $course;

    /** @var User */
    public $user;

    /** @var Session */
    public $session;

    /** @var Translator */
    public $translator;

    /** @var Router */
    public $urlGenerator;

    public $security;

    public $paths;

    public $entityManager;

    public $drivers = [];
    public $driverList = [];

    public function __construct(
        EntityManager $entityManager,
        array $paths,
        RouterInterface $urlGenerator,
        TranslatorInterface $translator,
        $security,
        $user,
        $course,
        $session
    ) {
        $this->paths = [
            //'root_sys' => api_get_path(SYS_PATH),
            //'sys_root' => api_get_path(SYS_PATH), // just an alias
            //'sys_course_path' => api_get_path(SYS_COURSE_PATH),
            //   'sys_config_path' => $app['path.config'],
            'path.temp' => api_get_path(SYS_ARCHIVE_PATH),
            //'sys_log_path' => $app['path.logs']
        ];
        $this->entityManager = $entityManager;
        //$this->paths = $paths;
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
        $this->security = $security;
        $this->user = $user;
        $this->course = $course;
        $this->session = $session;
        $this->driverList = $this->getDefaultDriverList();
    }

    public function getDriverList(): array
    {
        return $this->driverList;
    }

    /**
     * Available driver list.
     *
     * @param array $list
     */
    public function setDriverList($list)
    {
        $this->driverList = $list;
    }

    /**
     * @param Driver $driver
     */
    public function addDriver($driver)
    {
        if (!empty($driver)) {
            $this->drivers[$driver->getName()] = $driver;
        }
    }

    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * @param string $driverName
     *
     * @return Driver
     */
    public function getDriver($driverName)
    {
        if (isset($this->drivers[$driverName])) {
            return $this->drivers[$driverName];
        }

        return null;
    }

    /**
     * @param bool $processDefaultValues
     *
     * @return array
     */
    public function getRoots($processDefaultValues = true)
    {
        $roots = [];
        $drivers = $this->getDrivers();
        /** @var Driver $driver */
        foreach ($drivers as $driver) {
            if ($processDefaultValues) {
                $plugin = [
                    'chamilo' => [
                        'driverName' => $driver->getName(),
                        'connector' => $this,
                    ],
                ];
                $configuration = $driver->getConfiguration();
                $driver->setup();
                $configuration['plugin'] = $plugin;
                $root = $this->updateWithDefaultValues($configuration);
            }
            $roots[] = $root;
        }

        return $roots;
    }

    /**
     * Merges the default driver settings.
     *
     * @param array $driver
     *
     * @return array
     */
    public function updateWithDefaultValues($driver)
    {
        if (empty($driver) || !isset($driver['driver'])) {
            return [];
        }

        $defaultDriver = $this->getDefaultDriverSettings();

        if (isset($driver['attributes'])) {
            $attributes = array_merge($defaultDriver['attributes'], $driver['attributes']);
        } else {
            $attributes = $defaultDriver['attributes'];
        }

        $driverUpdated = array_merge($defaultDriver, $driver);
        $driverUpdated['driver'] = 'Chamilo\CoreBundle\Component\Editor\Driver\\'.$driver['driver'];
        $driverUpdated['attributes'] = $attributes;

        return $driverUpdated;
    }

    /**
     * Get default driver settings.
     */
    public function getDefaultDriverSettings(): array
    {
        // for more options: https://github.com/Studio-42/elFinder/wiki/Connector-configuration-options
        return [
            'uploadOverwrite' => false, // Replace files on upload or give them new name if the same file was uploaded
            //'acceptedName' =>
            'uploadAllow' => [
                'image',
                'audio',
                'video',
                'text/html',
                'text/csv',
                'application/pdf',
                'application/postscript',
                'application/vnd.ms-word',
                'application/vnd.ms-excel',
                'application/vnd.ms-powerpoint',
                'application/pdf',
                'application/xml',
                'application/vnd.oasis.opendocument.text',
                'application/x-shockwave-flash',
                'application/vnd.adobe.flash.movie',
            ], // allow files
            //'uploadDeny' => array('text/x-php'),
            'uploadOrder' => ['allow'], // only executes allow
            'disabled' => [
                'duplicate',
                'rename',
                'mkdir',
                'mkfile',
                'copy',
                'cut',
                'paste',
                'edit',
                'extract',
                'archive',
                'help',
                'resize',
            ],
            'attributes' => [
                // Hiding dangerous files
                [
                    'pattern' => '/\.(php|py|pl|sh|xml)$/i',
                    'read' => false,
                    'write' => false,
                    'hidden' => true,
                    'locked' => false,
                ],
                // Hiding _DELETED_ files
                [
                    'pattern' => '/_DELETED_/',
                    'read' => false,
                    'write' => false,
                    'hidden' => true,
                    'locked' => false,
                ],
                // Hiding thumbnails
                [
                    'pattern' => '/.tmb/',
                    'read' => false,
                    'write' => false,
                    'hidden' => true,
                    'locked' => false,
                ],
                [
                    'pattern' => '/.thumbs/',
                    'read' => false,
                    'write' => false,
                    'hidden' => true,
                    'locked' => false,
                ],
                [
                    'pattern' => '/.quarantine/',
                    'read' => false,
                    'write' => false,
                    'hidden' => true,
                    'locked' => false,
                ],
            ],
        ];
    }

    public function getOperations(): array
    {
        //https://github.com/Studio-42/elFinder/wiki/Connector-configuration-options-2.1
        $opts = [
            //'debug' => true,
            'bind' => [
                'upload rm mkdir' => [$this, 'manageCommands'],
            ],
            'sessionCloseEarlier' => false,
        ];

        $this->setDrivers();
        $opts['roots'] = $this->getRoots();

        return $opts;
    }

    /**
     * Set drivers from list.
     */
    public function setDrivers()
    {
        foreach ($this->getDriverList() as $driverName) {
            $this->setDriver($driverName);
        }
    }

    /**
     * Sets a driver.
     *
     * @param string $driverName
     */
    public function setDriver($driverName)
    {
        $driverClass = $this->getDriverClass($driverName);

        /** @var Driver $driver */
        $driver = new $driverClass();
        $driver->setName($driverName);
        $driver->setConnector($this);
        $this->addDriver($driver);
    }

    /**
     * Simple function to demonstrate how to control file access using "accessControl" callback.
     * This method will disable accessing files/folders starting from  '.' (dot).
     *
     * @param string $attr   attribute name (read|write|locked|hidden)
     * @param string $path   file path relative to volume root directory started with directory separator
     * @param string $data
     * @param string $volume
     *
     * @return bool|null
     */
    public function access($attr, $path, $data, $volume)
    {
        return 0 === strpos(basename($path), '.')       // if file/folder begins with '.' (dot)
            ? !('read' == $attr || 'write' == $attr)    // set read+write to false, other (locked+hidden) set to true
            : null; // else elFinder decide it itself
    }

    /**
     * @param string $cmd
     * @param array  $result
     * @param array  $args
     * @param Finder $elFinder
     */
    public function manageCommands($cmd, $result, $args, $elFinder)
    {
    }

    /**
     * Available driver list.
     *
     * @return array
     */
    private function getDefaultDriverList()
    {
        return [
            'CourseDriver',
            'CourseUserDriver',
            'DropBoxDriver',
            'HomeDriver',
            'PersonalDriver',
        ];
    }

    /**
     * @param string $driver
     *
     * @return string
     */
    private function getDriverClass($driver)
    {
        return 'Chamilo\CoreBundle\Component\Editor\Driver\\'.$driver;
    }
}
