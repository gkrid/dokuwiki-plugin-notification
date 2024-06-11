<?php

use dokuwiki\Extension\Event;
use dokuwiki\Extension\SyntaxPlugin;

/**
 * DokuWiki Plugin notification (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <it@rid.pl>
 */

class syntax_plugin_notification_list extends SyntaxPlugin
{
    public function getType()
    {
        return 'substition';
    }

    public function getSort()
    {
        return 20;
    }

    public function PType()
    {
        return 'block';
    }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *notification list *-+\n.*?----+', $mode, 'plugin_notification_list');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $lines = explode("\n", $match);
        array_shift($lines);
        array_pop($lines);

        $params = [
            'plugin' => '.*',
            'user' => '$USER$',
            'full' => true,
            'date' => '%Y-%m-%d'
        ];
        foreach ($lines as $line) {
            $pair = explode(':', $line, 2);
            if (count($pair) < 2) {
                continue;
            }
            $key = trim($pair[0]);
            $value = trim($pair[1]);

            if ($key == 'full') {
                $value = $value != '0';
            }

            $params[$key] = $value;
        }
        return $params;
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string        $mode     Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     *
     * @return bool If rendering was successful.
     */

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if (!$data) {
            return false;
        }

        $method = 'render' . ucfirst($mode);
        if (method_exists($this, $method)) {
            call_user_func([$this, $method], $renderer, $data);
            return true;
        }
        return false;
    }

    /**
     * @param $pattern
     * @return array
     */
    protected function getNotificationPlugins($pattern)
    {
        $plugins = [];
        Event::createAndTrigger('PLUGIN_NOTIFICATION_REGISTER_SOURCE', $plugins);
        $plugins = preg_grep('/' . $pattern . '/', $plugins);

        return $plugins;
    }

    /**
     * Render metadata
     *
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     */
    public function renderMetadata(Doku_Renderer $renderer, $data)
    {
        $plugin_name = $this->getPluginName();
        if (!isset($renderer->meta['plugin'][$plugin_name])) {
            $renderer->meta['plugin'][$plugin_name] = ['plugins' => []];
        }
        $plugins = $this->getNotificationPlugins($data['plugin']);
        $old_plugins = $renderer->meta['plugin'][$plugin_name]['plugins'];

        $renderer->meta['plugin'][$plugin_name]['plugins'] = array_unique(array_merge($plugins, $old_plugins));
        if ($data['user'] == '$USER$') {
            $renderer->meta['plugin'][$plugin_name]['dynamic user'] = true;
        }
    }

    /**
     * Render xhtml
     *
     * @param Doku_Renderer $renderer The renderer
     * @param array         $data     The data from the handler() function
     */
    public function renderXhtml(Doku_Renderer $renderer, $data)
    {
        global $INFO;

        $plugins = $this->getNotificationPlugins($data['plugin']);

        if ($data['user'] == '$USER$') {
            $data['user'] = $INFO['client'];
        }

        $notifications_data = [
            'plugins' => $plugins,
            'user' => $data['user'],
            'notifications' => []
        ];
        Event::createAndTrigger('PLUGIN_NOTIFICATION_GATHER', $notifications_data);

        $notifications = $notifications_data['notifications'];

        if (!$notifications) {
            $renderer->doc .= $this->getLang('no notifications');
            return;
        }

        $renderer->doc .= '<ul>';

        usort($notifications, function ($a, $b) {
            if ($a['timestamp'] == $b['timestamp']) {
                return 0;
            }
            return ($a['timestamp'] > $b['timestamp']) ? -1 : 1;
        });

        foreach ($notifications as $notification) {
            $content = $notification[$data['full'] ? 'full' : 'brief'];
            $timestamp = $notification['timestamp'];

            $date = '';
            if ($data['date']) {
                $date = strftime($data['date'], $timestamp);
            }

            $renderer->doc .= "<li class=\"level1\"><div class=\"li\">$date $content</div></li>";
        }
        $renderer->doc .= '</ul>';
    }
}
