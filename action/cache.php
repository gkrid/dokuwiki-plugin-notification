<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Extension\Event;

/**
 * DokuWiki Plugin notification (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <it@rid.pl>
 */

class action_plugin_notification_cache extends ActionPlugin
{
    /**
     * Registers a callback function for a given event
     *
     * @param EventHandler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handleParserCacheUse');
    }

    /**
     *
     * @param Event $event event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handleParserCacheUse(Event $event, $param)
    {
        /** @var cache_renderer $cache */
        $cache = $event->data;

        if (!$cache->page) return;
        //purge only xhtml cache
        if ($cache->mode != 'xhtml') return;

        //Check if it is plugins
        $notification = p_get_metadata($cache->page, 'plugin notification');
        if (!$notification) return;

        if ($notification['dynamic user']) {
            $cache->_nocache = true;
            return;
        }

        $data = [
            'plugins' => $notification['plugins'],
            'dependencies' => [],
            '_nocache' => false
        ];
        Event::createAndTrigger('PLUGIN_NOTIFICATION_CACHE_DEPENDENCIES', $data);

        //add a media directories to dependencies
        $cache->depends['files'] = array_merge($cache->depends['files'], $data['dependencies']);
        $cache->_nocache = $data['_nocache'];
    }
}
