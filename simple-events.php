<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Page\Collection;
use Grav\Common\Filesystem\Folder;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class SimpleEventsPlugin
 * @package Grav\Plugin
 */
class SimpleEventsPlugin extends Plugin
{
    protected $clearEvents = false;
    protected $checkUnpublishedDates = false;
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onTwigTemplatePaths'  => ['onTwigTemplatePaths', 0],
            'onGetPageTemplates'   => ['onGetPageTemplates', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
      if ( $this->isAdmin() ) {
          // enable the save event so past events can be deleted
          $this->enable([
            //'onAdminSave'        => ['onAdminSave', 0]
          ]);
  		}

        // Enable the main event we are interested in
        $this->enable([
            'onBuildPagesInitialized' => ['onBuildPagesInitialized', 0],
            'onPagesInitialized' => ['onPagesInitialized', 0],
            'onTwigInitialized' => ['onTwigInitialized', 0]
        ]);
    }

    /**
     * Add blueprint directory to page templates.
     */
    public function onGetPageTemplates(Event $event)
    {
        $types = $event->types;
        $locator = Grav::instance()['locator'];
        $types->scanBlueprints($locator->findResource('plugin://' . $this->name . '/blueprints'));
        $types->scanTemplates($locator->findResource('plugin://' . $this->name . '/templates'));
    }

    /**
     * Add templates to twig paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Register the new Twig filter.
     */
    public function onTwigInitialized(Event $e)
    {
        $this->grav['twig']->twig()->addFilter(
            new \Twig_SimpleFilter('linked', [$this, 'linkIt'])
        );
        $this->grav['twig']->twig()->addFunction(
            new \Twig_SimpleFunction('linkit', [$this, 'linkIt'])
        );
    }

    /**
     * Turn part of a string in [] into a link.
     */
    public function linkIt($string, $url)
    {
      if ($url) {
        if (substr($url, 0, 4) == "http") {
          $addr = $url;
        } else {
          $addr = $this->grav['base_url'].'/'.$url;
        }
        if(preg_match('/(.*)\[(.*)\](.*)/', $string, $matches)) {
          $linked = $matches[1].'<a href="'.$addr.'">'.$matches[2].'</a>'.$matches[3];
        } else {
          $linked = '<a href="'.$addr.'">'.$string.'</a>';
        }
        return $linked;
      } else {
        return $string;
      }
    }

    /*** cleanup on cache rebuild contributed by paamtbau@Grav discourse <3 ***/
    /** Cleanup up expired events when page collection has been build */
    public function onPagesInitialized()
    {
      if ($this->grav['config']->plugins['simple-events']['delete_old'] ?? false) {
        if ($this->clearEvents) {
          $pages = $this->grav['pages'];
          $unpublishedEvents = $pages->all()->ofType('event')->nonPublished();
          //dump($pages->all()->ofType('event')->nonPublished()); exit;

          foreach($unpublishedEvents as $event) {
            dump($event->path());
            Folder::delete($event->path()); // !! may not work for multilang!!
          }
        }
      }

      if ($this->checkUnpublishedDates) {
        // set unpublish datetime to header.start/header.end plus time set in options.
        $pages = $this->grav['pages'];
        $events = $pages->all()->ofType('event')->order('header.start');

        foreach($events as $e) {
          if (!$e->unpublishDate()) {
            $header_old = $e->header();
            // check if header.start is timestamp (int) or time string
            $start = $header_old->start;
            if (is_int($start)) {
              $start = date("Y-m-d", $start);
            }

            $time = " 0:00";
            if (!empty($this->grav['config']->plugins['simple-events']['unpublish_time'])) {
              $time = " ".$this->grav['config']->plugins['simple-events']['unpublish_time'];
            }

            $datetime = $start.$time;
            $e->unpublishDate($datetime);
            //dump($e->file());
            $e->save();
          }
        }
      }
    }

    /** Fired when Grav needs to refresh/build the cache */
    public function onBuildPagesInitialized()
    {
        //$this->clearEvents = true;
        //$this->checkUnpublishedDates = true;
    }
}
