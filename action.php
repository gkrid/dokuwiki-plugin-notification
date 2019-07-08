<?php
/**
 * DokuWiki Plugin notification (Action Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <it@rid.pl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class action_plugin_notification extends DokuWiki_Action_Plugin
{

    /**
     * Registers a callback function for a given event
     *
     * @param Doku_Event_Handler $controller DokuWiki's event controller object
     *
     * @return void
     */
    public function register(Doku_Event_Handler $controller)
    {
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handle_parser_cache_use');
    }

    /**
     *
     * @param Doku_Event $event  event object by reference
     * @param mixed      $param  [the parameters passed as fifth argument to register_hook() when this
     *                           handler was registered]
     *
     * @return void
     */
    public function handle_parser_cache_use(Doku_Event $event, $param)
    {
        /** @var cache_renderer $cache */
        $cache = $event->data;

        if(!$cache->page) return;
        //purge only xhtml cache
        if($cache->mode != 'xhtml') return;

        //Check if it is plugins
        $notification = p_get_metadata($cache->page, 'plugin notification');
        if(!$notification) return;

        if ($notification['dynamic user']) {
            $cache->_nocache = true;
            return;
        }

        $data = [
            'plugins' => $notification['plugins'],
            'dependencies' => []
        ];
        trigger_event('PLUGIN_NOTIFICATION_CACHE_DEPENDENCIES', $data);

        //add a media directories to dependencies
        $cache->depends['files'] = array_merge($cache->depends['files'], $data['dependencies']);
    }
}