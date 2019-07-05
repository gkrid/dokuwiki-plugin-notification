<?php
/**
 * DokuWiki Plugin notification (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Szymon Olewniczak <it@rid.pl>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) {
    die();
}

class syntax_plugin_notification_list extends DokuWiki_Syntax_Plugin
{
    function getType() {
        return 'substition';
    }

    function getSort() {
        return 20;
    }

    function PType() {
        return 'block';
    }

    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('----+ *notification list *-+\n.*?----+', $mode,'plugin_notification_list');
    }

    function handle($match, $state, $pos, Doku_Handler $handler){
        $lines = explode("\n", $match);
        array_shift($lines);
        array_pop($lines);

        $params = [
            'plugin' => '.*',
            'full' => true,
            'date' => 'Y-m-d'
        ];
        foreach ($lines as $line) {
            $pair = explode(':', $line, 2);
            if (count($pair) < 2) {
                continue;
            }
            $key = trim($pair[0]);
            $value = trim($pair[1]);

            if ($key == 'full') {
                $value = $value == '0' ? false : true;
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

        $method = 'render'.ucfirst($mode);
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
        trigger_event('PLUGIN_NOTIFICATION_REGISTER_SOURCE', $plugins);
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

        $plugins = $this->getNotificationPlugins($data['plugin']);
        $old_plugins = $renderer->meta['plugin'][$plugin_name];
        if (!$old_plugins) {
            $old_plugins = [];
        }

        $renderer->meta['plugin'][$plugin_name] = array_unique(array_merge($plugins, $old_plugins));
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

        $notifications_data = [
            'plugins' => $plugins,
            'user' => $INFO['client'],
            'notifications' => []
        ];
        trigger_event('PLUGIN_NOTIFICATION_GATHER', $notifications_data);

        $notifications = $notifications_data['notifications'];

        if (!$notifications) {
            $renderer->doc .= $this->getLang('no notifications');
            return;
        }

        $renderer->doc .= '<ul>';

        usort($notifications, function($a, $b) {
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

//            $li .= p_render($mode, p_get_instructions($content), $info);
            $renderer->doc .= "<li class=\"level1\"><div class=\"li\">$date $content</div></li>";
        }
        $renderer->doc .= '</ul>';
    }
}

