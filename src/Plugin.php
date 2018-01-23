<?php
/**
 * Git Hooks Management through Composer.
 *
 * @package   PHPComposter\PHPComposter
 * @author    Alain Schlesser <alain.schlesser@gmail.com>
 * @license   MIT
 * @link      http://www.brightnucleus.com/
 * @copyright 2016 Alain Schlesser, Bright Nucleus
 */

namespace PHPComposter\PHPComposter;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

/**
 * Class Plugin.
 *
 * This main class activates and sets up the PHP Composter system within the package's .git folder.
 *
 * @since   0.1.0
 *
 * @package PHPComposter\PHPComposter
 * @author  Alain Schlesser <alain.schlesser@gmail.com>
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * Instance of the IO interface.
     *
     * @var IOInterface
     *
     * @since 0.1.0
     */
    protected static $io;

    /**
     * Get the event subscriber configuration for this plugin.
     *
     * @return array<string,string> The events to listen to, and their associated handlers.
     */
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => 'persistConfig',
            ScriptEvents::POST_UPDATE_CMD  => 'persistConfig',
        );
    }

    /**
     * Persist the stored configuration.
     *
     * @since 0.1.0
     *
     * @param Event $event Event that was triggered.
     */
    public static function persistConfig(Event $event)
    {
        $filesystem = new Filesystem();
        $path       = Paths::getPath('git_composter');
        $filesystem->ensureDirectoryExists($path);

        // create symlinks for hooks
        file_put_contents(Paths::getPath('git_config'), static::getConfig());

        // save where the vendor dir is
        file_put_contents(Paths::getPath('env_config'), Paths::getPath('env_path'));
    }

    /**
     * Generate the config file.
     *
     * @since 0.1.0
     *
     * @return string Generated Config file.
     */
    public static function getConfig()
    {
        $output = '<?php' . PHP_EOL;
        $output .= '// PHP Composter configuration file.' . PHP_EOL;
        $output .= '// Do not edit, this file is generated automatically.' . PHP_EOL;
        $output .= '// Timestamp: ' . date('Y/m/d H:m:s') . PHP_EOL;
        $output .= PHP_EOL;
        $output .= 'return array(' . PHP_EOL;

        foreach (Hook::ALL_SUPPORTED as $hook) {
            $entries = HookConfig::getEntries($hook);
            $output  .= '    \'' . $hook . '\' => array(' . PHP_EOL;
            foreach ($entries as $priority => $methods) {
                $output .= '        ' . $priority . ' => array(' . PHP_EOL;
                foreach ($methods as $method) {
                    $output .= '            \'' . $method . '\',' . PHP_EOL;
                }
                $output .= '        ),' . PHP_EOL;
            }
            $output .= '    ),' . PHP_EOL;
        }

        $output .= ');' . PHP_EOL;

        return $output;
    }

    /**
     * Activate the Composer plugin.
     *
     * @since 0.1.0
     *
     * @param Composer    $composer Reference to the Composer instance.
     * @param IOInterface $io       Reference to the IO interface.
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        static::$io = $io;
        if (static::$io->isVerbose()) {
            static::$io->write('Activating PHP Composter plugin', true);
        }
        $installer = new Installer(static::$io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);

        $filesystem = new Filesystem();
        $this->cleanUp($filesystem);
        $this->linkBootstrapFiles($filesystem);
        $this->createGitHooks($filesystem);
    }

    /**
     * Clean up previous installation.
     *
     * @since 0.1.0
     *
     * @param Filesystem $filesystem Reference to the Filesystem instance.
     */
    protected function cleanUp(Filesystem $filesystem)
    {
        $composterPath = Paths::getPath('git_composter');
        if (static::$io->isVeryVerbose()) {
            static::$io->write(sprintf(
                'Removing previous PHP Composter actions at %1$s',
                $composterPath
            ), true);
        }
        $filesystem->emptyDirectory($composterPath, true);

        $composterTemplate = Paths::getPath('root_template');
        if (static::$io->isVeryVerbose()) {
            static::$io->write(sprintf(
                'Removing previous PHP Composter code at %1$s',
                $composterTemplate
            ), true);
        }
        $filesystem->emptyDirectory($composterTemplate, true);
    }

    /**
     * Symlink the bootstrapping code into the .git folder.
     *
     * @since 0.1.0
     *
     * @param Filesystem $filesystem Reference to the Filesystem instance.
     */
    protected function linkBootstrapFiles(Filesystem $filesystem)
    {
        $rootTemplate      = Paths::getPath('root_template');
        $composterTemplate = Paths::getPath('git_template');

        $files = array(
            'bootstrap.php',
        );

        $filesystem->ensureDirectoryExists($rootTemplate);

        foreach ($files as $file) {
            if (static::$io->isVeryVerbose()) {
                static::$io->write(sprintf(
                    'Symlinking %1$s to %2$s',
                    $rootTemplate . $file,
                    $composterTemplate . $file
                ));
            }
            $filesystem->relativeSymlink($composterTemplate . $file, $rootTemplate . $file);
        }
    }

    /**
     * Symlink each known Git hook to the PHP Composter bootstrapping script.
     *
     * @since 0.1.0
     *
     * @param Filesystem $filesystem Reference to the Filesystem instance.
     */
    protected function createGitHooks(Filesystem $filesystem)
    {

        $hooksPath     = Paths::getPath('root_hooks');
        $gitScriptPath = Paths::getPath('git_script');

        $filesystem->ensureDirectoryExists($hooksPath);

        foreach (Hook::ALL_SUPPORTED as $githook) {
            $hookPath = $hooksPath . $githook;
            if (static::$io->isDebug()) {
                static::$io->write(sprintf(
                    'Symlinking %1$s to %2$s',
                    $hookPath,
                    $gitScriptPath
                ));
            }
            $filesystem->relativeSymlink($gitScriptPath, $hookPath);
        }
    }
}
